<?php
/**
 * Setup verification script. Run this in your shell:
 *   php verify-setup.php
 *
 * Checks PHP version, extensions, file permissions, and database connectivity
 * to catch setup issues before they cause cryptic 500 errors.
 */

declare(strict_types=1);

echo "==================================\n";
echo "PowerMedia Gallery — Setup Verification\n";
echo "==================================\n\n";

$errors = [];
$warnings = [];
$info = [];

// PHP version
$phpVersion = PHP_VERSION;
echo "PHP Version: $phpVersion\n";
if (version_compare($phpVersion, '8.2.0', '<')) {
    $errors[] = "PHP 8.2+ required (current: $phpVersion)";
} else {
    echo "✓ PHP version OK\n";
}
echo "\n";

// Required extensions
echo "Checking PHP extensions...\n";
$requiredExts = ['pdo', 'pdo_mysql', 'gd', 'zip', 'exif'];
foreach ($requiredExts as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext\n";
    } else {
        $errors[] = "Missing extension: $ext";
    }
}
echo "\n";

// File permissions
echo "Checking file permissions...\n";
$folders = [
    'storage/hires' => 'Original photos',
    'storage/zips' => 'Download bundles',
    'storage/tmp' => 'Upload staging',
    'public/media/d' => 'Derivatives',
    'config' => 'Configuration',
];

foreach ($folders as $path => $desc) {
    if (!is_dir($path)) {
        $errors[] = "Missing folder: $path ($desc)";
    } elseif (!is_writable($path)) {
        $errors[] = "Not writable: $path ($desc) — run: chmod 755 $path";
    } else {
        echo "✓ $path writable\n";
    }
}
echo "\n";

// Config
echo "Checking configuration...\n";
if (!file_exists('config/config.php')) {
    if (file_exists('config/config.example.php')) {
        $info[] = "config/config.php not found (expected in Docker with auto-config, or after manual copy from config.example.php)";
    } else {
        $errors[] = "Missing config/config.example.php";
    }
} else {
    echo "✓ config/config.php exists\n";

    // Try to load config
    try {
        $config = require 'config/config.php';
        echo "✓ config/config.php is valid PHP\n";

        // Check required keys
        $requiredKeys = ['db', 'site', 'currency', 'security'];
        foreach ($requiredKeys as $key) {
            if (isset($config[$key])) {
                echo "✓ config['$key'] present\n";
            } else {
                $warnings[] = "config['$key'] missing";
            }
        }
    } catch (Throwable $e) {
        $errors[] = "config/config.php failed to load: " . $e->getMessage();
    }
}
echo "\n";

// Database
echo "Checking database connection...\n";
if (file_exists('config/config.php')) {
    try {
        $config = require 'config/config.php';
        $dbConfig = $config['db'] ?? [];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $dbConfig['host'] ?? 'localhost',
            $dbConfig['port'] ?? 3306,
            $dbConfig['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO($dsn, $dbConfig['user'] ?? '', $dbConfig['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        echo "✓ Database connected\n";

        // Check schema
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        $tableCount = (int)$result->fetchColumn();
        echo "✓ Database '$dbConfig[name]' has $tableCount tables\n";

        if ($tableCount < 10) {
            $info[] = "Schema incomplete ($tableCount tables found, expected 20+). Visit /admin/migrations to import.";
        } else {
            echo "✓ Schema appears complete\n";
        }
    } catch (PDOException $e) {
        $errors[] = "Database connection failed: " . $e->getMessage();
    }
} else {
    $info[] = "Skipping database check (config not yet set up)";
}
echo "\n";

// Summary
echo "==================================\n";
if (empty($errors)) {
    echo "✓ Setup verified!\n";
    if (!empty($warnings)) {
        echo "\nWarnings:\n";
        foreach ($warnings as $w) {
            echo "⚠ $w\n";
        }
    }
    if (!empty($info)) {
        echo "\nInfo:\n";
        foreach ($info as $i) {
            echo "ℹ $i\n";
        }
    }
    echo "\nNext steps:\n";
    echo "1. Visit http://localhost:8080/admin/setup (Docker) or http://localhost:8888/admin/setup (MAMP)\n";
    echo "2. Create your admin account\n";
    echo "3. Start uploading photos!\n";
} else {
    echo count($errors) . " error(s) found. Fix these before proceeding:\n\n";
    foreach ($errors as $e) {
        echo "✗ $e\n";
    }
    exit(1);
}
echo "==================================\n";

<?php
/**
 * Shared hosting setup helper. Run this after uploading via FTP:
 *   php host-setup.php
 *
 * This script helps with:
 * - Verifying directory structure
 * - Checking PHP version and extensions
 * - Creating necessary directories
 * - Validating config.php
 * - Importing database schema
 * - Troubleshooting common issues
 */

declare(strict_types=1);

echo "PowerMedia Gallery - Shared Hosting Setup Helper\n";
echo "================================================\n\n";

// Check PHP version
echo "1. Checking PHP version... ";
if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
    echo "✓ " . PHP_VERSION . "\n";
} else {
    echo "✗ PHP 8.2+ required (current: " . PHP_VERSION . ")\n";
    exit(1);
}

// Check extensions
echo "2. Checking required extensions:\n";
$requiredExts = ['pdo', 'pdo_mysql', 'gd', 'zip', 'exif'];
$allExtensionsOk = true;
foreach ($requiredExts as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ $ext\n";
    } else {
        echo "   ✗ Missing: $ext (contact your host to enable it)\n";
        $allExtensionsOk = false;
    }
}

if (!$allExtensionsOk) {
    echo "\nExtensions are missing. Contact your hosting support.\n";
    exit(1);
}

// Check directories
echo "\n3. Checking directory structure:\n";
$dirs = [
    'storage/hires' => 'Original photos (must be writable)',
    'storage/zips' => 'Download bundles (must be writable)',
    'storage/tmp' => 'Upload staging (must be writable)',
    'public/media/d' => 'Derivatives (must be writable)',
    'config' => 'Config folder (must be writable)',
];

foreach ($dirs as $dir => $desc) {
    if (!is_dir($dir)) {
        echo "   ✗ Missing: $dir\n";
        if (!@mkdir($dir, 0755, true)) {
            echo "      Could not create. Try via FTP: create folders $dir\n";
        } else {
            echo "      ✓ Created\n";
        }
    } elseif (!is_writable($dir)) {
        echo "   ✗ Not writable: $dir\n";
        echo "      Contact your host to set permissions to 755 or 777\n";
    } else {
        echo "   ✓ $dir ($desc)\n";
    }
}

// Check config
echo "\n4. Checking configuration:\n";
if (file_exists('config/config.php')) {
    echo "   ✓ config/config.php exists\n";

    try {
        $config = require 'config/config.php';
        echo "   ✓ config/config.php is valid PHP\n";

        // Validate config keys
        if (isset($config['db']) && isset($config['site']) && isset($config['security'])) {
            echo "   ✓ All required config sections present\n";
        } else {
            echo "   ✗ Missing config sections\n";
        }
    } catch (Throwable $e) {
        echo "   ✗ config/config.php has errors: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "   ⚠ config/config.php not found\n";
    echo "   Copy config/config.example.php to config/config.php and edit it\n";
    echo "   (You need: database credentials, Stripe keys, HMAC key)\n";
    exit(1);
}

// Try database connection
echo "\n5. Checking database connection:\n";
try {
    $config = require 'config/config.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $config['db']['host'] ?? 'localhost',
        $config['db']['port'] ?? 3306,
        $config['db']['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $config['db']['user'] ?? '', $config['db']['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "   ✓ Database connected\n";

    // Check if schema is imported
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM migrations");
        $migrationCount = (int)$result->fetchColumn();

        if ($migrationCount > 0) {
            echo "   ✓ Schema already imported\n";
        } else {
            echo "   ⚠ Schema exists but migrations table empty\n";
        }
    } catch (PDOException $e) {
        echo "   ⚠ Schema not imported yet. You need to:\n";
        echo "      1. Go to your hosting control panel (cPanel, Plesk, etc.)\n";
        echo "      2. Open phpMyAdmin\n";
        echo "      3. Select database: " . ($config['db']['name'] ?? 'photo_gallery') . "\n";
        echo "      4. Click 'Import' tab\n";
        echo "      5. Choose file: migrations/001_initial_schema.sql\n";
        echo "      6. Click 'Go'\n";
        echo "\n      Or if your host supports SSH:\n";
        echo "      mysql -h " . ($config['db']['host'] ?? 'localhost') . " -u " . ($config['db']['user'] ?? 'gallery') . " -p " . ($config['db']['name'] ?? 'photo_gallery') . " < migrations/001_initial_schema.sql\n";
    }

} catch (PDOException $e) {
    echo "   ✗ Database connection failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Check your config/config.php database settings\n";
    exit(1);
}

// Final checklist
echo "\n6. Setup verification:\n";
echo "   - Go to your site: https://yourdomain.com/admin/setup\n";
echo "   - Create your admin account\n";
echo "   - Upload some test photos\n";
echo "   - Check that derivatives are generated\n";
echo "\n✓ Setup verification complete!\n";
echo "\nNext steps:\n";
echo "   1. Set up cron job (see INSTALL.md)\n";
echo "   2. Configure Stripe keys in /admin after login\n";
echo "   3. Test with some photos\n";
echo "   4. Review SECURITY.md before going live\n";

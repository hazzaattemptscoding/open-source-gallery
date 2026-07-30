<?php
/**
 * Gallery - Universal Installer
 *
 * Works on: Windows, Mac, Linux, shared hosting, VPS, Docker
 * Run once: php install.php
 *
 * Handles everything:
 * - Creates config/config.php
 * - Creates/verifies database
 * - Imports schema
 * - Sets up directories
 * - Verifies installation
 */

declare(strict_types=1);

$GALLERY_VERSION = "1.0.0";
define('NEWLINE', PHP_EOL);

// Colors for terminal output
$colors = [
    'reset' => "\033[0m",
    'bold' => "\033[1m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'red' => "\033[31m",
    'blue' => "\033[34m",
];

function color($text, $color) {
    global $colors;
    if (!isset($colors[$color])) return $text;
    return $colors[$color] . $text . $colors['reset'];
}

function header_text($text) {
    echo NEWLINE . color("=== $text ===", 'blue') . NEWLINE . NEWLINE;
}

function success($text) {
    echo color("✓ ", 'green') . $text . NEWLINE;
}

function warning($text) {
    echo color("⚠ ", 'yellow') . $text . NEWLINE;
}

function error($text, $exit = true) {
    echo color("✗ ", 'red') . $text . NEWLINE;
    if ($exit) exit(1);
}

function ask($question, $default = '') {
    $prompt = color($question, 'bold');
    if ($default) $prompt .= " [$default]";
    $prompt .= ": ";

    echo $prompt;
    $input = trim(fgets(STDIN) ?: '');
    return $input ?: $default;
}

// ============================================================================
// START INSTALLATION
// ============================================================================

echo color("Gallery Installer v$GALLERY_VERSION", 'bold') . NEWLINE;
echo "Universal installation for any environment (Docker, Mac, Linux, Windows, hosting)" . NEWLINE;

// Check PHP version
header_text("Checking Environment");

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    error("PHP 8.2+ required (current: " . PHP_VERSION . ")");
}
success("PHP " . PHP_VERSION);

// Check extensions
$requiredExts = ['pdo', 'pdo_mysql', 'gd', 'zip', 'exif'];
foreach ($requiredExts as $ext) {
    if (!extension_loaded($ext)) {
        error("Missing PHP extension: $ext (ask host to enable it)");
    }
    success("Extension: $ext");
}

// Check directories exist
header_text("Checking Directories");

$dirs = [
    'storage/hires',
    'storage/zips',
    'storage/tmp',
    'public/media/d',
    'config',
    'migrations',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            error("Cannot create $dir. Check permissions or create manually.");
        }
        success("Created: $dir");
    } else {
        success("Found: $dir");
    }
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

header_text("Database Configuration");

echo "Which database do you want to use?" . NEWLINE;
echo "1. SQLite (recommended for local development) — single file, no server needed" . NEWLINE;
echo "2. MySQL/MariaDB (for production) — traditional database server" . NEWLINE . NEWLINE;

$dbChoice = ask("Choose database", "1");

if ($dbChoice === "1" || $dbChoice === "sqlite") {
    $dbDriver = "sqlite";
    $dbPath = ask("SQLite database path", "storage/gallery.sqlite");
    $dbConfig = [
        'driver' => 'sqlite',
        'path'   => $dbPath,
    ];
} else {
    $dbDriver = "mysql";
    echo NEWLINE . "Provide database credentials. If you don't have a database yet, we'll create one." . NEWLINE . NEWLINE;

    $dbHost = ask("Database host", "localhost");
    $dbPort = ask("Database port", "3306");
    $dbName = ask("Database name", "photo_gallery");
    $dbUser = ask("Database username", "gallery");
    $dbPass = ask("Database password (leave blank for no password)", "");

    $dbConfig = [
        'driver'  => 'mysql',
        'host'    => $dbHost,
        'port'    => (int)$dbPort,
        'name'    => $dbName,
        'user'    => $dbUser,
        'pass'    => $dbPass,
        'charset' => 'utf8mb4',
    ];
}

echo NEWLINE . "Attempting to connect to database..." . NEWLINE;

try {
    if ($dbDriver === 'sqlite') {
        $pdo = new PDO("sqlite:" . $dbConfig['path'], null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // SQLite pragmas
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        success("Connected to SQLite");
        success("Database file: " . $dbConfig['path']);

        // Check if schema exists
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
        $hasSchema = (bool)$stmt->fetch();

        if ($hasSchema) {
            success("Schema already imported");
        } else {
            warning("Schema not yet imported. Will import now...");
            $schemaFile = 'migrations/001_initial_schema.sqlite.sql';
            if (!file_exists($schemaFile)) {
                error("Schema file not found: $schemaFile");
            }

            $schema = file_get_contents($schemaFile);
            $statements = array_filter(
                explode(';', $schema),
                fn($s) => trim($s) && !str_starts_with(trim($s), '--')
            );

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!$stmt) continue;
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    warning("SQL: " . substr($e->getMessage(), 0, 50));
                }
            }

            success("Schema imported");
        }
    } else {
        // MySQL setup
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'] ?: 'root', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        success("Connected to MySQL server");

        // Create database if it doesn't exist
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            success("Database '{$dbConfig['name']}' ready");
        } catch (PDOException $e) {
            warning("Could not create database (may already exist): " . $e->getMessage());
        }

        // Select database
        $pdo->exec("USE `{$dbConfig['name']}`");
        success("Selected database: {$dbConfig['name']}");

        // Check if schema exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
        $hasSchema = (bool)$stmt->fetch();

        if ($hasSchema) {
            success("Schema already imported");
        } else {
            warning("Schema not yet imported. Will import now...");
            $schemaFile = 'migrations/001_initial_schema.sql';
            if (!file_exists($schemaFile)) {
                error("Schema file not found: $schemaFile");
            }

            $schema = file_get_contents($schemaFile);
            $statements = array_filter(
                explode(';', $schema),
                fn($s) => trim($s) && !str_starts_with(trim($s), '--')
            );

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!$stmt) continue;
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    warning("SQL: " . substr($e->getMessage(), 0, 50));
                }
            }

            success("Schema imported");
        }
    }

} catch (PDOException $e) {
    warning("Database connection failed: " . $e->getMessage());
    echo NEWLINE;
    echo color("Troubleshooting:", 'bold') . NEWLINE;
    if ($dbDriver === 'sqlite') {
        echo "1. Make sure the storage/ directory is writable: chmod 755 storage/" . NEWLINE;
    } else {
        echo "1. Make sure MySQL/MariaDB is running" . NEWLINE;
        echo "   On Mac: brew services start mysql" . NEWLINE;
        echo "   On Linux: sudo systemctl start mysql" . NEWLINE;
        echo NEWLINE;
        echo "2. Verify your credentials are correct" . NEWLINE;
    }
    echo NEWLINE;
    echo "3. Or try Docker instead (easiest):" . NEWLINE;
    echo "   docker-compose up" . NEWLINE;
    echo NEWLINE;
    error("Installation requires a working database connection.");
}

// ============================================================================
// SITE CONFIGURATION
// ============================================================================

header_text("Site Configuration");

$siteName = ask("Site name (shown in admin)", "Photo Gallery");
$baseUrl = ask("Base URL (no trailing slash)", "http://localhost:8080");
$supportEmail = ask("Support email address", "support@example.com");
$currency = ask("Currency (ISO 4217 code)", "GBP");

// ============================================================================
// CREATE CONFIG FILE
// ============================================================================

header_text("Creating Configuration");

// Generate secure keys
$hmacKey = bin2hex(random_bytes(32));
$cronSecret = bin2hex(random_bytes(32));

$config = [
    'db' => $dbConfig,
    'site' => [
        'name' => $siteName,
        'base_url' => $baseUrl,
        'support_email' => $supportEmail,
    ],
    'currency' => $currency,
    'branding' => ['theme' => 'podium-ink'],
    'stripe' => [
        'publishable_key' => '',
        'secret_key' => '',
        'webhook_secret' => '',
    ],
    'smtp' => [
        'host' => '',
        'port' => 587,
        'user' => '',
        'pass' => '',
        'from_email' => '',
        'from_name' => '',
    ],
    'discounts' => [
        10 => 0.15,
        20 => 0.20,
        50 => 0.25,
    ],
    'security' => [
        'hmac_key' => $hmacKey,
        'cron_secret' => $cronSecret,
    ],
    'timezone' => 'UTC',
];

$configCode = "<?php\nreturn " . var_export($config, true) . ";\n";

if (!@file_put_contents('config/config.php', $configCode)) {
    error("Cannot write config/config.php. Check directory permissions.");
}

if (!@chmod('config/config.php', 0600)) {
    warning("Could not set secure permissions on config/config.php");
}

success("Created config/config.php");

// ============================================================================
// VERIFY INSTALLATION
// ============================================================================

header_text("Installation Complete!");

echo color("Your gallery is ready to use:", 'bold') . NEWLINE . NEWLINE;

echo "  URL: " . color($baseUrl, 'green') . NEWLINE;
echo "  Admin setup: " . color($baseUrl . '/admin/setup', 'green') . NEWLINE . NEWLINE;

echo "Next step:" . NEWLINE;
echo "  1. Visit " . color($baseUrl . '/admin/setup', 'green') . NEWLINE;
echo "  2. Create your admin account" . NEWLINE;
echo "  3. Start uploading photos!" . NEWLINE . NEWLINE;

// If not Docker, show cron setup
if (!getenv('DOCKER_CONTAINER')) {
    echo color("Important for shared hosting:", 'yellow') . NEWLINE;
    echo "  Set up a cron job to run every 5 minutes:" . NEWLINE;
    echo "  " . color("curl " . $baseUrl . "/cron/" . $cronSecret, 'bold') . NEWLINE;
    echo "  Or: " . color("php " . __DIR__ . "/cron/run.php", 'bold') . NEWLINE . NEWLINE;
}

echo "Questions? See " . color("INSTALL.md", 'bold') . " or " . color("QUICKSTART.md", 'bold') . NEWLINE;

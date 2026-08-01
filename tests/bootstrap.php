<?php
/**
 * Test bootstrap: Set up test environment and database fixtures.
 */

declare(strict_types=1);

// Define root path for loading app code.
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';

// Load .env.test if available, otherwise use test defaults.
if (file_exists(APP_ROOT . '/.env.test')) {
    $env = parse_ini_file(APP_ROOT . '/.env.test', true);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// Database config for tests. Default to SQLite for dev convenience;
// override TEST_DB_DRIVER=mysql for CI/production environments.
$dbDriver = getenv('TEST_DB_DRIVER') ?: 'sqlite';

if ($dbDriver === 'mysql') {
    $dbHost = getenv('TEST_DB_HOST') ?: 'localhost';
    $dbUser = getenv('TEST_DB_USER') ?: 'root';
    $dbPass = getenv('TEST_DB_PASSWORD') ?: '';
    $dbName = getenv('TEST_DB_NAME') ?: 'gallery_test';

    if (!str_contains(strtolower($dbName), 'test')) {
        fwrite(STDERR, "Refusing to run: TEST_DB_NAME ('$dbName') doesn't contain \"test\". " .
            "This bootstrap drops and recreates that database on every run.\n");
        exit(1);
    }

    $dsn = "mysql:host=$dbHost;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    try {
        $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
        $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Exception $e) {
        echo "Warning: Could not create test database. Tests may fail.\n";
    }

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} else {
    // SQLite (default for dev)
    $dbPath = getenv('TEST_DB_PATH') ?: '/tmp/gallery_test.db';
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }

    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
}

// Run migrations. 001 is a special case per app/lib/migrations.php's own
// doc comment (it creates the `migrations` table the runner checks against),
// so it's applied directly here; everything after 001 goes through the real
// runner so the test schema matches production exactly instead of drifting
// to whatever subset of tables migration 001 alone happened to create.
require_once APP_ROOT . '/app/lib/migrations.php';

if ($dbDriver === 'sqlite') {
    $migrationFile = APP_ROOT . '/migrations/001_initial_schema.sqlite.sql';
} else {
    $migrationFile = APP_ROOT . '/migrations/001_initial_schema.sql';
}

if (file_exists($migrationFile)) {
    $sql = file_get_contents($migrationFile);
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?=\n|$)/m', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt . ';');
        }
    }
}

$migrationsDir = APP_ROOT . '/migrations';
foreach (migrations_pending($pdo, $migrationsDir) as $filename) {
    migrations_apply($pdo, $migrationsDir, $filename);
}

// Store PDO instance globally for tests to access.
$GLOBALS['test_pdo'] = $pdo;

<?php
/**
 * Test bootstrap: Set up test environment and database fixtures.
 */

declare(strict_types=1);

// Define root path for loading app code.
define('APP_ROOT', dirname(__DIR__));

// Load .env.test if available, otherwise use test defaults.
if (file_exists(APP_ROOT . '/.env.test')) {
    $env = parse_ini_file(APP_ROOT . '/.env.test', true);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// Database config for tests (uses separate test database).
$dbHost = getenv('TEST_DB_HOST') ?: 'localhost';
$dbUser = getenv('TEST_DB_USER') ?: 'root';
$dbPass = getenv('TEST_DB_PASSWORD') ?: '';
$dbName = getenv('TEST_DB_NAME') ?: 'gallery_test';

// Create PDO instance for test database.
$dsn = "mysql:host=$dbHost;charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Create test database (drop if exists).
try {
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Exception $e) {
    echo "Warning: Could not create test database. Tests may fail.\n";
}

// Connect to test database.
$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Run migrations.
$migrationFile = APP_ROOT . '/migrations/001_initial_schema.sql';
if (file_exists($migrationFile)) {
    $sql = file_get_contents($migrationFile);
    $pdo->exec($sql);
}

// Store PDO instance globally for tests to access.
$GLOBALS['test_pdo'] = $pdo;

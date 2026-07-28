<?php
/**
 * Loaded by every entry point (public/index.php, cron/run.php, migration
 * runner) before anything else. Loads config, opens the DB connection, and
 * sets error handling so nothing ever leaks a stack trace to a visitor.
 *
 * Provides $config (array) and $pdo (PDO) to whatever includes this file.
 */

declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';

if (!is_file($configPath)) {
    // Fails loudly and immediately rather than limping on with defaults —
    // a self-hoster who forgot to copy config.example.php needs to know now,
    // not after they've watermarked a gallery of test photos.
    fwrite(STDERR, "Missing config/config.php. Copy config/config.example.php to config/config.php and fill in real values.\n");
    http_response_code(500);
    exit(1);
}

$config = require $configPath;

date_default_timezone_set($config['timezone'] ?? 'UTC');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/lib/db.php';

$pdo = db_connect($config['db']);

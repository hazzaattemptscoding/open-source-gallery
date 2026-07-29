<?php
/**
 * Bootstrap for remote admin mode entry point (admin/index.php).
 * Checks admin_mode config and creates appropriate database connection.
 * When admin_mode is 'local', delegates to regular bootstrap.
 * When admin_mode is 'remote', creates connection to production database.
 *
 * Provides $config (array) and $pdo (PDO) to the admin entry point.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap-config.php';

$configPath = __DIR__ . '/../config/config.php';

if (!is_file($configPath)) {
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
require_once __DIR__ . '/lib/remote-db.php';

$adminMode = $config['admin_mode'] ?? 'local';

if ($adminMode === 'remote') {
    // Remote admin mode: connect to production database over network
    try {
        $pdo = create_remote_db_connection($config);
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed to connect to remote database: " . $e->getMessage() . "\n");
        http_response_code(500);
        exit(1);
    }
} else {
    // Local admin mode: use regular database connection
    $pdo = db_connect($config['db']);
}

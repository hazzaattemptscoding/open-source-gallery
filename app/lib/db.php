<?php
/**
 * PDO connection factory supporting both SQLite (local dev) and MySQL (production).
 * All database queries go through here so connection options are centralized.
 *
 * SQLite: Single-file database, no server needed. Perfect for local development
 * and previewing. Works out of the box.
 *
 * MySQL: Traditional database server. For production deployments.
 */

declare(strict_types=1);

/**
 * Open a PDO connection from the 'db' section of config.php.
 *
 * Supports two drivers:
 * - 'sqlite': File-based, no server required
 * - 'mysql': Traditional MySQL/MariaDB server
 *
 * @param array $dbConfig Database configuration array
 */
function db_connect(array $dbConfig): PDO
{
    $driver = $dbConfig['driver'] ?? 'mysql';

    if ($driver === 'sqlite') {
        return db_connect_sqlite($dbConfig);
    } elseif ($driver === 'mysql') {
        return db_connect_mysql($dbConfig);
    } else {
        throw new InvalidArgumentException("Unknown database driver: $driver");
    }
}

/**
 * SQLite connection (local development, file-based, no server).
 *
 * @param array{path:string} $dbConfig
 */
function db_connect_sqlite(array $dbConfig): PDO
{
    $path = $dbConfig['path'] ?? __DIR__ . '/../../storage/gallery.sqlite';

    $pdo = new PDO("sqlite:$path", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // SQLite-specific pragmas for better performance and safety
    $pdo->exec('PRAGMA journal_mode = WAL');           // Write-ahead logging for concurrency
    $pdo->exec('PRAGMA foreign_keys = ON');            // Enable foreign key constraints
    $pdo->exec('PRAGMA synchronous = NORMAL');         // Balance safety and speed
    $pdo->exec('PRAGMA cache_size = -64000');          // 64MB cache
    $pdo->exec('PRAGMA temp_store = MEMORY');          // Temp tables in memory

    return $pdo;
}

/**
 * MySQL connection (production, server-based).
 *
 * @param array{host:string,port:int,name:string,user:string,pass:string,charset:string} $dbConfig
 */
function db_connect_mysql(array $dbConfig): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name'],
        $dbConfig['charset']
    );

    return new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

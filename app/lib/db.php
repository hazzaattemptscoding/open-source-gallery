<?php
/**
 * Single PDO connection factory. Every part of the app that touches MySQL
 * goes through this so the connection options (exceptions on error, real
 * prepared statements, utf8mb4) are set in exactly one place.
 */

declare(strict_types=1);

/**
 * Open a PDO connection from the 'db' section of config.php.
 *
 * Emulated prepares are turned off: PDO's emulation rewrites placeholders
 * into a literal query string itself, which reopens the SQL-injection risk
 * prepared statements exist to close. Real server-side prepares avoid that.
 *
 * @param array{host:string,port:int,name:string,user:string,pass:string,charset:string} $dbConfig
 */
function db_connect(array $dbConfig): PDO
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

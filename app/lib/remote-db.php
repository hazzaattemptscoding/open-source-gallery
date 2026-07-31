<?php
/**
 * Remote database connection handler for admin mode.
 * Establishes PDO connection to production database from remote admin server.
 */

declare(strict_types=1);

function create_remote_db_connection(array $config): PDO {
    if (empty($config['admin_remote'])) {
        throw new RuntimeException('admin_remote config not found');
    }

    $remote = $config['admin_remote'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $remote['db_host'],
        $remote['db_port'] ?? 3306,
        $remote['db_name'],
        'utf8mb4'
    );

    try {
        $pdo = new PDO(
            $dsn,
            $remote['db_user'],
            $remote['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Verify connection by running a simple query
        $pdo->query('SELECT 1');

        return $pdo;
    } catch (PDOException $e) {
        error_log('remote-db: create_remote_db_connection() failed: ' . $e->getMessage());
        throw new RuntimeException(
            'Failed to connect to remote database: ' . $e->getMessage()
        );
    }
}

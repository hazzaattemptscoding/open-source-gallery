<?php
declare(strict_types=1);

/**
 * Backup job: database dump + storage/hires/ archive.
 * Runs via cron job system, stores to storage/backups/.
 * Manual restore process documented in BACKUP_RESTORE.md.
 */

/**
 * Queue a backup job to run as soon as possible via cron.
 * Can be called from admin actions or scheduled external triggers.
 */
function queue_backup_job(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO jobs (type, payload, status, run_after)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ');
    $stmt->execute(['backup', json_encode([]), 'pending']);
}

/**
 * Process a backup job: dump database, create archive of hires storage.
 * Returns true on success, false on failure (logged by caller).
 */
function process_backup_job(PDO $pdo, array $payload): bool {
    $backupDir = __DIR__ . '/../../storage/backups';
    $timestamp = date('Y-m-d-H-i-s', time());
    $sessionId = uniqid('backup_', true);

    // Create backups directory if it doesn't exist
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0700, true);
    }

    if (!is_dir($backupDir)) {
        error_log("backup: Cannot create backup directory at {$backupDir}");
        return false;
    }

    // Backup database
    try {
        $dbBackupPath = "{$backupDir}/database-{$timestamp}.sql";
        $success = backup_database($pdo, $dbBackupPath);
        if (!$success) {
            error_log("backup: Database backup failed");
            return false;
        }
    } catch (Throwable $e) {
        error_log("backup: Database backup error: " . get_class($e) . ': ' . $e->getMessage());
        return false;
    }

    // Backup hires storage
    try {
        $hiresBackupPath = "{$backupDir}/storage-hires-{$timestamp}.tar.gz";
        $hiresDir = __DIR__ . '/../../storage/hires';

        if (is_dir($hiresDir)) {
            $cmd = sprintf(
                'tar -czf %s -C %s hires 2>/dev/null',
                escapeshellarg($hiresBackupPath),
                escapeshellarg(__DIR__ . '/../../storage')
            );
            $exitCode = 0;
            system($cmd, $exitCode);

            if ($exitCode !== 0) {
                error_log("backup: Failed to create hires archive, exit code {$exitCode}");
                @unlink($dbBackupPath);
                return false;
            }
        }
    } catch (Throwable $e) {
        error_log("backup: Storage backup error: " . get_class($e) . ': ' . $e->getMessage());
        @unlink($dbBackupPath);
        return false;
    }

    // Clean up old backups (keep last 7 days)
    cleanup_old_backups($backupDir, 7 * 24 * 60 * 60);

    return true;
}

/**
 * Dump the database to a SQL file using PDO's available methods.
 * For MySQL: uses mysqldump if available, falls back to PHP extraction.
 * For SQLite: uses .dump command.
 */
function backup_database(PDO $pdo, string $outputPath): bool {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        return backup_database_mysql($pdo, $outputPath);
    } elseif ($driver === 'sqlite') {
        return backup_database_sqlite($pdo, $outputPath);
    }

    error_log("backup: Unsupported database driver: {$driver}");
    return false;
}

/**
 * MySQL database backup via mysqldump or SQL extraction.
 */
function backup_database_mysql(PDO $pdo, string $outputPath): bool {
    // Try mysqldump first (most reliable)
    $dsn = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    // Extract DB name from DSN or environment
    $dbName = getenv('DB_NAME') ?: 'gallery';

    if (function_exists('exec')) {
        $cmd = sprintf(
            'mysqldump --single-transaction --quick --no-create-db %s > %s 2>/dev/null',
            escapeshellarg($dbName),
            escapeshellarg($outputPath)
        );
        $output = null;
        $exitCode = 0;
        @exec($cmd, $output, $exitCode);

        if ($exitCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
            return true;
        }
    }

    // Fallback: PHP-based extraction (slower but works everywhere)
    return backup_database_php($pdo, $outputPath);
}

/**
 * SQLite database backup: copy the database file.
 */
function backup_database_sqlite(PDO $pdo, string $outputPath): bool {
    $dbPath = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    // For SQLite, the connection string contains the path
    // Try to extract it from the last used database or default path
    if (!$dbPath || !file_exists($dbPath)) {
        // Default location for sqlite
        $dbPath = __DIR__ . '/../../storage/gallery.db';
    }

    if (!file_exists($dbPath)) {
        error_log("backup: SQLite database not found at {$dbPath}");
        return false;
    }

    // Use SQL dump for consistency with MySQL
    try {
        $handle = fopen($outputPath, 'w');
        if (!$handle) {
            return false;
        }

        // Get all tables
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Write basic header
        fwrite($handle, "-- SQLite database dump\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");

        foreach ($tables as $table) {
            // Get schema
            $schemaStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE name = '" . $table . "' AND type='table'");
            $schema = $schemaStmt->fetchColumn();
            if ($schema) {
                fwrite($handle, "{$schema};\n\n");
            }

            // Get data
            $dataStmt = $pdo->query("SELECT * FROM \"{$table}\"");
            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_keys($row);
                $vals = array_map(function($v) { return $v === null ? 'NULL' : "'" . addslashes($v) . "'"; }, array_values($row));
                fwrite($handle, "INSERT INTO \"{$table}\" (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n");
            }
            fwrite($handle, "\n");
        }

        fclose($handle);
        return true;
    } catch (Throwable $e) {
        error_log("backup: SQLite dump error: " . $e->getMessage());
        return false;
    }
}

/**
 * PHP-based database dump (fallback for MySQL without mysqldump).
 */
function backup_database_php(PDO $pdo, string $outputPath): bool {
    try {
        $handle = fopen($outputPath, 'w');
        if (!$handle) {
            return false;
        }

        // Write header
        fwrite($handle, "-- Database backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");

        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Get create table statement
            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            if ($createRow) {
                $createSQL = $createRow['Create Table'] ?? '';
                fwrite($handle, "{$createSQL};\n\n");
            }

            // Get data
            $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_keys($row);
                $vals = array_map(function($v) { return $v === null ? 'NULL' : "'" . addslashes($v) . "'"; }, array_values($row));
                fwrite($handle, "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n");
            }
            fwrite($handle, "\n");
        }

        fclose($handle);
        return true;
    } catch (Throwable $e) {
        error_log("backup: PHP dump error: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove backups older than the retention period.
 */
function cleanup_old_backups(string $backupDir, int $retentionSeconds): void {
    if (!is_dir($backupDir)) {
        return;
    }

    $now = time();
    $files = glob("{$backupDir}/*");

    foreach ($files as $file) {
        if (is_file($file)) {
            $age = $now - filemtime($file);
            if ($age > $retentionSeconds) {
                @unlink($file);
            }
        }
    }
}

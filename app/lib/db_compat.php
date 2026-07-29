<?php
declare(strict_types=1);

/**
 * Database compatibility layer for MySQL/SQLite differences.
 * Provides driver-aware helpers for MySQL-specific SQL constructs.
 */

function get_db_driver(PDO $pdo): string {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function is_sqlite(PDO $pdo): bool {
    return get_db_driver($pdo) === 'sqlite';
}

function is_mysql(PDO $pdo): bool {
    return in_array(get_db_driver($pdo), ['mysql', 'pdo_mysql']);
}

/**
 * Advisory lock: MySQL GET_LOCK / SQLite PRAGMA locking fallback.
 * Returns true if lock acquired, false otherwise.
 */
function db_acquire_lock(PDO $pdo, string $lockName, int $timeoutSeconds = 10): bool {
    if (is_mysql($pdo)) {
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([$lockName, $timeoutSeconds]);
        return (bool)$stmt->fetchColumn();
    }

    if (is_sqlite($pdo)) {
        try {
            $pdo->exec('BEGIN IMMEDIATE');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    return false;
}

function db_release_lock(PDO $pdo, string $lockName): void {
    if (is_mysql($pdo)) {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')
            ->execute([$lockName]);
    } elseif (is_sqlite($pdo)) {
        try {
            $pdo->exec('COMMIT');
        } catch (PDOException $e) {
            $pdo->exec('ROLLBACK');
        }
    }
}

/**
 * Current timestamp: CURRENT_TIMESTAMP (works on both) but allow overrides.
 */
function db_now_sql(): string {
    return 'CURRENT_TIMESTAMP';
}

/**
 * Date subtraction: DATE_SUB(date, INTERVAL X unit) on MySQL, date('-X days') on SQLite.
 */
function db_date_sub_sql(string $dateColumn, int $amount, string $unit = 'day'): string {
    if (is_mysql($_GLOBALS['pdo'] ?? null)) {
        $unitMap = ['day' => 'DAY', 'hour' => 'HOUR', 'week' => 'WEEK'];
        $sqlUnit = $unitMap[$unit] ?? 'DAY';
        return "DATE_SUB({$dateColumn}, INTERVAL {$amount} {$sqlUnit})";
    }

    $unitMap = ['day' => 'days', 'hour' => 'hours', 'week' => 'days'];
    $sqlUnit = $unitMap[$unit] ?? 'days';
    if ($unit === 'week') {
        $amount *= 7;
    }
    return "date({$dateColumn}, '-{$amount} {$sqlUnit}')";
}

/**
 * Date addition: DATE_ADD(date, INTERVAL X unit) on MySQL, date('+X days') on SQLite.
 */
function db_date_add_sql(string $dateColumn, int $amount, string $unit = 'day'): string {
    if (is_mysql($_GLOBALS['pdo'] ?? null)) {
        $unitMap = ['day' => 'DAY', 'hour' => 'HOUR', 'week' => 'WEEK'];
        $sqlUnit = $unitMap[$unit] ?? 'DAY';
        return "DATE_ADD({$dateColumn}, INTERVAL {$amount} {$sqlUnit})";
    }

    $unitMap = ['day' => 'days', 'hour' => 'hours', 'week' => 'days'];
    $sqlUnit = $unitMap[$unit] ?? 'days';
    if ($unit === 'week') {
        $amount *= 7;
    }
    return "date({$dateColumn}, '+{$amount} {$sqlUnit}')";
}

/**
 * Date formatting: DATE_FORMAT(date, format) on MySQL, strftime(format, date) on SQLite.
 * Only supports a few common formats to keep it portable.
 */
function db_date_format_sql(string $dateColumn, string $format): string {
    if (is_mysql($_GLOBALS['pdo'] ?? null)) {
        return "DATE_FORMAT({$dateColumn}, '{$format}')";
    }

    $formatMap = [
        '%Y-%m-%d' => '%Y-%m-%d',
        '%Y-%m' => '%Y-%m',
        '%m/%d/%Y' => '%m/%d/%Y',
    ];

    $sqliteFormat = $formatMap[$format] ?? $format;
    return "strftime('{$sqliteFormat}', {$dateColumn})";
}

/**
 * Upsert: ON DUPLICATE KEY UPDATE (MySQL) or INSERT OR REPLACE (SQLite).
 * This is a helper to show the pattern; actual usage differs by context.
 * MySQL: INSERT ... ON DUPLICATE KEY UPDATE col = VALUES(col)
 * SQLite: INSERT OR REPLACE INTO ... with all columns
 */
function db_supports_on_duplicate_key(PDO $pdo): bool {
    return is_mysql($pdo);
}

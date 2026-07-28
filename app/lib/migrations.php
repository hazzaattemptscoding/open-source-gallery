<?php
/**
 * Applies numbered .sql files from migrations/ in order, recording each one
 * in the `migrations` table so it never runs twice. The very first migration
 * (001_initial_schema.sql, which creates the migrations table itself) is a
 * special case: a fresh install imports it directly via phpMyAdmin or the
 * mysql CLI per INSTALL.md, since there's no `migrations` table to check
 * against until it's run. Everything after 001 goes through this runner.
 */

declare(strict_types=1);

/**
 * List migration filenames in migrations/ that haven't been applied yet,
 * in filename order (numeric prefix keeps that order stable).
 *
 * @return string[] filenames, e.g. ['002_add_thing.sql']
 */
function migrations_pending(PDO $pdo, string $migrationsDir): array
{
    $applied = [];
    foreach ($pdo->query('SELECT filename FROM migrations') as $row) {
        $applied[$row['filename']] = true;
    }

    $files = glob($migrationsDir . '/*.sql');
    sort($files, SORT_STRING);

    $pending = [];
    foreach ($files as $path) {
        $filename = basename($path);
        if (!isset($applied[$filename])) {
            $pending[] = $filename;
        }
    }

    return $pending;
}

/**
 * Apply one migration file and record it as applied. Statements are split
 * on `;` at end-of-line, which is all this project's migrations need (no
 * stored procedures/triggers with embedded semicolons).
 *
 * Deliberately NOT wrapped in a PHP transaction: MySQL/MariaDB DDL statements
 * (CREATE TABLE, ALTER TABLE) each cause an implicit commit, which silently
 * ends any transaction already open. A beginTransaction()/commit() wrapped
 * around DDL looks safe but commit()/rollBack() end up operating on no active
 * transaction and throw — worse, it would give the false impression that a
 * half-applied schema change had been rolled back when it hadn't. If a
 * statement fails partway through a migration, the schema is left exactly
 * where MySQL left it; that has to be fixed by hand before rerunning, same
 * as any other MySQL migration tool.
 *
 * @throws PDOException if any statement fails.
 */
function migrations_apply(PDO $pdo, string $migrationsDir, string $filename): void
{
    $sql = file_get_contents($migrationsDir . '/' . $filename);
    if ($sql === false) {
        throw new RuntimeException("Could not read migration file: {$filename}");
    }

    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    foreach ($statements as $statement) {
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        $pdo->exec($statement);
    }

    $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
    $stmt->execute(['filename' => $filename]);
}

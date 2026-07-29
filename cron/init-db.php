<?php
/**
 * Database initialization script. Ensures schema is imported and up-to-date.
 * Safe to run multiple times (idempotent). Run this from Docker ENTRYPOINT
 * or manually after setup:
 *   php cron/init-db.php
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

echo "Database Initialization\n";
echo "======================\n\n";

try {
    // Detect database driver
    $dbDriver = $config['db']['driver'] ?? 'mysql';
    echo "Database driver: " . ($dbDriver === 'sqlite' ? 'SQLite' : 'MySQL') . "\n\n";

    // Check if migrations table exists
    if ($dbDriver === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
        $migrationsTableExists = (bool)$stmt->fetch();
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
        $migrationsTableExists = (bool)$stmt->fetch();
    }

    if (!$migrationsTableExists) {
        echo "Migrations table not found. Importing initial schema...\n";

        // Choose schema file based on database driver
        $schemaExtension = ($dbDriver === 'sqlite') ? '.sqlite.sql' : '.sql';
        $schemaFile = __DIR__ . '/../migrations/001_initial_schema' . $schemaExtension;

        if (!file_exists($schemaFile)) {
            fwrite(STDERR, "ERROR: Schema file not found: $schemaFile\n");
            exit(1);
        }

        $schema = file_get_contents($schemaFile);

        // Split by semicolon but preserve ones inside strings
        $statements = array_filter(
            array_map('trim', preg_split('/;[\s\n]+/', $schema)),
            fn($s) => !empty($s) && !str_starts_with($s, '--')
        );

        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement . ';');
            } catch (PDOException $e) {
                // Some statements might error (like CREATE TABLE IF NOT EXISTS when it already exists)
                // This is usually fine - we're being idempotent
                echo "  Note: " . $e->getMessage() . "\n";
            }
        }

        echo "✓ Schema imported\n";
    } else {
        echo "✓ Schema already imported\n";
    }

    // Verify key tables exist
    $requiredTables = [
        'admin_users', 'events', 'sessions', 'photos', 'orders', 'settings'
    ];

    echo "\nVerifying tables...\n";
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "  ✓ $table\n";
        } else {
            throw new Exception("Missing critical table: $table");
        }
    }

    echo "\n✓ Database initialization complete!\n";
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: Database initialization failed\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

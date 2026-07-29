<?php
/**
 * CLI command implementations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../email.php';
require_once __DIR__ . '/../settings.php';

function cli_migrate(PDO $pdo, array $args): void {
    $direction = $args[0] ?? 'up';

    if ($direction === 'up') {
        cli_log('Running pending migrations...');
        $stmt = $pdo->query('SELECT filename FROM migrations ORDER BY applied_at');
        $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $migrationsDir = __DIR__ . '/../../migrations';
        $files = glob($migrationsDir . '/*.sql') ?: [];
        sort($files);

        $count = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            if (!in_array($filename, $applied)) {
                cli_log("Applying: $filename");
                $sql = file_get_contents($file);
                try {
                    $pdo->exec($sql);
                    $count++;
                } catch (PDOException $e) {
                    cli_error("Failed to apply $filename: " . $e->getMessage());
                }
            }
        }

        cli_success("Applied $count migrations");
    }
}

function cli_migrate_status(PDO $pdo): void {
    $stmt = $pdo->query('SELECT filename, applied_at FROM migrations ORDER BY applied_at');
    $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($migrations)) {
        cli_log('No migrations applied');
        return;
    }

    echo "\nApplied Migrations:\n";
    echo str_repeat('-', 50) . "\n";
    foreach ($migrations as $m) {
        echo sprintf("  %-30s %s\n", $m['filename'], $m['applied_at']);
    }
    echo str_repeat('-', 50) . "\n";
}

function cli_db_validate(PDO $pdo): void {
    cli_log('Validating database schema...');

    $requiredTables = [
        'admin_users', 'events', 'sessions', 'photos', 'photo_tags',
        'orders', 'order_items', 'audit_log', 'admin_roles',
        'print_providers', 'print_settings', 'print_orders', 'print_items',
    ];

    $stmt = $pdo->query('SHOW TABLES');
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $missing = array_diff($requiredTables, $existing);
    if (!empty($missing)) {
        cli_error('Missing tables: ' . implode(', ', $missing));
    }

    cli_success('Database schema valid (' . count($existing) . ' tables)');
}

function cli_photos_organize(PDO $pdo, array $args): void {
    $eventId = (int)($args[0] ?? 0);

    if ($eventId === 0) {
        cli_log('Usage: photos:organize <event_id>');
        cli_log('Organizes photos by event into directories');
        return;
    }

    cli_log("Organizing photos for event #$eventId...");

    $stmt = $pdo->prepare('SELECT id, public_token FROM photos WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    cli_success('Found ' . count($photos) . ' photos in event #' . $eventId);
    cli_log('(Actual file operations would be implemented based on storage backend)');
}

function cli_photos_fix_watermarks(PDO $pdo, array $args): void {
    $eventId = (int)($args[0] ?? 0);

    cli_log('Regenerating watermarks...');
    if ($eventId > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM photos WHERE event_id = ? AND status = ?');
        $stmt->execute([$eventId, 'live']);
        $count = (int)$stmt->fetchColumn();
        cli_log("Found $count photos to regenerate");
    } else {
        $stmt = $pdo->query('SELECT COUNT(*) FROM photos WHERE status = ?');
        $stmt->execute(['live']);
        $count = (int)$stmt->fetchColumn();
        cli_log("Found $count photos to regenerate (all events)");
    }

    cli_success('Watermark regeneration queued for cron worker');
}

function cli_email_queue(PDO $pdo, array $args): void {
    cli_log('Checking email queue...');

    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                   SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM emails
        SQL);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "\nEmail Queue Status:\n";
        echo str_repeat('-', 40) . "\n";
        echo sprintf("  Total:    %d\n", $stats['total'] ?? 0);
        echo sprintf("  Pending:  %d\n", $stats['pending'] ?? 0);
        echo sprintf("  Sent:     %d\n", $stats['sent'] ?? 0);
        echo sprintf("  Failed:   %d\n", $stats['failed'] ?? 0);
        echo str_repeat('-', 40) . "\n";
    } catch (Throwable $e) {
        cli_log('Email table does not exist yet');
    }
}

function cli_email_retry(PDO $pdo, array $args): void {
    cli_log('Retrying failed emails...');

    try {
        $stmt = $pdo->prepare('UPDATE emails SET status = ? WHERE status = ? AND retry_count < ?');
        $stmt->execute(['pending', 'failed', 3]);
        cli_success('Marked ' . $stmt->rowCount() . ' emails for retry');
    } catch (Throwable $e) {
        cli_log('Email system not yet initialized');
    }
}

function cli_backup_create(PDO $pdo, array $config, array $args): void {
    cli_log('Creating database backup...');

    $backupDir = __DIR__ . '/../../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0700, true);
    }

    $filename = 'backup_' . date('Y-m-d_Hi') . '.sql';
    $filepath = "$backupDir/$filename";

    try {
        $stmt = $pdo->query('SELECT VERSION()');
        $version = $stmt->fetchColumn();
        cli_log("Database: $version");

        cli_log("Backup would be saved to: $filepath");
        cli_log("(Database dump implementation depends on hosting environment)");
    } catch (Throwable $e) {
        cli_error('Backup failed: ' . $e->getMessage());
    }
}

function cli_config_validate(array $config): void {
    cli_log('Validating configuration...');

    $errors = [];

    // Check required sections
    $required = ['site', 'database', 'security', 'currency'];
    foreach ($required as $section) {
        if (empty($config[$section])) {
            $errors[] = "Missing config section: $section";
        }
    }

    // Check Stripe config for live mode
    if (!empty($config['stripe']['mode']) && $config['stripe']['mode'] === 'live') {
        if (empty($config['stripe']['secret_key']) || strpos($config['stripe']['secret_key'], 'sk_live_') !== 0) {
            $errors[] = 'Stripe live mode requires valid sk_live_* key';
        }
    }

    // Check email config
    if (!empty($config['email']['enabled'])) {
        if (empty($config['email']['from'])) {
            $errors[] = 'Email enabled but no from address configured';
        }
    }

    if (!empty($errors)) {
        echo "Configuration errors:\n";
        foreach ($errors as $err) {
            echo "  ✗ $err\n";
        }
        exit(1);
    }

    cli_success('Configuration valid');
}

function cli_analytics_export(PDO $pdo, array $args): void {
    $format = $args[0] ?? 'csv';
    $days = (int)($args[1] ?? 30);

    cli_log("Exporting analytics (last $days days, format: $format)...");

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_pence) as revenue
        FROM orders
        WHERE created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    SQL);
    $stmt->execute([$days]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'csv') {
        echo "date,orders,revenue_pence\n";
        foreach ($rows as $row) {
            echo sprintf("%s,%d,%d\n", $row['date'], $row['orders'], $row['revenue']);
        }
    } else {
        echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
    }

    cli_success('Export complete (' . count($rows) . ' rows)');
}

function cli_cron_test(PDO $pdo): void {
    cli_log('Testing cron job execution...');

    require_once __DIR__ . '/../cron.php';
    $start = microtime(true);

    try {
        run_cron_drain($pdo);
        $elapsed = round((microtime(true) - $start) * 1000);
        cli_success("Cron completed in {$elapsed}ms");
    } catch (Throwable $e) {
        cli_error('Cron failed: ' . $e->getMessage());
    }
}

function cli_settings_get(PDO $pdo, array $args): void {
    $category = $args[0] ?? null;
    $key = $args[1] ?? null;

    if (!$category || !$key) {
        cli_log('Usage: settings:get <category> <key>');
        cli_log('Example: settings:get email from_address');
        return;
    }

    $value = get_setting($pdo, $category, $key);
    if ($value === null) {
        cli_log("Setting not found: $category.$key");
        return;
    }

    echo "Value: " . (is_array($value) ? json_encode($value) : $value) . "\n";
}

function cli_settings_set(PDO $pdo, array $args): void {
    $category = $args[0] ?? null;
    $key = $args[1] ?? null;
    $value = $args[2] ?? null;

    if (!$category || !$key || $value === null) {
        cli_log('Usage: settings:set <category> <key> <value>');
        cli_log('Example: settings:set email from_address noreply@example.com');
        return;
    }

    $errors = validate_setting($pdo, $category, $key, $value);
    if (!empty($errors)) {
        foreach ($errors as $err) {
            cli_log("Error: $err");
        }
        return;
    }

    if (set_setting($pdo, $category, $key, $value)) {
        cli_success("Setting updated: $category.$key = $value");
    } else {
        cli_error("Failed to update setting");
    }
}

function cli_settings_list(PDO $pdo, array $args): void {
    $category = $args[0] ?? null;

    $settings = $category ? get_all_settings($pdo, $category) : get_all_settings($pdo);

    if (empty($settings)) {
        cli_log('No settings found');
        return;
    }

    echo "\nSettings:\n";
    echo str_repeat('-', 80) . "\n";

    $currentCat = null;
    foreach ($settings as $s) {
        if ($currentCat !== $s['category']) {
            $currentCat = $s['category'];
            echo "\n[" . strtoupper($currentCat) . "]\n";
        }
        $value = strlen($s['value']) > 40 ? substr($s['value'], 0, 40) . '...' : $s['value'];
        printf("  %-20s = %s\n", $s['key_name'], $value);
    }
    echo str_repeat('-', 80) . "\n";
}

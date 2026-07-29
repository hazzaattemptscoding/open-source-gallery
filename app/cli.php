<?php
/**
 * Admin CLI tool for operations, migrations, bulk tasks.
 * Usage: php app/cli.php <command> [args]
 * Run from SSH: ssh user@host "cd /path/to/gallery && php app/cli.php help"
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib/cli/commands.php';

if (php_sapi_name() !== 'cli') {
    echo "CLI tool requires command line mode\n";
    exit(1);
}

$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

try {
    match ($command) {
        'help' => cli_help(),
        'migrate' => cli_migrate($pdo, $args),
        'migrate:status' => cli_migrate_status($pdo),
        'db:validate' => cli_db_validate($pdo),
        'photos:organize' => cli_photos_organize($pdo, $args),
        'photos:fix-watermarks' => cli_photos_fix_watermarks($pdo, $args),
        'email:queue' => cli_email_queue($pdo, $args),
        'email:retry' => cli_email_retry($pdo, $args),
        'backup:create' => cli_backup_create($pdo, $config, $args),
        'config:validate' => cli_config_validate($config),
        'analytics:export' => cli_analytics_export($pdo, $args),
        'cron:test' => cli_cron_test($pdo),
        'settings:get' => cli_settings_get($pdo, $args),
        'settings:set' => cli_settings_set($pdo, $args),
        'settings:list' => cli_settings_list($pdo, $args),
        default => cli_error("Unknown command: $command"),
    };
} catch (Throwable $e) {
    cli_error("Error: " . $e->getMessage());
}

function cli_help(): void {
    echo <<<'HELP'
Open Source Gallery — Admin CLI Tool

Usage: php app/cli.php <command> [args]

Database:
  migrate               Apply pending migrations
  migrate:status        Show migration status
  db:validate           Validate database schema

Photos:
  photos:organize       Rename/organize photos by event
  photos:fix-watermarks Regenerate watermarks for photos

Email:
  email:queue           Show pending email queue
  email:retry           Retry failed emails

Settings:
  settings:list         List all settings (or filtered by category)
  settings:get          Get a specific setting value
  settings:set          Set a setting value

Backup:
  backup:create         Create database backup

Configuration:
  config:validate       Validate config.php settings

Analytics:
  analytics:export      Export analytics to CSV

Maintenance:
  cron:test             Test cron job execution

Examples:
  php app/cli.php settings:list email
  php app/cli.php settings:get email from_address
  php app/cli.php settings:set email from_address noreply@example.com

Options:
  help                  Show this message

HELP;
}

function cli_log(string $msg): void {
    echo "[" . date('H:i:s') . "] $msg\n";
}

function cli_error(string $msg): void {
    fwrite(STDERR, "ERROR: $msg\n");
    exit(1);
}

function cli_success(string $msg): void {
    echo "✓ $msg\n";
}

<?php
/**
 * Setup health checks. Runs before every request to catch missing config/db/folders.
 * Shows helpful error messages if something's wrong.
 */

declare(strict_types=1);

function setup_check_database(PDO $pdo): array
{
    $issues = [];

    try {
        $result = $pdo->query('SELECT 1');
        if (!$result) {
            $issues[] = 'Database connection failed: No result from test query';
        }
    } catch (Exception $e) {
        $issues[] = 'Database error: ' . $e->getMessage();
    }

    try {
        $tables = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchColumn();
        if ($tables < 10) {
            $issues[] = 'Database schema incomplete: Only ' . $tables . ' tables found (expected 20+). Run migrations: /admin/migrations';
        }
    } catch (Exception $e) {
        $issues[] = 'Could not check tables: ' . $e->getMessage();
    }

    return $issues;
}

function setup_check_folders(): array
{
    $issues = [];
    $folders = [
        __DIR__ . '/../../storage/hires' => 'storage/hires (original photos)',
        __DIR__ . '/../../storage/zips' => 'storage/zips (download bundles)',
        __DIR__ . '/../../storage/tmp' => 'storage/tmp (upload chunks)',
        __DIR__ . '/../../public/media/d' => 'public/media/d (derivatives)',
    ];

    foreach ($folders as $path => $label) {
        if (!is_dir($path)) {
            $issues[] = "Missing folder: $label";
        } elseif (!is_writable($path)) {
            $issues[] = "Not writable: $label (run: chmod 755 $path)";
        }
    }

    return $issues;
}

function setup_check_admin(PDO $pdo): bool
{
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        return $count > 0;
    } catch (Exception $e) {
        return false;
    }
}

function setup_show_error($issues): void
{
    if (empty($issues)) {
        return;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Setup Issues</title><style>';
    echo 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #1a1a1a; color: #fff; margin: 0; padding: 2rem; }';
    echo '.container { max-width: 800px; margin: 0 auto; }';
    echo 'h1 { color: #ff6b6b; }';
    echo '.issue { background: rgba(255,107,107,0.1); border: 1px solid #ff6b6b; padding: 1rem; margin: 1rem 0; border-radius: 4px; }';
    echo '.fix { color: #51cf66; font-family: monospace; margin-top: 0.5rem; }';
    echo 'a { color: #74c0fc; text-decoration: none; }';
    echo 'a:hover { text-decoration: underline; }';
    echo '</style></head><body>';
    echo '<div class="container">';
    echo '<h1>⚠ Setup Issues</h1>';
    echo '<p>The site found some problems. Fix them below:</p>';

    foreach ($issues as $issue) {
        echo '<div class="issue">' . htmlspecialchars($issue) . '</div>';
    }

    echo '<p><a href="/admin/migrations">→ Run migrations</a> to set up the database.</p>';
    echo '<p><a href="/admin/setup">→ Create admin account</a></p>';
    echo '</div></body></html>';
    exit;
}

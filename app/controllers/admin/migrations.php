<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/migrations.php';

/**
 * Admin-only migrations runner (docs/architecture.md section 2: "numbered
 * .sql files + tiny runner (admin-only page)"). Exists because IONOS-style
 * shared hosting may give no CLI/SSH access at all, so applying a schema
 * change can't assume `php migrate.php` is even possible — a self-hoster
 * uploading files over FTP still needs a way to run migrations.
 */
function admin_migrations_controller(PDO $pdo, array $config): void {
    require_admin();
    $adminId = current_admin_id();
    $ip = client_ip();
    $csrfToken = csrf_token();
    $siteName = $config['site']['name'] ?? 'Gallery';
    $migrationsDir = __DIR__ . '/../../../migrations';

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo 'CSRF verification failed.';
            return;
        }
        $applied = [];
        $error = '';

        foreach (migrations_pending($pdo, $migrationsDir) as $filename) {
            try {
                migrations_apply($pdo, $migrationsDir, $filename);
                $applied[] = $filename;
                audit_log($pdo, 'admin', 'migration_applied', 'migration', null, ['filename' => $filename], $ip);
            } catch (PDOException $e) {
                $error = "Failed applying {$filename}: {$e->getMessage()}";
                break;
            }
        }

        $pending = migrations_pending($pdo, $migrationsDir);
        render(__DIR__ . '/../../views/admin/migrations.php', compact('siteName', 'csrfToken', 'pending', 'applied', 'error'));
        return;
    }

    $pending = migrations_pending($pdo, $migrationsDir);
    $applied = [];
    $error = '';
    render(__DIR__ . '/../../views/admin/migrations.php', compact('siteName', 'csrfToken', 'pending', 'applied', 'error'));
}

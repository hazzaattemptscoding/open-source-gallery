<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

/** POST-only and CSRF-checked: logout is a mutating admin request per docs/architecture.md section 6, not a plain link. */
function admin_logout_controller(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
        header('Location: /admin');
        exit;
    }

    admin_logout($pdo);
    header('Location: /admin/login');
    exit;
}

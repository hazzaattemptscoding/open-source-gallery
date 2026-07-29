<?php
/**
 * Placeholder landing page after login. Event/photo CRUD (build stage 2
 * step 3) replaces this with the real admin home.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/setup.php';

function admin_dashboard_controller(PDO $pdo, array $config): void
{
    require_admin();

    $stmt = $pdo->prepare('SELECT email, totp_enabled FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => current_admin_id()]);
    $admin = $stmt->fetch();

    $setupComplete = is_setup_complete($pdo);
    $setupChecklist = !$setupComplete ? get_setup_checklist($pdo) : [];

    render(__DIR__ . '/../../views/admin/dashboard.php', [
        'adminEmail' => $admin['email'],
        'totpEnabled' => (bool) $admin['totp_enabled'],
        'siteName' => $config['site']['name'],
        'csrfToken' => csrf_token(),
        'setupComplete' => $setupComplete,
        'setupChecklist' => $setupChecklist,
    ]);
}

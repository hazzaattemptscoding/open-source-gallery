<?php
/**
 * Admin management: create, edit, delete admins with roles.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/csrf.php';

function admin_admins_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_manage_admins($pdo)) {
        http_response_code(403);
        echo '403 Forbidden: Admin management access required';
        exit;
    }

    $csrfToken = csrf_token();
    $action = $_GET['action'] ?? 'list';
    $errors = [];

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo 'CSRF verification failed.';
            return;
        }
        $action = $_POST['action'] ?? 'list';

        if ($action === 'create') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $roleId = (int)($_POST['role_id'] ?? 1);

            // Validate
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters';
            }

            // Check email not already taken
            $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'Email already in use';
            }

            if (empty($errors)) {
                if (create_admin($pdo, $email, $password, $roleId)) {
                    audit_log($pdo, 'admin', 'create_admin', 'admin_user', null, ['email' => $email, 'role_id' => $roleId], client_ip());
                    header('Location: /admin/admins?action=list&success=1');
                    exit;
                } else {
                    $errors[] = 'Failed to create admin';
                }
            }
        } elseif ($action === 'update_role') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            $roleId = (int)($_POST['role_id'] ?? 1);

            // Prevent self-demotion
            if ($adminId == $_SESSION['admin_id']) {
                $errors[] = 'You cannot change your own role';
            } else {
                if (update_admin_role($pdo, $adminId, $roleId)) {
                    audit_log($pdo, 'admin', 'update_admin_role', 'admin_user', $adminId, ['role_id' => $roleId], client_ip());
                    header('Location: /admin/admins?action=list&success=1');
                    exit;
                } else {
                    $errors[] = 'Failed to update role';
                }
            }
        } elseif ($action === 'delete') {
            $adminId = (int)($_POST['admin_id'] ?? 0);

            // Prevent self-deletion
            if ($adminId == $_SESSION['admin_id']) {
                $errors[] = 'You cannot delete your own account';
            } else {
                if (delete_admin($pdo, $adminId)) {
                    audit_log($pdo, 'admin', 'delete_admin', 'admin_user', $adminId, [], client_ip());
                    header('Location: /admin/admins?action=list&success=1');
                    exit;
                } else {
                    $errors[] = 'Failed to delete admin (may be the last admin)';
                }
            }
        }
    }

    // Get data for view
    $admins = get_all_admins($pdo);
    $roles = get_all_roles($pdo);
    $success = $_GET['success'] ?? false;

    render(__DIR__ . '/../../views/admin/admins.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'csrfToken' => csrf_token(), // re-fetch: csrf_verify() above invalidates the token on success, and a POST that succeeded already redirected away, so this always reflects the current session's live token for the page about to render
        'action' => $action,
        'admins' => $admins,
        'roles' => $roles,
        'errors' => $errors,
        'success' => $success,
        'currentAdminId' => $_SESSION['admin_id'],
    ]);
}

<?php
/**
 * Admin: Email notification management.
 * View queue, resend, customize templates.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/email.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/csrf.php';

function admin_emails_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_view_settings($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $action = $_GET['action'] ?? 'queue';
    $errors = [];
    $success = $_GET['success'] ?? false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please try again.';
        } else {
            $action = $_POST['action'] ?? 'queue';

            if ($action === 'update_template') {
                $templateId = (int)($_POST['template_id'] ?? 0);
                if (update_email_template($pdo, $templateId, [
                    'subject_template' => $_POST['subject_template'] ?? '',
                    'body_html_template' => $_POST['body_html_template'] ?? '',
                    'body_text_template' => $_POST['body_text_template'] ?? '',
                    'enabled' => isset($_POST['enabled']) ? 1 : 0,
                ])) {
                    audit_log($pdo, 'admin', 'update_email_template', 'email_template', $templateId, [], client_ip());
                    // saved={id} so the view reopens the template just edited:
                    // templates are collapsed by default, and dropping the admin
                    // back onto a closed list gives no sign the save worked.
                    header('Location: /admin/emails?action=templates&success=1&saved=' . $templateId);
                    exit;
                }
                $errors[] = 'Could not save that template. Check the server error log for details.';
            }
        }
    }

    $stats = get_email_stats($pdo);
    $queue = get_email_queue($pdo, 100);
    $templates = get_email_templates($pdo);

    render(__DIR__ . '/../../views/admin/emails.php', [
        'pageTitle' => 'Email Management',
        'currentPage' => 'emails',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'action' => $action,
        'stats' => $stats,
        'queue' => $queue,
        'templates' => $templates,
        'errors' => $errors,
        'success' => $success,
        'csrfToken' => csrf_token(),
    ]);
}

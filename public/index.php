<?php
/**
 * Single front controller. Every request that isn't a static asset comes
 * through here (see public/.htaccess), which loads config/DB once, starts
 * the session with secure cookie flags, sends the security headers required
 * by docs/architecture.md section 6, then dispatches on the URL path.
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/lib/session.php';
require __DIR__ . '/../app/lib/view.php';

session_start_secure();

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; form-action 'self' https://checkout.stripe.com; frame-ancestors 'none'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
if ($path === '') {
    $path = '/';
}

switch ($path) {
    case '/admin/setup':
        require __DIR__ . '/../app/controllers/admin/setup.php';
        admin_setup_controller($pdo, $config);
        break;

    case '/admin/login':
        require __DIR__ . '/../app/controllers/admin/login.php';
        admin_login_controller($pdo, $config);
        break;

    case '/admin/logout':
        require __DIR__ . '/../app/controllers/admin/logout.php';
        admin_logout_controller($pdo);
        break;

    case '/admin/totp/enroll':
        require __DIR__ . '/../app/controllers/admin/totp_enroll.php';
        admin_totp_enroll_controller($pdo, $config);
        break;

    case '/admin':
        require __DIR__ . '/../app/controllers/admin/dashboard.php';
        admin_dashboard_controller($pdo, $config);
        break;

    case '/admin/upload':
        require __DIR__ . '/../app/controllers/admin/upload_page.php';
        admin_upload_page_controller($pdo, $config);
        break;

    default:
        if (strpos($path, '/admin/events') === 0) {
            require __DIR__ . '/../app/controllers/admin/events.php';
            admin_events_controller($pdo, $config);
        } elseif (strpos($path, '/admin/sessions') === 0) {
            require __DIR__ . '/../app/controllers/admin/sessions.php';
            admin_sessions_controller($pdo, $config);
        } elseif (strpos($path, '/admin/photos') === 0) {
            require __DIR__ . '/../app/controllers/admin/photos.php';
            admin_photos_controller($pdo, $config);
        } elseif (strpos($path, '/admin/upload') === 0) {
            require __DIR__ . '/../app/controllers/admin/upload.php';
            admin_upload_controller($pdo, $config);
        } else {
            http_response_code(404);
            echo '404 Not Found';
        }
}

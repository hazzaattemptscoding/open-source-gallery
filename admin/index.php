<?php
/**
 * Admin entry point for both local and remote deployment modes.
 *
 * When admin_mode is 'local': can be deployed alongside public/index.php on the
 *   same server. Browsers navigate to /admin/* paths directly.
 *
 * When admin_mode is 'remote': deployed to a separate server (home machine,
 *   local dev environment, etc.) and this becomes the entry point instead.
 *   Connects to production database and files via remote MySQL and SFTP.
 *   Users navigate to this admin server directly (different hostname/IP).
 *
 * Both modes reuse the same admin controllers and views from app/controllers/admin/.
 */

declare(strict_types=1);

require __DIR__ . '/../app/remote-bootstrap.php';
require __DIR__ . '/../app/lib/session.php';
require __DIR__ . '/../app/lib/view.php';

session_start_secure();

// Security headers (same as public site)
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; form-action 'self' https://checkout.stripe.com; frame-ancestors 'none'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
header('X-Permitted-Cross-Domain-Policies: none');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// Parse path relative to this admin entry point
// In remote mode, this might be http://admin.local/setup or http://admin.local/dashboard
// In local mode, this might be http://example.com/admin/setup or http://example.com/admin/dashboard
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');

// Strip common path prefixes to normalize the route
// Both /admin/setup and /setup (remote) should dispatch the same controller
if (strpos($path, '/admin') === 0) {
    $path = substr($path, 6);  // Remove /admin prefix
}
if ($path === '') {
    $path = '/';
}

// Admin-only routes
switch ($path) {
    case '/':
        require __DIR__ . '/../app/controllers/admin/dashboard.php';
        admin_dashboard_controller($pdo, $config);
        break;

    case '/setup':
        require __DIR__ . '/../app/controllers/admin/setup.php';
        admin_setup_controller($pdo, $config);
        break;

    case '/login':
        require __DIR__ . '/../app/controllers/admin/login.php';
        admin_login_controller($pdo, $config);
        break;

    case '/logout':
        require __DIR__ . '/../app/controllers/admin/logout.php';
        admin_logout_controller($pdo);
        break;

    case '/totp/enroll':
        require __DIR__ . '/../app/controllers/admin/totp_enroll.php';
        admin_totp_enroll_controller($pdo, $config);
        break;

    case '/upload':
        require __DIR__ . '/../app/controllers/admin/upload_page.php';
        admin_upload_page_controller($pdo, $config);
        break;

    case '/stats':
        require __DIR__ . '/../app/controllers/admin/stats.php';
        admin_stats_controller($pdo, $config);
        break;

    case '/orders':
        require __DIR__ . '/../app/controllers/admin/orders.php';
        admin_orders_controller($pdo, $config);
        break;

    case '/settings':
        require __DIR__ . '/../app/controllers/admin/settings.php';
        admin_settings_controller($pdo, $config);
        break;

    case '/export':
        require __DIR__ . '/../app/controllers/admin/export.php';
        admin_export_page_controller($pdo, $config);
        break;

    case '/audit-log':
        require __DIR__ . '/../app/controllers/admin/audit_log.php';
        admin_audit_log_controller($pdo, $config);
        break;

    case '/my-sessions':
        require __DIR__ . '/../app/controllers/admin/my_sessions.php';
        admin_my_sessions_controller($pdo, $config);
        break;

    case '/health':
        require __DIR__ . '/../app/controllers/admin/health.php';
        admin_health_check_controller($pdo, $config);
        break;

    case '/analytics':
        require __DIR__ . '/../app/controllers/admin/analytics.php';
        admin_analytics_controller($pdo, $config);
        break;

    case '/print_orders':
        require __DIR__ . '/../app/controllers/admin/print_orders.php';
        admin_print_orders_controller($pdo, $config);
        break;

    case '/admins':
        require __DIR__ . '/../app/controllers/admin/admins.php';
        admin_admins_controller($pdo, $config);
        break;

    case '/emails':
        require __DIR__ . '/../app/controllers/admin/emails.php';
        admin_emails_controller($pdo, $config);
        break;

    case '/bulk':
        require __DIR__ . '/../app/controllers/admin/bulk.php';
        admin_bulk_controller($pdo, $config);
        break;

    case '/watermarks':
        require __DIR__ . '/../app/controllers/admin/watermarks.php';
        admin_watermarks_controller($pdo, $config);
        break;

    case '/reporting':
        require __DIR__ . '/../app/controllers/admin/reporting.php';
        admin_reporting_controller($pdo, $config);
        break;

    case '/migrations':
        require __DIR__ . '/../app/controllers/admin/migrations.php';
        admin_migrations_controller($pdo, $config);
        break;

    // /export subpaths
    default:
        if (strpos($path, '/export/') === 0) {
            require __DIR__ . '/../app/controllers/admin/export.php';
            $type = substr($path, 8);  // /export/ is 8 chars
            match ($type) {
                'orders' => admin_export_orders_controller($pdo, $config),
                'photos' => admin_export_photos_controller($pdo, $config),
                'customers' => admin_export_customers_controller($pdo, $config),
                'events' => admin_export_events_controller($pdo, $config),
                default => (function() {
                    http_response_code(404);
                    echo '404 Not Found';
                })(),
            };
            exit;
        }

        // /admin/events routes
        if (strpos($path, '/events') === 0) {
            require __DIR__ . '/../app/controllers/admin/events.php';
            admin_events_controller($pdo, $config);
            exit;
        }

        // /admin/sessions routes
        if (strpos($path, '/sessions') === 0) {
            require __DIR__ . '/../app/controllers/admin/sessions.php';
            admin_sessions_controller($pdo, $config);
            exit;
        }

        // /admin/photos routes
        if (strpos($path, '/photos/tags') === 0) {
            require __DIR__ . '/../app/controllers/admin/tagging.php';
            admin_tagging_controller($pdo, $config);
            exit;
        }

        if (strpos($path, '/photos') === 0) {
            require __DIR__ . '/../app/controllers/admin/photos.php';
            admin_photos_controller($pdo, $config);
            exit;
        }

        // /admin/upload routes
        if (strpos($path, '/upload') === 0) {
            require __DIR__ . '/../app/controllers/admin/upload.php';
            admin_upload_controller($pdo, $config);
            exit;
        }

        // /admin/jobs routes
        if (strpos($path, '/jobs/run') === 0) {
            require __DIR__ . '/../app/controllers/admin/jobs.php';
            admin_jobs_run_controller($pdo, $config);
            exit;
        }

        http_response_code(404);
        echo '404 Not Found';
}

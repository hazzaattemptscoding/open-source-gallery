<?php
/**
 * Single front controller. Every request that isn't a static asset comes
 * through here (see public/.htaccess), which loads config/DB once, starts
 * the session with secure cookie flags, sends the security headers required
 * by docs/architecture.md section 6, then dispatches on the URL path.
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

// If bootstrap detected a setup error, show diagnostic page
if ($bootstrapError) {
    http_response_code(500);
    require __DIR__ . '/../app/views/errors/setup_diagnostic.php';
    exit;
}

require __DIR__ . '/../app/lib/session.php';
require __DIR__ . '/../app/lib/view.php';

session_start_secure();

// Security headers (docs/architecture.md section 6)
// style-src and font-src are stated explicitly rather than inherited from
// default-src. They are the same value, but naming them documents that inline
// styles and CDN fonts are deliberately refused: fonts are self-hosted under
// /assets/fonts, and every style belongs in a stylesheet. Do NOT add
// 'unsafe-inline' here to make a page render — that silently re-permits the
// whole class of inline styling this codebase is moving away from.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; form-action 'self' https://checkout.stripe.com; frame-ancestors 'none'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
header('X-Permitted-Cross-Domain-Policies: none');

// HSTS only in production (meaningless on http://localhost)
if (($config['dev_mode'] ?? 'production') !== 'local') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
if ($path === '') {
    $path = '/';
}

// When in remote admin mode, refuse all /admin routes on the public server
if (($config['admin_mode'] ?? 'local') === 'remote' && strpos($path, '/admin') === 0) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

// URL-invoked cron fallback (docs/architecture.md section 5) for hosts whose
// cron is URL-based rather than CLI. cron/run.php lives outside the docroot
// and has no direct HTTP path, so this is the only way to reach it over the web.
// Dynamic CSS with customization overrides
if ($path === '/api/styles.css') {
    require __DIR__ . '/../app/lib/customize.php';
    header('Content-Type: text/css; charset=utf-8');

    // Cache bust on customize.json changes
    $customizeFile = __DIR__ . '/../storage/customize.json';
    $mtime = file_exists($customizeFile) ? filemtime($customizeFile) : 0;
    $baseFile = __DIR__ . '/assets/css/podium-ink.css';
    $baseMtime = filemtime($baseFile);
    $etag = '"' . md5("$baseMtime-$mtime") . '"';

    // Set ETag and allow short cache, checking ETag on each request
    header("ETag: $etag");
    header('Cache-Control: public, max-age=60, must-revalidate');

    // If client has cached version, check if it's still valid
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        http_response_code(304);
        exit;
    }

    echo file_get_contents($baseFile);
    echo "\n/* Customization Overrides */\n\n";
    $settings = get_customize_settings();
    echo get_customize_css_overrides($settings);
    exit;
}

// Serve customization assets (logos, etc)
if (preg_match('#^/storage/customize/(.+)$#', $path, $assetMatch)) {
    $filename = $assetMatch[1];
    // Prevent directory traversal
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
        http_response_code(404);
        exit;
    }

    $filepath = __DIR__ . '/../storage/customize/' . $filename;
    if (!file_exists($filepath)) {
        http_response_code(404);
        exit;
    }

    // Determine MIME type
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// URL-invoked cron fallback (docs/architecture.md section 5) for hosts whose
// cron is URL-based rather than CLI. cron/run.php lives outside the docroot
// and has no direct HTTP path, so this is the only way to reach it over the web.
if (preg_match('#^/cron/(.+)$#', $path, $cronMatch)) {
    $cronSecret = $config['security']['cron_secret'] ?? '';
    if ($cronSecret === '' || !hash_equals($cronSecret, $cronMatch[1])) {
        http_response_code(404);
        echo '404 Not Found';
        exit;
    }
    require __DIR__ . '/../app/lib/cron.php';
    run_cron_drain($pdo);
    header('Content-Type: text/plain');
    echo 'OK';
    exit;
}

switch ($path) {
    case '/':
        require __DIR__ . '/../app/controllers/public/home.php';
        public_home_controller($pdo, $config);
        break;

    case '/search':
        require __DIR__ . '/../app/controllers/public/search.php';
        public_search_controller($pdo, $config);
        break;

    case '/api/search':
        require __DIR__ . '/../app/controllers/public/search.php';
        public_search_api_controller($pdo, $config);
        break;

    case '/cart':
        require __DIR__ . '/../app/controllers/public/cart.php';
        public_cart_page_controller($pdo, $config);
        break;

    case '/cart/add':
        require __DIR__ . '/../app/controllers/public/cart.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            public_cart_add_controller($pdo, $config);
        } else {
            http_response_code(405);
            echo 'Method Not Allowed';
        }
        break;

    case '/cart/remove':
        require __DIR__ . '/../app/controllers/public/cart.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            public_cart_remove_controller($pdo, $config);
        } else {
            http_response_code(405);
            echo 'Method Not Allowed';
        }
        break;

    case '/favorites':
        require __DIR__ . '/../app/controllers/public/favorites.php';
        public_favorites_page_controller($pdo, $config);
        break;

    case '/favorites/add':
        require __DIR__ . '/../app/controllers/public/favorites.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            public_favorites_add_controller($pdo, $config);
        } else {
            http_response_code(405);
            echo 'Method Not Allowed';
        }
        break;

    case '/favorites/remove':
        require __DIR__ . '/../app/controllers/public/favorites.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            public_favorites_remove_controller($pdo, $config);
        } else {
            http_response_code(405);
            echo 'Method Not Allowed';
        }
        break;

    case '/api/photos':
        require __DIR__ . '/../app/controllers/public/api_photos.php';
        public_api_photos_controller($pdo, $config);
        break;

    case '/api/photos/view':
        require __DIR__ . '/../app/controllers/public/api_photos.php';
        public_api_photo_view_controller($pdo, $config);
        break;

    case '/checkout':
        require __DIR__ . '/../app/controllers/public/checkout.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            public_checkout_controller($pdo, $config);
        } else {
            http_response_code(405);
            echo 'Method Not Allowed';
        }
        break;

    case '/webhook/stripe':
        require __DIR__ . '/../app/controllers/webhook/stripe.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            webhook_stripe_controller($pdo, $config);
        } else {
            http_response_code(405);
            echo 'Method Not Allowed';
        }
        break;

    case '/admin/setup':
        require __DIR__ . '/../app/controllers/admin/setup_wizard.php';
        admin_setup_wizard_controller($pdo, $config);
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

    case '/admin/stats':
        require __DIR__ . '/../app/controllers/admin/stats.php';
        admin_stats_controller($pdo, $config);
        break;

    case '/admin/orders':
        require __DIR__ . '/../app/controllers/admin/orders.php';
        admin_orders_controller($pdo, $config);
        break;

    case '/admin/customize':
        require __DIR__ . '/../app/controllers/admin/customize.php';
        admin_customize_controller($pdo, $config);
        break;

    case '/admin/export':
        require __DIR__ . '/../app/controllers/admin/export.php';
        admin_export_page_controller($pdo, $config);
        break;

    case '/admin/audit-log':
        require __DIR__ . '/../app/controllers/admin/audit_log.php';
        admin_audit_log_controller($pdo, $config);
        break;

    case '/admin/health':
        require __DIR__ . '/../app/controllers/admin/health.php';
        admin_health_check_controller($pdo, $config);
        break;

    case '/admin/analytics':
        require __DIR__ . '/../app/controllers/admin/analytics.php';
        admin_analytics_controller($pdo, $config);
        break;

    case '/admin/print_orders':
        require __DIR__ . '/../app/controllers/admin/print_orders.php';
        admin_print_orders_controller($pdo, $config);
        break;

    case '/admin/admins':
        require __DIR__ . '/../app/controllers/admin/admins.php';
        admin_admins_controller($pdo, $config);
        break;

    case '/admin/emails':
        require __DIR__ . '/../app/controllers/admin/emails.php';
        admin_emails_controller($pdo, $config);
        break;

    case '/admin/bulk':
        require __DIR__ . '/../app/controllers/admin/bulk.php';
        admin_bulk_controller($pdo, $config);
        break;

    case '/admin/watermarks':
        require __DIR__ . '/../app/controllers/admin/watermarks.php';
        admin_watermarks_controller($pdo, $config);
        break;

    case '/admin/reporting':
        require __DIR__ . '/../app/controllers/admin/reporting.php';
        admin_reporting_controller($pdo, $config);
        break;

    case '/admin/export/orders':
        require __DIR__ . '/../app/controllers/admin/export.php';
        admin_export_orders_controller($pdo, $config);
        exit;

    case '/admin/export/photos':
        require __DIR__ . '/../app/controllers/admin/export.php';
        admin_export_photos_controller($pdo, $config);
        exit;

    case '/admin/export/customers':
        require __DIR__ . '/../app/controllers/admin/export.php';
        admin_export_customers_controller($pdo, $config);
        exit;

    case '/admin/export/events':
        require __DIR__ . '/../app/controllers/admin/export.php';
        admin_export_events_controller($pdo, $config);
        exit;

    case '/admin/jobs/run':
        require __DIR__ . '/../app/controllers/admin/jobs.php';
        admin_jobs_run_controller($pdo, $config);
        break;

    case '/admin/migrations':
        require __DIR__ . '/../app/controllers/admin/migrations.php';
        admin_migrations_controller($pdo, $config);
        break;

    case '/admin/api/health':
        require __DIR__ . '/../app/controllers/admin/api_system.php';
        admin_api_health_controller($pdo, $config);
        break;

    case '/admin/api/jobs':
        require __DIR__ . '/../app/controllers/admin/api_system.php';
        admin_api_jobs_controller($pdo, $config);
        break;

    case '/admin/api/cache':
        require __DIR__ . '/../app/controllers/admin/api_system.php';
        admin_api_cache_controller($pdo, $config);
        break;

    case '/admin/api/perf':
        require __DIR__ . '/../app/controllers/admin/api_system.php';
        admin_api_perf_controller($pdo, $config);
        break;

    case '/admin/api/batch':
        require __DIR__ . '/../app/controllers/admin/api_system.php';
        admin_api_batch_controller($pdo, $config);
        break;

    case '/admin/api/fulfillment':
        require __DIR__ . '/../app/controllers/admin/api_fulfillment.php';
        admin_api_fulfillment_controller($pdo, $config);
        break;

    case '/sitemap.xml':
        require __DIR__ . '/../app/controllers/public/sitemap.php';
        public_sitemap_controller($pdo, $config);
        break;

    default:
        if (preg_match('#^/admin/settings(?:/([a-z_]+))?$#', $path, $m)) {
            // Handle /admin/settings or /admin/settings/{category}
            $_GET['category'] = $m[1] ?? 'site';
            require __DIR__ . '/../app/controllers/admin/settings.php';
            admin_settings_controller($pdo, $config);
        } elseif (strpos($path, '/admin/events') === 0) {
            require __DIR__ . '/../app/controllers/admin/events.php';
            admin_events_controller($pdo, $config);
        } elseif (strpos($path, '/admin/sessions') === 0) {
            require __DIR__ . '/../app/controllers/admin/sessions.php';
            admin_sessions_controller($pdo, $config);
        } elseif (strpos($path, '/admin/photos/tags') === 0) {
            require __DIR__ . '/../app/controllers/admin/tagging.php';
            admin_tagging_controller($pdo, $config);
        } elseif (strpos($path, '/admin/photos') === 0) {
            require __DIR__ . '/../app/controllers/admin/photos.php';
            admin_photos_controller($pdo, $config);
        } elseif (strpos($path, '/admin/upload') === 0) {
            require __DIR__ . '/../app/controllers/admin/upload.php';
            admin_upload_controller($pdo, $config);
        } elseif (preg_match('#^/download/([a-z0-9]+)$#', $path, $m)) {
            require __DIR__ . '/../app/controllers/public/download.php';
            public_download_controller($pdo, $config, $m[1]);
        } elseif (preg_match('#^/checkout/success/([a-z0-9]+)$#', $path, $m)) {
            require __DIR__ . '/../app/controllers/public/checkout.php';
            public_checkout_success_controller($pdo, $config, $m[1]);
        } elseif (preg_match('#^/order/([a-z0-9-]+)$#', $path, $m)) {
            require __DIR__ . '/../app/controllers/public/order_tracking.php';
            public_order_tracking_controller($pdo, $config, $m[1]);
        } elseif (preg_match('#^/e/([a-z0-9-]+)(?:/([a-z0-9-]+))?$#', $path, $m)) {
            require __DIR__ . '/../app/controllers/public/event.php';
            public_event_controller($pdo, $config, $m[1], $m[2] ?? null);
        } elseif (preg_match('#^/api/v1/photos/(\d+)$#', $path, $m)) {
            require __DIR__ . '/../app/controllers/api/photos.php';
            api_v1_photo_controller($pdo, (int)$m[1]);
        } elseif ($path === '/api/v1/photos') {
            require __DIR__ . '/../app/controllers/api/photos.php';
            api_v1_photos_controller($pdo);
        } elseif (preg_match('#^/media/d/([a-z0-9]+)-(400|800|1600)\.jpg$#', $path, $m)) {
            // Only reached when the derivative is absent: .htaccess serves the
            // real file directly when it exists. That gap is normal in
            // production between upload and the cron run that builds
            // derivatives. Without this branch the request fell through to the
            // HTML 404 page, so an <img> received a text/html body and rendered
            // as a broken image with no clue why.
            require __DIR__ . '/../app/lib/placeholder.php';
            $placeholderWidth = (int) $m[2];
            serve_placeholder_jpeg($placeholderWidth, (int) round($placeholderWidth * 2 / 3), $m[1]);
        } else {
            http_response_code(404);
            render(__DIR__ . '/../app/views/errors/404.php', []);
        }
}

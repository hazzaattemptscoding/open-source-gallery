<?php
/**
 * CLI cron entry point: `php cron/run.php` on a 5-minute schedule.
 * The URL-invoked fallback for hosts whose cron is URL-based
 * (GET /cron/{CRON_SECRET}) is handled by public/index.php instead —
 * cron/ lives outside the docroot, so this file has no direct HTTP path.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/lib/cron.php';

run_cron_drain($pdo);

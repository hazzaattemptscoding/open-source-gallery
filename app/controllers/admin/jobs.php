<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/cron.php';

/**
 * POST /admin/jobs/run — lets an admin drain the queue on demand from the
 * health page rather than waiting for the next cron tick, useful right
 * after a bulk upload or when cron isn't configured yet on a fresh install.
 *
 * Used to be its own ~180-line reimplementation of run_cron_drain()
 * (app/lib/cron.php) with the same claim logic and a shorter list of
 * handled job types: 'zip_build', 'cleanup', 'view_count' and 'backup' all
 * fell through to a bare `return true` in this file's old process_job(),
 * which deleted the job as if it had succeeded without ever running it. It
 * also carried its own parallel email-sending path (email_template_*()
 * functions in the now-removed app/lib/email_templates.php) reading
 * config['mail']['from_address'], a key that has never existed in
 * config.php's real shape (config['smtp'] is). Neither divergence had been
 * noticed because nothing in the admin UI ever calls this endpoint -- no
 * button, no fetch() -- so it has only ever been reachable by a direct POST.
 * A shorter budget than the full cron drain, since this is a synchronous
 * HTTP request an admin is waiting on in a browser tab.
 */
function admin_jobs_run_controller(PDO $pdo, array $config): void {
    require_admin();

    header('Content-Type: application/json');

    $startTime = microtime(true);
    $processed = run_cron_drain($pdo, 20.0);

    echo json_encode([
        'status' => 'ok',
        'processed' => $processed,
        'elapsed' => round(microtime(true) - $startTime, 2),
    ]);
}

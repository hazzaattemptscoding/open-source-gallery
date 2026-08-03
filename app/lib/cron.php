<?php
declare(strict_types=1);

require_once __DIR__ . '/derivatives.php';
require_once __DIR__ . '/db_compat.php';
require_once __DIR__ . '/backup.php';

$GLOBALS['pdo'] = $GLOBALS['pdo'] ?? null;

/**
 * Job drain with a caller-chosen time budget. Two callers: the CLI/URL cron
 * entry point wants the full 50s (docs/architecture.md section 5's
 * shared-hosting cron interval), while the browser-assisted drain on the
 * health page (app/controllers/admin/jobs.php) is a synchronous HTTP
 * request a human is waiting on and needs a much shorter one. Both used to
 * be separate ~60-line implementations of the same loop; the second one
 * silently deleted any job type it didn't explicitly handle (zip_build,
 * cleanup, view_count, backup all fell through to a bare `return true`) as
 * if it had succeeded, without ever running it. One implementation now
 * serves both.
 *
 * The claim step, not the whole drain, holds the write lock. It used to
 * wrap the entire budget in one db_acquire_lock()/db_release_lock() pair --
 * harmless on MySQL, where GET_LOCK() is a named advisory lock unrelated to
 * row locking, but on SQLite db_acquire_lock() opens a real BEGIN IMMEDIATE
 * transaction, and SQLite allows exactly one writer at a time. Holding that
 * open for the full 50s budget meant every customer write anywhere in the
 * app -- add to cart, checkout, a queued view-count increment -- blocked or
 * failed for up to 50 seconds on every single cron tick. The claim UPDATE
 * below is a few milliseconds; only that needs the lock, not whatever the
 * claimed job's handler goes on to do (deriving an image, sending mail,
 * building a zip).
 *
 * @return int number of jobs this call actually processed (succeeded or
 *             failed-and-recorded), for the browser drain's "processed: N"
 *             response. The CLI/URL caller ignores it.
 */
function run_cron_drain(PDO $pdo, float $budget = 50.0): int {
    $GLOBALS['pdo'] = $pdo;
    $processed = 0;
    $startTime = microtime(true);

    /*
     * Campaign scans run before the job queue, not as queued jobs.
     *
     * They are scans over current state ("which galleries went live recently",
     * "which checkouts were abandoned") rather than units of work someone
     * enqueued, so there is nothing to put in the queue in the first place, and
     * enqueueing them would need a scheduler this app does not have.
     *
     * Running first means they cannot be starved by a long backlog of
     * derivative jobs eating the whole budget. They are cheap: two indexed
     * queries when the campaigns are switched off, which is the default.
     *
     * Wrapped because nothing about marketing email is important enough to stop
     * derivative generation, which is what customers are actually waiting for.
     */
    try {
        require_once __DIR__ . '/campaigns.php';
        run_campaign_scans($pdo, $GLOBALS['config'] ?? []);
    } catch (Throwable $e) {
        error_log('campaign scans failed: ' . $e->getMessage());
    }

    /*
     * Finish what migration 016 deliberately left undone.
     *
     * That migration backfills entrants from existing entry lists with no share
     * token, because SQL has no source of randomness worth trusting for a
     * bearer token that is the only thing protecting a child's photo page. The
     * tokens are minted here instead, from random_bytes().
     *
     * Runs before the job queue for the same reason the campaign scans do: it
     * must not be starved by a long backlog. It is a single indexed query
     * returning nothing once the backfill is done, which is the normal case,
     * so the steady-state cost is one query per cron run.
     */
    try {
        require_once __DIR__ . '/entrants.php';
        $minted = mint_missing_entrant_share_tokens($pdo);
        if ($minted > 0) {
            error_log("minted {$minted} entrant share token(s)");
        }
    } catch (Throwable $e) {
        error_log('entrant share token minting failed: ' . $e->getMessage());
    }

    while ((microtime(true) - $startTime) < $budget) {
        $jobId = claim_next_job($pdo);
        if ($jobId === null) {
            break;
        }

        $stmt = $pdo->prepare('SELECT type, payload, attempts FROM jobs WHERE id = ?');
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            // Claimed then vanished between the UPDATE and this SELECT --
            // not expected (nothing else deletes a 'running' row), but not
            // worth crashing the drain over either.
            continue;
        }

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $type = (string)$job['type'];
        $payload = json_decode((string)$job['payload'], true) ?? [];
        $attempts = (int)$job['attempts'];

        $success = false;
        $lastError = null;
        try {
            if ($type === 'derivative') {
                $success = process_derivative_job($pdo, $payload);
            } elseif ($type === 'email') {
                $success = process_email_job($pdo, $payload);
            } elseif ($type === 'zip_build') {
                $success = process_zip_build_job($pdo, $payload);
            } elseif ($type === 'cleanup') {
                $success = process_cleanup_job($pdo, $payload);
            } elseif ($type === 'view_count') {
                $success = process_view_count_job($pdo, $payload);
            } elseif ($type === 'backup') {
                $success = process_backup_job($pdo, $payload);
            } else {
                $lastError = "Unknown job type \"{$type}\"";
            }
        } catch (Throwable $e) {
            $success = false;
            // Class name included because the message alone is often
            // ambiguous (an out-of-memory Error and a PDOException read
            // very differently to whoever is debugging this later).
            $lastError = get_class($e) . ': ' . $e->getMessage();
        }

        // A handler returning false without throwing is still a failure,
        // and it used to leave no trace at all.
        if (!$success && $lastError === null) {
            $lastError = "Handler for \"{$type}\" reported failure without an exception";
        }

        if ($success) {
            $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
        } else {
            // jobs.last_error exists and the health page reads it, but
            // nothing ever wrote to it: the reason a job died was thrown
            // away here, leaving "3 jobs failed" and no way to find out
            // why. Persist it on both the give-up and retry paths, and
            // log it too so the failure is visible even if the row is
            // later cleaned up.
            error_log("cron: job {$jobId} ({$type}) failed: {$lastError}");

            if ($attempts >= 3) {
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL, last_error = ? WHERE id = ?')
                    ->execute(['failed', $lastError, $jobId]);
            } else {
                $backoff = min(3600, (2 ** $attempts) * 60);
                $runAfter = (clone $now)->modify("+{$backoff} seconds")->format('Y-m-d H:i:s');
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL, run_after = ?, last_error = ? WHERE id = ?')
                    ->execute(['pending', $runAfter, $lastError, $jobId]);
            }
        }

        $processed++;
    }

    return $processed;
}

/**
 * Atomically claim the oldest eligible pending job and return its id, or
 * null if there is none.
 *
 * Two-step (SELECT a candidate, then UPDATE ... WHERE id = ? AND
 * status = 'pending') rather than the previous single UPDATE ... ORDER BY
 * ... LIMIT 1 followed by a separate `SELECT ... WHERE status = 'running'
 * ORDER BY id DESC LIMIT 1` to fetch "the row just claimed". That second
 * query was never actually guaranteed to be the same row the UPDATE claimed
 * -- it re-derived "most recent running job" from scratch, which happens to
 * match under this function's own single-claim-at-a-time usage but is not
 * what the UPDATE's rowCount() actually told the caller. Selecting by id
 * from the start removes the gap between "what was claimed" and "what gets
 * processed" entirely, rather than relying on the two queries agreeing.
 *
 * The lock is scoped to exactly this claim, not the caller's whole drain
 * loop -- see run_cron_drain()'s docblock for why that distinction matters
 * on SQLite specifically.
 */
function claim_next_job(PDO $pdo): ?int {
    $lockToken = 'pm_cron_claim';
    if (!db_acquire_lock($pdo, $lockToken, 5)) {
        return null;
    }

    try {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $lockedAtThreshold = (clone $now)->modify('-10 minutes')->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('
            SELECT id FROM jobs
            WHERE status = ? AND run_after <= ? AND (locked_at IS NULL OR locked_at < ?)
            ORDER BY id ASC
            LIMIT 1
        ');
        $stmt->execute(['pending', $now->format('Y-m-d H:i:s'), $lockedAtThreshold]);
        $candidateId = $stmt->fetchColumn();
        if ($candidateId === false) {
            return null;
        }

        $stmt = $pdo->prepare('
            UPDATE jobs
            SET status = ?, locked_at = ?, attempts = attempts + 1
            WHERE id = ? AND status = ?
        ');
        $stmt->execute(['running', $now->format('Y-m-d H:i:s'), $candidateId, 'pending']);

        // rowCount() === 0 means another process claimed this exact row
        // between the SELECT and this UPDATE -- possible even with the lock
        // above on MySQL, where GET_LOCK() is advisory and nothing stops a
        // connection that never calls it from writing the same row. Treated
        // as "nothing claimed", not an error: the caller's loop will try
        // again next iteration and pick a different candidate.
        return $stmt->rowCount() === 1 ? (int)$candidateId : null;
    } finally {
        db_release_lock($pdo, $lockToken);
    }
}

/**
 * Sends receipt or refund confirmation emails with download link.
 * Requires mail server configured via sendmail_path or SMTP settings.
 */
function process_email_job(PDO $pdo, array $payload): bool {
    require_once __DIR__ . '/mailer.php';

    $orderId = (int)($payload['order_id'] ?? 0);
    $emailType = (string)($payload['type'] ?? 'receipt');

    if ($orderId <= 0) {
        return false;
    }

    // Load config for email details
    $config = require __DIR__ . '/../../config/config.php';

    if ($emailType === 'receipt') {
        return send_receipt_email($pdo, $config, $orderId);
    } elseif ($emailType === 'refund') {
        $refundType = (string)($payload['refund_type'] ?? 'full');
        return send_refund_email($pdo, $config, $orderId, $refundType);
    }

    return false;
}

/**
 * Pre-builds ZIP files of purchased photos and caches them for quick download.
 * Reduces latency on high-traffic download endpoints by pre-building during
 * cron instead of blocking the download request.
 */
function process_zip_build_job(PDO $pdo, array $payload): bool {
    require_once __DIR__ . '/orders.php';

    $orderId = (int)($payload['order_id'] ?? 0);

    if ($orderId <= 0) {
        return false;
    }

    $items = get_order_items($pdo, $orderId);
    if (empty($items)) {
        return false;
    }

    $files = [];
    foreach ($items as $item) {
        $photoIds = resolve_order_item_photo_ids($pdo, $item);

        foreach ($photoIds as $photoId) {
            $stmt = $pdo->prepare('SELECT event_id, public_token, original_filename, file_extension FROM photos WHERE id = ?');
            $stmt->execute([$photoId]);
            $photo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($photo) {
                $eventId = (int)$photo['event_id'];
                $token = (string)$photo['public_token'];
                $ext = (string)($photo['file_extension'] ?? 'jpg');
                $filePath = __DIR__ . "/../../storage/hires/{$eventId}/{$token}.{$ext}";

                if (file_exists($filePath)) {
                    $filename = (string)($photo['original_filename'] ?? 'photo.jpg');
                    $files[] = [
                        'path' => $filePath,
                        'name' => $filename,
                    ];
                }
            }
        }
    }

    if (empty($files)) {
        return false;
    }

    $zipDir = __DIR__ . '/../../storage/zips';
    if (!is_dir($zipDir)) {
        @mkdir($zipDir, 0755, true);
    }

    $zipPath = "{$zipDir}/{$orderId}.zip";

    // Clean up any stale ZIP (in case rebuild is needed)
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }

    $zip = new ZipArchive();
    if (!$zip->open($zipPath, ZipArchive::CREATE)) {
        return false;
    }

    // Same collision handling as download.php's on-demand stream_zip():
    // bundles routinely contain photos with identical original_filename
    // values (e.g. every export from the same camera session named
    // "IMG_0001.jpg"), and ZipArchive::addFile() silently overwrites an
    // existing entry of the same name rather than erroring, so without
    // this a pre-built bundle zip can quietly contain fewer files than
    // the order actually paid for.
    $nameCount = [];
    foreach ($files as $file) {
        $name = $file['name'];
        $nameCount[$name] = ($nameCount[$name] ?? 0) + 1;

        if ($nameCount[$name] > 1) {
            $pathInfo = pathinfo($name);
            $name = $pathInfo['filename'] . '_' . $nameCount[$name] . '.' . ($pathInfo['extension'] ?? '');
        }

        $zip->addFile($file['path'], $name);
    }

    $zip->close();

    return file_exists($zipPath);
}

/**
 * Deletes the 1600px derivative for one photo.
 *
 * Retained as a handler, but nothing enqueues 'cleanup' jobs any more and
 * nothing should. Derivative generation used to queue one of these per photo
 * to delete the 1600px version after 7 days, which was a conversion leak:
 * motorsport galleries are frequently discovered late by word of mouth, and a
 * degraded preview at the moment of discovery loses the sale. That policy was
 * dropped in Stage 2.4.2 (all sizes now kept for the life of the photo) and
 * migration 011_purge_cleanup_jobs cleared the queued rows.
 *
 * This remains only so that any 'cleanup' row still sitting in an old database
 * drains cleanly instead of failing the job forever. Do not wire it back up
 * without revisiting the retention decision above.
 */
function process_cleanup_job(PDO $pdo, array $payload): bool {
    $photoId = (int)($payload['photo_id'] ?? 0);
    if ($photoId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT public_token FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $token = $stmt->fetchColumn();
    if (!$token) {
        return false;
    }

    $derivPath = __DIR__ . '/../../public/media/d';
    $largePath = "{$derivPath}/{$token}-1600.jpg";

    if (file_exists($largePath)) {
        @unlink($largePath);
    }

    return true;
}

/**
 * Async view count increment. API endpoint queues these jobs so the response
 * is fast; cron processes the queued increments asynchronously. Batches view
 * counts by date to avoid excessive database traffic.
 */
function process_view_count_job(PDO $pdo, array $payload): bool {
    $photoId = (int)($payload['photo_id'] ?? 0);
    $eventId = (int)($payload['event_id'] ?? 0);

    if ($photoId <= 0 || $eventId <= 0) {
        return false;
    }

    // Increment photo view count
    $pdo->prepare('UPDATE photos SET view_count = view_count + 1 WHERE id = ?')
        ->execute([$photoId]);

    // Increment daily stats for analytics
    $today = date('Y-m-d');
    if (db_supports_on_duplicate_key($pdo)) {
        $stmt = $pdo->prepare('
            INSERT INTO stats_daily (stat_date, event_id, photo_views)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE photo_views = photo_views + 1
        ');
        $stmt->execute([$today, $eventId]);
    } else {
        $stmt = $pdo->prepare('UPDATE stats_daily SET photo_views = photo_views + 1 WHERE stat_date = ? AND event_id = ?');
        $stmt->execute([$today, $eventId]);
        if ($stmt->rowCount() === 0) {
            $pdo->prepare('INSERT INTO stats_daily (stat_date, event_id, photo_views) VALUES (?, ?, 1)')
                ->execute([$today, $eventId]);
        }
    }

    return true;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/derivatives.php';
require_once __DIR__ . '/db_compat.php';

$GLOBALS['pdo'] = $GLOBALS['pdo'] ?? null;

/**
 * Full 50s-budget job drain, guarded by a MySQL advisory lock so an
 * overlapping cron tick (or a stuck previous run) exits immediately
 * instead of racing. Shared by the CLI entry (cron/run.php) and the
 * URL-invoked fallback (public/index.php's /cron/{secret} route) —
 * see docs/architecture.md section 5.
 */
function run_cron_drain(PDO $pdo): void {
    $GLOBALS['pdo'] = $pdo;
    $lockToken = 'pm_cron';
    if (!db_acquire_lock($pdo, $lockToken, 0)) {
        return;
    }

    $startTime = microtime(true);
    $budget = 50.0;

    try {
        while ((microtime(true) - $startTime) < $budget) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $lockedAtThreshold = (clone $now)->modify('-10 minutes')->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare('
                SELECT id, type, payload, attempts
                FROM jobs
                WHERE status = ? AND run_after <= ? AND (locked_at IS NULL OR locked_at < ?)
                ORDER BY id ASC
                LIMIT 1
            ');
            $stmt->execute(['pending', $now->format('Y-m-d H:i:s'), $lockedAtThreshold]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                break;
            }

            $jobId = (int)$job['id'];
            $stmt = $pdo->prepare('
                UPDATE jobs
                SET status = ?, locked_at = ?, attempts = attempts + 1
                WHERE id = ?
            ');
            $stmt->execute(['running', $now->format('Y-m-d H:i:s'), $jobId]);

            if ($stmt->rowCount() === 0) {
                break;
            }

            $type = (string)$job['type'];
            $payload = json_decode((string)$job['payload'], true) ?? [];
            $attempts = (int)$job['attempts'];

            $success = false;
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
                }
            } catch (Throwable $e) {
                error_log("Job {$jobId} ({$type}) failed: {$e->getMessage()}");
                $success = false;
            }

            if ($success) {
                $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
            } elseif ($attempts >= 3) {
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL WHERE id = ?')->execute(['failed', $jobId]);
            } else {
                $backoff = min(3600, (2 ** $attempts) * 60);
                $runAfter = (clone $now)->modify("+{$backoff} seconds")->format('Y-m-d H:i:s');
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL, run_after = ? WHERE id = ?')
                    ->execute(['pending', $runAfter, $jobId]);
            }
        }
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
 * Image tiering: deletes 1600px derivatives for photos older than 7 days
 * to save storage space. Smaller 400/800px versions remain for gallery display.
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

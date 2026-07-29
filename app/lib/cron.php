<?php
declare(strict_types=1);

require_once __DIR__ . '/derivatives.php';

/**
 * Full 50s-budget job drain, guarded by a MySQL advisory lock so an
 * overlapping cron tick (or a stuck previous run) exits immediately
 * instead of racing. Shared by the CLI entry (cron/run.php) and the
 * URL-invoked fallback (public/index.php's /cron/{secret} route) —
 * see docs/architecture.md section 5.
 */
function run_cron_drain(PDO $pdo): void {
    $lockToken = 'pm_cron';
    if (!$pdo->query("SELECT GET_LOCK('{$lockToken}', 0)")->fetchColumn()) {
        return;
    }

    $startTime = microtime(true);
    $budget = 50.0;

    while ((microtime(true) - $startTime) < $budget) {
        $stmt = $pdo->prepare('
            UPDATE jobs
            SET status = ?, locked_at = CURRENT_TIMESTAMP, attempts = attempts + 1
            WHERE status = ? AND run_after <= CURRENT_TIMESTAMP AND (locked_at IS NULL OR locked_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE))
            ORDER BY id ASC
            LIMIT 1
        ');
        $stmt->execute(['running', 'pending']);

        if ($stmt->rowCount() === 0) {
            break;
        }

        $stmt = $pdo->prepare('SELECT id, type, payload, attempts FROM jobs WHERE status = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute(['running']);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            break;
        }

        $jobId = (int)$job['id'];
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
            $success = false;
        }

        if ($success) {
            $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
        } elseif ($attempts >= 3) {
            $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL WHERE id = ?')->execute(['failed', $jobId]);
        } else {
            $backoff = min(3600, (2 ** $attempts) * 60);
            $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL, run_after = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ? SECOND) WHERE id = ?')
                ->execute(['pending', $backoff, $jobId]);
        }
    }

    $pdo->query("SELECT RELEASE_LOCK('{$lockToken}')");
}

/**
 * Sends receipt or refund confirmation emails with download link.
 * Requires mail server configured via sendmail_path or SMTP settings.
 */
function process_email_job(PDO $pdo, array $payload): bool {
    require_once __DIR__ . '/email.php';

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
        $photoId = (int)($item['photo_id'] ?? 0);
        if (!$photoId) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT event_id, public_token, original_filename, file_extension FROM photos WHERE id = ?');
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($photo) {
            $eventId = (int)$photo['event_id'];
            $token = (string)$photo['public_token'];
            $ext = (string)($photo['file_extension'] ?? 'jpg');
            $filePath = __DIR__ . "/../storage/hires/{$eventId}/{$token}.{$ext}";

            if (file_exists($filePath)) {
                $filename = (string)($photo['original_filename'] ?? 'photo.jpg');
                $files[] = [
                    'path' => $filePath,
                    'name' => $filename,
                ];
            }
        }
    }

    if (empty($files)) {
        return false;
    }

    $zipDir = __DIR__ . '/../storage/zips';
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

    foreach ($files as $file) {
        $zip->addFile($file['path'], $file['name']);
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
    $stmt = $pdo->prepare('
        INSERT INTO stats_daily (stat_date, event_id, photo_views)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE photo_views = photo_views + 1
    ');
    $stmt->execute([$today, $eventId]);

    return true;
}

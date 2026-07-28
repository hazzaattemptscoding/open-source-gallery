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
            SET status = ?, locked_at = NOW(), attempts = attempts + 1
            WHERE status = ? AND run_after <= NOW() AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))
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
            $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL, run_after = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?')
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
 * Currently stubbed; in production would build ZIP in /storage/zips/{orderId}.zip
 */
function process_zip_build_job(PDO $pdo, array $payload): bool {
    $orderId = (int)($payload['order_id'] ?? 0);

    if ($orderId <= 0) {
        return false;
    }

    // For now, ZIP building is stubbed. In a production setup with large
    // order counts, this would pre-build ZIPs for quick download delivery.
    // The download endpoint currently builds ZIPs on-demand.

    return true;
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

<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

/**
 * Fulfillment job lifecycle for NAS storage mode.
 *
 * When storage_mode is 'remote-nas', orders trigger fulfillment jobs instead
 * of direct downloads. A poller script on the user's always-on machine polls
 * /admin/api/fulfillment for pending jobs, sends WoL to the NAS, then polls
 * again when the NAS awakes. The NAS pushes originals back over SFTP, and
 * the job transitions to 'fulfilled' once files arrive.
 *
 * For 'local' storage mode, fulfillment jobs are never created; downloads
 * work as before (originals always available from storage/hires/).
 */

function create_fulfillment_job(PDO $pdo, int $orderId, string $macsAddress = ''): bool {
    $config = require __DIR__ . '/../../config/config.php';
    if (($config['storage_mode'] ?? 'local') !== 'remote-nas') {
        return false;
    }

    $stmt = $pdo->prepare('
        INSERT INTO jobs (type, payload, status, run_after)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ');

    return $stmt->execute([
        'fulfillment',
        json_encode([
            'order_id' => $orderId,
            'nas_mac_address' => $macsAddress,
        ]),
        'pending',
    ]);
}

/**
 * Mark a fulfillment job as WoL packet sent.
 * Called by the poller after sending magic packet to NAS.
 */
function mark_wol_sent(PDO $pdo, int $jobId): bool {
    $stmt = $pdo->prepare('UPDATE jobs SET wol_sent_at = CURRENT_TIMESTAMP WHERE id = ? AND type = ?');
    return $stmt->execute([$jobId, 'fulfillment']);
}

/**
 * Mark fulfillment complete (files arrived in temp storage).
 * Called by cron after detecting files pushed via SFTP.
 */
function mark_fulfilled(PDO $pdo, int $jobId): bool {
    $stmt = $pdo->prepare('UPDATE jobs SET status = ?, fulfilled_at = CURRENT_TIMESTAMP WHERE id = ? AND type = ?');
    return $stmt->execute(['fulfilled', $jobId, 'fulfillment']);
}

/**
 * Check for stalled fulfillment jobs (pending >15 min without WoL sent, or
 * WoL sent >15 min without fulfillment). Send alert email to admin.
 */
function check_stalled_fulfillment_jobs(PDO $pdo, array $config): void {
    $adminEmail = $config['site']['support_email'] ?? '';
    if (!$adminEmail) {
        return;
    }

    $stmt = $pdo->prepare('
        SELECT id, payload, DATE_ADD(created_at, INTERVAL 15 MINUTE) as stall_time
        FROM jobs
        WHERE type = ? AND status IN (?, ?)
        AND (
            (wol_sent_at IS NULL AND created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE))
            OR (wol_sent_at IS NOT NULL AND fulfilled_at IS NULL AND wol_sent_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE))
        )
        AND alert_sent_at IS NULL
    ');
    $stmt->execute(['fulfillment', 'pending', 'processing']);
    $stalledJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stalledJobs as $job) {
        $payload = json_decode($job['payload'], true);
        $orderId = $payload['order_id'] ?? 0;

        send_fulfillment_alert_email($adminEmail, $config, $orderId, $job['id']);

        $updateStmt = $pdo->prepare('UPDATE jobs SET alert_sent_at = CURRENT_TIMESTAMP WHERE id = ?');
        $updateStmt->execute([$job['id']]);
    }
}

/**
 * Cleanup temp fulfillment files (downloaded orders or 72h expired).
 * Called by cron job worker.
 */
function cleanup_fulfillment_temp_files(PDO $pdo, array $config): int {
    $tempDir = __DIR__ . '/../../storage/fulfillment_temp';
    if (!is_dir($tempDir)) {
        return 0;
    }

    $cleaned = 0;
    $files = array_diff(scandir($tempDir), ['.', '..']);

    foreach ($files as $file) {
        $filePath = "$tempDir/$file";
        $age = time() - filemtime($filePath);

        // Delete if older than 72 hours
        if ($age > (72 * 3600)) {
            if (unlink($filePath)) {
                $cleaned++;
            }
        }
    }

    return $cleaned;
}

/**
 * Clean up downloaded fulfillment files when they've been successfully delivered.
 * Called from the download controller when serving a fulfillment temp file.
 */
function mark_fulfillment_downloaded(PDO $pdo, int $orderId): void {
    $config = require __DIR__ . '/../../config/config.php';
    if (($config['storage_mode'] ?? 'local') !== 'remote-nas') {
        return;
    }

    $stmt = $pdo->prepare('
        UPDATE jobs
        SET status = ?
        WHERE type = ? AND payload LIKE ? AND status = ?
    ');
    $stmt->execute(['completed', 'fulfillment', "%order_id\":{$orderId}%", 'fulfilled']);
}

function send_fulfillment_alert_email(string $adminEmail, array $config, int $orderId, int $jobId): void {
    $siteName = $config['site']['name'] ?? 'Gallery';
    $subject = "Fulfillment Stalled: Order #{$orderId}";
    $body = <<<EOF
Order fulfillment has stalled. The poller on your home machine hasn't picked up
or completed this order within 15 minutes.

Order ID: {$orderId}
Job ID: {$jobId}

Possible causes:
- Poller script not running
- NAS not waking up to WoL packet
- SFTP push from NAS to server failed
- Network connectivity between server and NAS

Check:
1. Is the poller script running on your always-on machine?
2. Is the NAS responding to WoL packets?
3. Are network connectivity and SFTP credentials correct?
4. Check poller logs for errors

Manual recovery: You can manually upload the original files to storage/fulfillment_temp/
and the system will package and email them to the customer.

Site: {$siteName}
EOF;

    send_email($adminEmail, $subject, $body);
}

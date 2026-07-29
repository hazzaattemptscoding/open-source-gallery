<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/fulfillment.php';

/**
 * Fulfillment poller API — returns pending jobs for the NAS poller script.
 *
 * The poller script runs on the user's always-on machine (Raspberry Pi, old laptop, etc)
 * and polls this endpoint every 60-90 seconds. It's outbound-only; IONOS never initiates
 * a connection into the home network, keeping the home network secure.
 *
 * Only accessible when storage_mode is 'remote-nas'. Authentication via API token
 * (generated during setup wizard, stored in settings table).
 */

function admin_api_fulfillment_controller(PDO $pdo, array $config): void {
    if (($config['storage_mode'] ?? 'local') !== 'remote-nas') {
        http_response_code(404);
        echo json_encode(['error' => 'Feature not enabled']);
        exit;
    }

    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'poll';
    $token = $input['token'] ?? '';

    if (!verify_poller_token($pdo, $token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    match ($action) {
        'poll' => api_fulfillment_poll($pdo, $input),
        'wol-sent' => api_fulfillment_wol_sent($pdo, $input),
        'fulfilled' => api_fulfillment_fulfilled($pdo, $input),
        default => api_error('Unknown action'),
    };
}

function api_fulfillment_poll(PDO $pdo, array $input): void {
    $stmt = $pdo->prepare('
        SELECT id, payload, created_at
        FROM jobs
        WHERE type = ? AND status = ?
        ORDER BY created_at ASC
        LIMIT 50
    ');
    $stmt->execute(['fulfillment', 'pending']);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pending = [];
    foreach ($jobs as $job) {
        $payload = json_decode($job['payload'], true);
        $pending[] = [
            'job_id' => (int)$job['id'],
            'order_id' => (int)($payload['order_id'] ?? 0),
            'nas_mac' => $payload['nas_mac_address'] ?? '',
            'created_at' => $job['created_at'],
        ];
    }

    echo json_encode([
        'ok' => true,
        'count' => count($pending),
        'jobs' => $pending,
    ]);
}

function api_fulfillment_wol_sent(PDO $pdo, array $input): void {
    $jobId = (int)($input['job_id'] ?? 0);
    if ($jobId <= 0) {
        api_error('Invalid job_id');
        return;
    }

    if (mark_wol_sent($pdo, $jobId)) {
        echo json_encode(['ok' => true]);
    } else {
        api_error('Job not found');
    }
}

function api_fulfillment_fulfilled(PDO $pdo, array $input): void {
    $jobId = (int)($input['job_id'] ?? 0);
    if ($jobId <= 0) {
        api_error('Invalid job_id');
        return;
    }

    // Mark as 'fulfilled' — files have been pushed to fulfillment_temp/ directory
    $stmt = $pdo->prepare('UPDATE jobs SET status = ? WHERE id = ?');
    if ($stmt->execute(['fulfilled', $jobId])) {
        echo json_encode(['ok' => true]);
    } else {
        api_error('Job not found');
    }
}

function verify_poller_token(PDO $pdo, string $token): bool {
    if (!$token) {
        return false;
    }

    // Token is stored hashed in settings table (set during setup wizard)
    $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $stmt->execute(['fulfillment_poller_token_hash']);
    $storedHash = (string)$stmt->fetchColumn();

    return $storedHash !== '' && hash_equals($storedHash, hash('sha256', $token));
}

function api_error(string $message): void {
    http_response_code(400);
    echo json_encode(['error' => $message]);
    exit;
}

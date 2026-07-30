<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/derivatives.php';
require_once __DIR__ . '/../../lib/db_compat.php';

function admin_jobs_run_controller(PDO $pdo, array $config): void {
    require_admin();

    header('Content-Type: application/json');

    $lockToken = 'pm_cron_browser';
    if (!db_acquire_lock($pdo, $lockToken, 0)) {
        echo json_encode(['status' => 'locked']);
        return;
    }

    $startTime = microtime(true);
    $budget = 20.0;
    $jobsProcessed = 0;

    try {
        while ((microtime(true) - $startTime) < $budget) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $stmt = $pdo->prepare('
                UPDATE jobs
                SET status = ?, locked_at = ?
                WHERE status = ? AND run_after <= ?
                ORDER BY id ASC
                LIMIT 1
            ');
            $stmt->execute(['running', $now->format('Y-m-d H:i:s'), 'pending', $now->format('Y-m-d H:i:s')]);

        if ($stmt->rowCount() === 0) {
            break;
        }

        $stmt = $pdo->prepare('SELECT id, type, payload FROM jobs WHERE status = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute(['running']);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            break;
        }

        $jobId = (int)$job['id'];
        $type = (string)$job['type'];
        $payload = json_decode((string)$job['payload'], true) ?? [];

        $success = process_job($pdo, $type, $payload);

            if ($success) {
                $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
                $jobsProcessed++;
            } else {
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL WHERE id = ?')->execute(['pending', $jobId]);
                break;
            }
        }
    } finally {
        db_release_lock($pdo, $lockToken);
    }

    echo json_encode([
        'status' => 'ok',
        'processed' => $jobsProcessed,
        'elapsed' => round(microtime(true) - $startTime, 2),
    ]);
}

function process_job(PDO $pdo, string $type, array $payload): bool {
    try {
        if ($type === 'derivative') {
            return process_derivative_job($pdo, $payload);
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

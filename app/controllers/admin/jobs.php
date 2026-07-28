<?php
declare(strict_types=1);

require __DIR__ . '/../../lib/auth.php';

function admin_jobs_run_controller(PDO $pdo, array $config): void {
    require_admin();

    header('Content-Type: application/json');

    $lockToken = 'pm_cron_browser';
    if (!$pdo->query("SELECT GET_LOCK('{$lockToken}', 0)")->fetchColumn()) {
        echo json_encode(['status' => 'locked']);
        return;
    }

    $startTime = microtime(true);
    $budget = 20.0;
    $jobsProcessed = 0;

    while ((microtime(true) - $startTime) < $budget) {
        $stmt = $pdo->prepare('
            UPDATE jobs
            SET status = ?, locked_at = NOW()
            WHERE status = ? AND run_after <= NOW()
            ORDER BY id ASC
            LIMIT 1
        ');
        $stmt->execute(['running', 'pending']);

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

        $success = process_job($pdo, $config, $type, $payload);

        if ($success) {
            $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
            $jobsProcessed++;
        } else {
            $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL WHERE id = ?')->execute(['pending', $jobId]);
            break;
        }
    }

    $pdo->query("SELECT RELEASE_LOCK('{$lockToken}')");

    echo json_encode([
        'status' => 'ok',
        'processed' => $jobsProcessed,
        'elapsed' => round(microtime(true) - $startTime, 2),
    ]);
}

function process_job(PDO $pdo, array $config, string $type, array $payload): bool {
    try {
        if ($type === 'derivative') {
            return process_derivative_job($pdo, $config, $payload);
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function process_derivative_job(PDO $pdo, array $config, array $payload): bool {
    $photoId = (int)($payload['photo_id'] ?? 0);
    if ($photoId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, event_id, public_token FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        return false;
    }

    require __DIR__ . '/../../lib/images.php';

    $eventId = (int)$photo['event_id'];
    $token = (string)$photo['public_token'];
    $hiresPath = __DIR__ . "/../../storage/hires/{$eventId}/{$token}.jpg";

    if (!file_exists($hiresPath)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT skey, svalue FROM settings WHERE skey LIKE ?');
    $stmt->execute(['watermark_%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = [
        'text' => 'WATERMARK',
        'opacity' => 0.3,
        'position' => 'bottom-right',
    ];

    foreach ($rows as $row) {
        $key = str_replace('watermark_', '', $row['skey']);
        $settings[$key] = $row['svalue'];
    }

    $sizes = [400, 800, 1600];
    $derivPath = __DIR__ . '/../../public/media/d';

    if (!is_dir($derivPath)) {
        @mkdir($derivPath, 0755, true);
    }

    foreach ($sizes as $size) {
        $outPath = "{$derivPath}/{$token}-{$size}.jpg";
        $watermark = ($size >= 800);
        generate_derivative($hiresPath, $outPath, $size, $watermark, $settings);
    }

    $stmt = $pdo->prepare('UPDATE photos SET status = ? WHERE id = ?');
    $stmt->execute(['live', $photoId]);
    return true;
}

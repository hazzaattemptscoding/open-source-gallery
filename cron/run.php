<?php
/**
 * Cron entry point. Invoked via CLI or HTTP (GET /cron/{CRON_SECRET}).
 * Claims a distributed lock, drains jobs for ~50s, then exits.
 * No framework, single responsibility: loop over pending jobs and execute them.
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$secret = $config['cron_secret'] ?? '';
if ($secret === '') {
    die('CRON_SECRET not configured.');
}

if (php_sapi_name() !== 'cli') {
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
    if ($path !== "/cron/{$secret}") {
        http_response_code(404);
        exit;
    }
    http_response_code(200);
    header('Content-Type: text/plain');
}

run_cron($pdo);

function run_cron(PDO $pdo): void {
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
        $error = '';

        try {
            if ($type === 'derivative') {
                $success = process_derivative_job($pdo, $payload);
            } elseif ($type === 'email') {
                $success = process_email_job($pdo, $payload);
            } elseif ($type === 'cleanup') {
                $success = process_cleanup_job($pdo, $payload);
            } else {
                $error = "Unknown job type: {$type}";
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        if ($success) {
            $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
        } else {
            if ($attempts >= 3) {
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL WHERE id = ?')->execute(['failed', $jobId]);
            } else {
                $backoff = min(3600, pow(2, $attempts) * 60);
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL, run_after = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?')
                    ->execute(['pending', $backoff, $jobId]);
            }
        }
    }

    $pdo->query("SELECT RELEASE_LOCK('{$lockToken}')");
}

function process_derivative_job(PDO $pdo, array $payload): bool {
    $photoId = (int)($payload['photo_id'] ?? 0);
    if ($photoId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, event_id, public_token, hires_size_bytes FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        return false;
    }

    global $config;
    require __DIR__ . '/../app/lib/images.php';

    $eventId = (int)$photo['event_id'];
    $token = (string)$photo['public_token'];
    $hiresPath = __DIR__ . "/../storage/hires/{$eventId}/{$token}.jpg";

    if (!file_exists($hiresPath)) {
        return false;
    }

    $settings = get_watermark_settings($pdo);
    $sizes = [400, 800, 1600];
    $derivPath = __DIR__ . '/../public/media/d';

    if (!is_dir($derivPath)) {
        @mkdir($derivPath, 0755, true);
    }

    try {
        foreach ($sizes as $size) {
            $outPath = "{$derivPath}/{$token}-{$size}.jpg";
            $watermark = ($size >= 800);
            generate_derivative($hiresPath, $outPath, $size, $watermark, $settings);
        }
    } catch (Throwable $e) {
        $stmt = $pdo->prepare('UPDATE photos SET status = ? WHERE id = ?');
        $stmt->execute(['failed', $photoId]);
        return false;
    }

    $stmt = $pdo->prepare('UPDATE photos SET status = ? WHERE id = ?');
    $stmt->execute(['live', $photoId]);
    return true;
}

function process_email_job(PDO $pdo, array $payload): bool {
    return true;
}

function process_cleanup_job(PDO $pdo, array $payload): bool {
    return true;
}

function get_watermark_settings(PDO $pdo): array {
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

    return $settings;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/images.php';

/**
 * Generates the 400/800/1600px derivatives for one photo and flips it to
 * 'live'. Shared by cron/run.php and the browser-assisted drain
 * (app/controllers/admin/jobs.php) so the two paths can't drift apart.
 */
function process_derivative_job(PDO $pdo, array $payload): bool {
    $photoId = (int)($payload['photo_id'] ?? 0);
    if ($photoId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, event_id, public_token, file_extension FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        return false;
    }

    $eventId = (int)$photo['event_id'];
    $token = (string)$photo['public_token'];
    $ext = (string)($photo['file_extension'] ?? 'jpg');
    $hiresPath = __DIR__ . "/../../storage/hires/{$eventId}/{$token}.{$ext}";

    if (!file_exists($hiresPath)) {
        return false;
    }

    $settings = get_watermark_settings($pdo);
    // Include photo ID in watermark text so previews can be identified by buyers
    $settings['text'] = "ID: {$photoId}";

    $sizes = [400, 800, 1200, 1600];
    $derivPath = __DIR__ . '/../../public/media/d';

    if (!is_dir($derivPath)) {
        @mkdir($derivPath, 0755, true);
    }

    $derivBytes = 0;
    try {
        foreach ($sizes as $size) {
            $outPath = "{$derivPath}/{$token}-{$size}.jpg";
            $watermark = $settings['enabled'] && $size >= $settings['min_width'];
            generate_derivative($hiresPath, $outPath, $size, $watermark, $settings);
            $bytes = (int)@filesize($outPath);
            if ($bytes > 0) {
                $derivBytes += $bytes;
            }
        }
    } catch (Throwable $e) {
        $pdo->prepare('UPDATE photos SET status = ? WHERE id = ?')->execute(['failed', $photoId]);
        return false;
    }

    $pdo->prepare('UPDATE photos SET status = ?, deriv_size_bytes = ? WHERE id = ?')
        ->execute(['live', $derivBytes, $photoId]);

    return true;
}

/**
 * Reads the watermark_* rows from settings (percent-based opacity/scale,
 * per migrations/001_initial_schema.sql seed data) and converts them into
 * the fractions/ints app/lib/images.php expects.
 */
function get_watermark_settings(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT skey, svalue FROM settings WHERE skey LIKE ?');
    $stmt->execute(['watermark_%']);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'enabled' => (bool)($rows['watermark_enabled'] ?? '1'),
        'opacity' => ((float)($rows['watermark_opacity'] ?? 35)) / 100,
        'scale' => ((float)($rows['watermark_scale'] ?? 22)) / 100,
        'position' => $rows['watermark_position'] ?? 'bottom-right',
        'min_width' => (int)($rows['watermark_min_width'] ?? 800),
        'text' => $rows['watermark_text'] ?? 'PREVIEW',
    ];
}

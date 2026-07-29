<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/orders.php';
require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../lib/audit.php';

/**
 * GET /download/{token} — validates download link and streams files.
 * Token is a raw string; we hash it server-side and look up in download_links.
 * Checks expiry and download count before streaming. Records download.
 */
function public_download_controller(PDO $pdo, array $config, string $rawToken): void {
    $ip = get_client_ip();
    if (!check_rate_limit($pdo, 'download', $ip, 3600, 30)) {
        http_response_code(429);
        echo 'Too many download attempts. Try again later.';
        return;
    }

    $tokenHash = hash('sha256', $rawToken, true);
    $encodedHash = strtr(base64_encode($tokenHash), '+/', '-_');

    $stmt = $pdo->prepare('
        SELECT id, order_id, expires_at, max_downloads, download_count, revoked
        FROM download_links
        WHERE token_hash = ?
    ');
    $stmt->execute([$encodedHash]);
    $downloadLink = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$downloadLink) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    if ((int)($downloadLink['revoked'] ?? 0) === 1) {
        http_response_code(410);
        echo 'Download link revoked';
        return;
    }

    if (strtotime($downloadLink['expires_at']) < time()) {
        http_response_code(410);
        echo 'Download link expired';
        return;
    }

    $orderId = (int)$downloadLink['order_id'];
    $items = get_order_items($pdo, $orderId);

    if (empty($items)) {
        http_response_code(404);
        echo 'No items in order';
        return;
    }

    $files = [];

    foreach ($items as $item) {
        $photoId = (int)($item['photo_id'] ?? 0);
        $sessionId = (int)($item['session_id'] ?? 0);
        $eventId = (int)($item['event_id'] ?? 0);

        $photoIds = [];
        if ($photoId) {
            $photoIds = [$photoId];
        } elseif ($sessionId) {
            $stmt = $pdo->prepare('SELECT DISTINCT photo_id FROM session_photos WHERE session_id = ?');
            $stmt->execute([$sessionId]);
            $photoIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'photo_id');
        } elseif ($eventId) {
            $stmt = $pdo->prepare('SELECT id FROM photos WHERE event_id = ? AND status = ?');
            $stmt->execute([$eventId, 'live']);
            $photoIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        }

        foreach ($photoIds as $pid) {
            $stmt = $pdo->prepare('
                SELECT id, event_id, public_token, original_filename, file_extension FROM photos WHERE id = ?
            ');
            $stmt->execute([$pid]);
            $photo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($photo) {
                $eventIdFromPhoto = (int)$photo['event_id'];
                $token = (string)$photo['public_token'];
                $ext = (string)($photo['file_extension'] ?? 'jpg');
                $filePath = __DIR__ . "/../../storage/hires/{$eventIdFromPhoto}/{$token}.{$ext}";

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
        http_response_code(404);
        echo 'No files found';
        return;
    }

    // Atomically increment download count, but only if under the cap
    $stmt = $pdo->prepare('UPDATE download_links SET download_count = download_count + 1, last_used_at = CURRENT_TIMESTAMP WHERE id = ? AND download_count < max_downloads');
    $stmt->execute([$downloadLink['id']]);

    if ($stmt->rowCount() === 0) {
        http_response_code(410);
        echo 'Download limit exceeded';
        return;
    }

    audit_log($pdo, 'public', 'download', 'order', $orderId, [
        'file_count' => count($files),
    ], $ip);

    // Single file: stream directly
    if (count($files) === 1) {
        stream_file($files[0]['path']);
        return;
    }

    // Multiple files: use pre-built ZIP if available, build on-demand otherwise
    stream_zip($files, $orderId);
}

function stream_file(string $filePath): void {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(500);
        echo 'File not available';
        return;
    }

    $filename = basename($filePath);
    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
}

function stream_zip(array $files, int $orderId = 0): void {
    $zipPath = null;

    // Check for pre-built ZIP from cron job
    if ($orderId > 0) {
        $prebuiltZip = __DIR__ . "/../../storage/zips/{$orderId}.zip";
        if (file_exists($prebuiltZip) && is_readable($prebuiltZip)) {
            $zipPath = $prebuiltZip;
        }
    }

    // Build on-demand if no pre-built ZIP
    if (!$zipPath) {
        $tempZip = tempnam(sys_get_temp_dir(), 'zip_');
        if (!$tempZip) {
            http_response_code(500);
            echo 'Unable to create zip';
            return;
        }

        $zip = new ZipArchive();
        if (!$zip->open($tempZip, ZipArchive::CREATE)) {
            unlink($tempZip);
            http_response_code(500);
            echo 'Unable to create zip';
            return;
        }

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }

        $zip->close();
        $zipPath = $tempZip;
    }

    if (!file_exists($zipPath) || !is_readable($zipPath)) {
        http_response_code(500);
        echo 'ZIP file not available';
        return;
    }

    $fileSize = filesize($zipPath);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="photos.zip"');
    header('Content-Length: ' . $fileSize);

    readfile($zipPath);

    // Only clean up temp file, not pre-built ZIPs (they persist for future downloads)
    if (strpos($zipPath, sys_get_temp_dir()) === 0) {
        unlink($zipPath);
    }
}

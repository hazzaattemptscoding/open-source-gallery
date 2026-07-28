<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/orders.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

/**
 * GET /download/{token} — validates download link and streams files.
 * Token is a raw string; we hash it server-side and look up in download_links.
 * Checks expiry and download count before streaming. Records download.
 */
function public_download_controller(PDO $pdo, array $config, string $rawToken): void {
    $ip = get_client_ip();
    $rateLimitKey = hash('sha256', "download:{$ip}");
    if (!check_rate_limit($pdo, $rateLimitKey, 30)) {
        http_response_code(429);
        echo 'Too many download attempts. Try again later.';
        return;
    }

    $tokenHash = hash('sha256', $rawToken, true);
    $encodedHash = strtr(base64_encode($tokenHash), '+/', '-_');

    $stmt = $pdo->prepare('
        SELECT id, order_id, expires_at, max_downloads, downloads_used
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

    if (strtotime($downloadLink['expires_at']) < time()) {
        http_response_code(410);
        echo 'Download link expired';
        return;
    }

    if ($downloadLink['downloads_used'] >= $downloadLink['max_downloads']) {
        http_response_code(410);
        echo 'Download limit exceeded';
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
        if (!$photoId) {
            continue;
        }

        $stmt = $pdo->prepare('
            SELECT id, event_id, public_token, original_filename FROM photos WHERE id = ?
        ');
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($photo) {
            $eventId = (int)$photo['event_id'];
            $token = (string)$photo['public_token'];
            $filePath = __DIR__ . "/../../storage/hires/{$eventId}/{$token}.jpg";

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
        http_response_code(404);
        echo 'No files found';
        return;
    }

    // Record download
    $stmt = $pdo->prepare('UPDATE download_links SET downloads_used = downloads_used + 1 WHERE id = ?');
    $stmt->execute([$downloadLink['id']]);

    // Single file: stream directly
    if (count($files) === 1) {
        stream_file($files[0]['path']);
        return;
    }

    // Multiple files: zip them
    stream_zip($files);
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

function stream_zip(array $files): void {
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

    $fileSize = filesize($tempZip);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="photos.zip"');
    header('Content-Length: ' . $fileSize);

    readfile($tempZip);
    unlink($tempZip);
}

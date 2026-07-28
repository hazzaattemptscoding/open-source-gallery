<?php
declare(strict_types=1);

const CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB

function init_upload_batch(PDO $pdo): int {
    $stmt = $pdo->prepare('INSERT INTO upload_batches DEFAULT VALUES');
    $stmt->execute();
    return (int)$pdo->lastInsertId();
}

function register_upload_file(PDO $pdo, int $batchId, int $chunksTotal): int {
    $stmt = $pdo->prepare('
        INSERT INTO upload_files (batch_id, chunks_total, chunks_received, status)
        VALUES (?, ?, 0, ?)
    ');
    $stmt->execute([$batchId, $chunksTotal, 'uploading']);
    return (int)$pdo->lastInsertId();
}

function validate_image_file(string $filePath): string {
    if (!file_exists($filePath)) {
        return 'File not found.';
    }

    if (!is_readable($filePath)) {
        return 'File not readable.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string)finfo_file($finfo, $filePath) : null;
    finfo_close($finfo);

    if (!$mimeType || !in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
        return "Invalid file type: {$mimeType}. Only JPEG and PNG allowed.";
    }

    $info = @getimagesize($filePath);
    if (!$info) {
        return 'Not a valid image or corrupted.';
    }

    [$width, $height, $type] = $info;
    $mimeFromGetImageSize = image_type_to_mime_type($type);
    if ($mimeFromGetImageSize !== $mimeType) {
        return 'File extension does not match content (mime type mismatch).';
    }

    if ($width < 400 || $height < 400) {
        return 'Image must be at least 400×400 pixels.';
    }

    if ($width > 16384 || $height > 16384) {
        return 'Image is too large (max 16384×16384 px).';
    }

    return '';
}

function extract_exif_taken_at(string $filePath): ?string {
    if (!extension_loaded('exif')) {
        return null;
    }

    $exif = @exif_read_data($filePath);
    if (!$exif) {
        return null;
    }

    if (isset($exif['DateTimeOriginal'])) {
        $dt = $exif['DateTimeOriginal'];
        if (preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $dt, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
    }

    if (isset($exif['DateTime'])) {
        $dt = $exif['DateTime'];
        if (preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $dt, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
    }

    return null;
}

function sanitize_filename(string $original): string {
    $name = pathinfo($original, PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
    $name = preg_replace('/-+/', '-', $name);
    $name = trim($name, '-');
    return $name ?: 'upload';
}

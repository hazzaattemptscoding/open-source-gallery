<?php
declare(strict_types=1);

const CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB

function init_upload_batch(PDO $pdo, int $sessionId): int {
    $stmt = $pdo->prepare('INSERT INTO upload_batches (session_id) VALUES (?)');
    $stmt->execute([$sessionId]);
    return (int)$pdo->lastInsertId();
}

function register_upload_file(PDO $pdo, int $batchId, string $clientName, int $sizeBytes, int $chunksTotal): int {
    $stmt = $pdo->prepare('
        INSERT INTO upload_files (batch_id, client_name, size_bytes, chunk_size, chunks_total, chunks_received, status)
        VALUES (?, ?, ?, ?, ?, 0, ?)
    ');
    $stmt->execute([$batchId, $clientName, $sizeBytes, CHUNK_SIZE, $chunksTotal, 'uploading']);
    return (int)$pdo->lastInsertId();
}

/**
 * @param list<string>|null $allowedFormats File extensions (e.g. ['jpg','png'])
 *        the admin has chosen to accept, from photos.allowed_formats. Only
 *        ever narrows the effective set: intersected with the MIME types this
 *        function actually decodes, never widens past them. The MIME check
 *        below is the real security boundary and a settings value must not be
 *        able to move it -- an admin cannot enable HEIC uploads by typing
 *        "heic" into a text field, since nothing downstream can decode one.
 */
function validate_image_file(string $filePath, ?array $allowedFormats = null): string {
    if (!file_exists($filePath)) {
        return 'File not found.';
    }

    if (!is_readable($filePath)) {
        return 'File not readable.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string)finfo_file($finfo, $filePath) : null;
    finfo_close($finfo);

    $decodable = ['image/jpeg', 'image/png'];
    $accepted = $decodable;
    if ($allowedFormats !== null) {
        $extToMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        $requested = array_filter(array_map(fn($ext) => $extToMime[strtolower(trim($ext))] ?? null, $allowedFormats));
        // Intersect, never union: an unrecognised or undecodable extension in
        // the setting (heic, webp, ...) is silently dropped rather than
        // widening what this function will accept.
        $accepted = array_values(array_intersect($decodable, $requested)) ?: $decodable;
    }

    if (!$mimeType || !in_array($mimeType, $accepted, true)) {
        $allowedList = implode(' or ', array_map(fn($m) => strtoupper(str_replace('image/', '', $m)), $accepted));
        return "Invalid file type: {$mimeType}. Only {$allowedList} allowed.";
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

    foreach (['DateTimeOriginal', 'DateTime'] as $field) {
        if (isset($exif[$field]) && preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}:\d{2}:\d{2})$/', $exif[$field], $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}";
        }
    }

    return null;
}

/**
 * original_filename is display/reference only, never used as a path (see
 * migrations/001_initial_schema.sql). Strip control characters and cap
 * length to fit the column; anything else in the client-supplied name is
 * safe because it's always escaped through e() on output.
 */
function safe_original_filename(string $original): string {
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $original);
    $name = trim((string)$name);
    return $name !== '' ? mb_substr($name, 0, 255) : 'upload.jpg';
}

function get_file_extension(string $filePath): string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string)finfo_file($finfo, $filePath) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === 'image/png') {
        return 'png';
    }
    return 'jpg';
}

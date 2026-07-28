<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/upload.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_upload_controller(PDO $pdo, array $config): void {
    require_admin();
    $adminId = current_admin_id();
    $ip = client_ip();

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
    $method = $_SERVER['REQUEST_METHOD'];

    if ($path === '/admin/upload/init' && $method === 'POST') {
        handle_init($pdo, $adminId, $ip);
    } elseif ($path === '/admin/upload/chunk' && $method === 'POST') {
        handle_chunk($pdo, $adminId, $ip);
    } elseif ($path === '/admin/upload/finalize' && $method === 'POST') {
        handle_finalize($pdo, $adminId, $ip);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
}

function handle_init(PDO $pdo, int $adminId, string $ip): void {
    $sessionId = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    $fileStrings = $_POST['files'] ?? [];

    if ($sessionId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id required']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        return;
    }

    if (empty($fileStrings) || !is_array($fileStrings)) {
        http_response_code(400);
        echo json_encode(['error' => 'No files specified']);
        return;
    }

    $batchId = init_upload_batch($pdo, $sessionId);
    $response = [];

    foreach ($fileStrings as $fileString) {
        $file = json_decode($fileString, true);
        if (!is_array($file)) {
            continue;
        }

        $fileSize = (int)($file['size'] ?? 0);
        $fileName = safe_original_filename((string)($file['name'] ?? ''));

        if ($fileSize <= 0) {
            continue;
        }

        $chunksTotal = (int)ceil($fileSize / CHUNK_SIZE);
        $fileId = register_upload_file($pdo, $batchId, $fileName, $fileSize, $chunksTotal);

        $response[] = [
            'file_id' => $fileId,
            'name' => $fileName,
            'size' => $fileSize,
            'chunks_total' => $chunksTotal,
        ];
    }

    audit_log($pdo, 'admin', 'upload_batch_initiated', 'batch', $batchId, ['file_count' => count($response)], $ip);

    header('Content-Type: application/json');
    echo json_encode(['batch_id' => $batchId, 'files' => $response]);
}

function handle_chunk(PDO $pdo, int $adminId, string $ip): void {
    $fileId = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : 0;

    if ($fileId <= 0 || $chunkIndex < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file_id or chunk_index']);
        return;
    }

    $stmt = $pdo->prepare('SELECT batch_id, chunks_total, chunks_received, status FROM upload_files WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }

    if ($file['status'] !== 'uploading') {
        http_response_code(400);
        echo json_encode(['error' => 'Upload already completed or failed']);
        return;
    }

    if ($chunkIndex >= (int)$file['chunks_total']) {
        http_response_code(400);
        echo json_encode(['error' => 'Chunk index out of range']);
        return;
    }

    if (!isset($_FILES['chunk'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No chunk data']);
        return;
    }

    $chunk = $_FILES['chunk'];
    if ($chunk['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => "Upload error: {$chunk['error']}"]);
        return;
    }

    $tmpDir = __DIR__ . '/../../../storage/tmp/uploads/' . $fileId;
    if (!is_dir($tmpDir)) {
        if (!@mkdir($tmpDir, 0700, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create upload directory']);
            return;
        }
    }

    $chunkPath = $tmpDir . '/chunk-' . $chunkIndex;
    if (!@move_uploaded_file($chunk['tmp_name'], $chunkPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save chunk']);
        return;
    }

    $newChunksReceived = (int)$file['chunks_received'] + 1;
    $stmt = $pdo->prepare('UPDATE upload_files SET chunks_received = ? WHERE id = ?');
    $stmt->execute([$newChunksReceived, $fileId]);

    audit_log($pdo, 'admin', 'upload_chunk_received', 'file', $fileId, ['chunk' => $chunkIndex, 'total' => $file['chunks_total']], $ip);

    header('Content-Type: application/json');
    echo json_encode([
        'file_id' => $fileId,
        'chunk_index' => $chunkIndex,
        'chunks_received' => $newChunksReceived,
        'chunks_total' => $file['chunks_total'],
    ]);
}

function handle_finalize(PDO $pdo, int $adminId, string $ip): void {
    $fileId = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;

    if ($fileId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file_id']);
        return;
    }

    $stmt = $pdo->prepare('
        SELECT uf.batch_id, uf.client_name, uf.chunks_total, uf.chunks_received, uf.status, ub.session_id
        FROM upload_files uf
        JOIN upload_batches ub ON ub.id = uf.batch_id
        WHERE uf.id = ?
    ');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }

    if ($file['status'] !== 'uploading') {
        http_response_code(400);
        echo json_encode(['error' => 'Upload already completed or failed']);
        return;
    }

    if ((int)$file['chunks_received'] !== (int)$file['chunks_total']) {
        http_response_code(400);
        echo json_encode(['error' => 'Not all chunks received']);
        return;
    }

    $sessionId = (int)$file['session_id'];
    $stmt = $pdo->prepare('SELECT event_id FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        return;
    }

    $eventId = (int)$session['event_id'];
    $tmpDir = __DIR__ . '/../../../storage/tmp/uploads/' . $fileId;
    $assembledPath = $tmpDir . '/assembled.jpg';

    if (!assemble_chunks($tmpDir, (int)$file['chunks_total'], $assembledPath)) {
        $pdo->prepare('UPDATE upload_files SET status = ?, error = ? WHERE id = ?')
            ->execute(['failed', 'Failed to assemble chunks', $fileId]);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to assemble chunks']);
        return;
    }

    $validationError = validate_image_file($assembledPath);
    if ($validationError) {
        @unlink($assembledPath);
        $pdo->prepare('UPDATE upload_files SET status = ?, error = ? WHERE id = ?')
            ->execute(['failed', $validationError, $fileId]);
        http_response_code(400);
        echo json_encode(['error' => $validationError]);
        return;
    }

    $imageSize = @getimagesize($assembledPath);
    if (!$imageSize) {
        @unlink($assembledPath);
        $pdo->prepare('UPDATE upload_files SET status = ?, error = ? WHERE id = ?')
            ->execute(['failed', 'Could not read image dimensions', $fileId]);
        http_response_code(400);
        echo json_encode(['error' => 'Could not read image dimensions']);
        return;
    }

    $width = (int)$imageSize[0];
    $height = (int)$imageSize[1];
    $fileSize = (int)@filesize($assembledPath);
    $takenAt = extract_exif_taken_at($assembledPath);
    $originalFilename = (string)$file['client_name'];

    $publicToken = generate_public_token();
    $photoDir = __DIR__ . '/../../../storage/hires/' . $eventId;
    if (!is_dir($photoDir)) {
        @mkdir($photoDir, 0700, true);
    }

    $hiresPath = $photoDir . '/' . $publicToken . '.jpg';
    if (!@rename($assembledPath, $hiresPath)) {
        @unlink($assembledPath);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move file to storage']);
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO photos (public_token, event_id, session_id, status, original_filename, width, height, hires_size_bytes, taken_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$publicToken, $eventId, $sessionId, 'processing', $originalFilename, $width, $height, $fileSize, $takenAt]);
    $photoId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE upload_files SET status = ?, photo_id = ? WHERE id = ?');
    $stmt->execute(['done', $photoId, $fileId]);

    queue_derivative_job($pdo, $photoId);
    audit_log($pdo, 'admin', 'photo_uploaded', 'photo', $photoId, ['public_token' => $publicToken, 'size' => $fileSize], $ip);

    @rmdir($tmpDir);

    header('Content-Type: application/json');
    echo json_encode([
        'photo_id' => $photoId,
        'public_token' => $publicToken,
        'status' => 'processing',
    ]);
}

function assemble_chunks(string $tmpDir, int $chunksTotal, string $outputPath): bool {
    $out = @fopen($outputPath, 'wb');
    if (!$out) {
        return false;
    }

    for ($i = 0; $i < $chunksTotal; $i++) {
        $chunkPath = $tmpDir . '/chunk-' . $i;
        $chunk = @file_get_contents($chunkPath);
        if ($chunk === false) {
            @fclose($out);
            return false;
        }
        if (fwrite($out, $chunk) === false) {
            @fclose($out);
            return false;
        }
    }

    return @fclose($out) !== false;
}

function generate_public_token(): string {
    $bytes = random_bytes(12);
    $base62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $token = '';
    for ($i = 0; $i < 12; $i++) {
        $token .= $base62[ord($bytes[$i]) % 62];
    }
    return $token;
}

function queue_derivative_job(PDO $pdo, int $photoId): void {
    $stmt = $pdo->prepare('
        INSERT INTO jobs (type, payload, status, attempts, run_after)
        VALUES (?, ?, ?, 0, NOW())
    ');
    $stmt->execute(['derivative', json_encode(['photo_id' => $photoId]), 'pending']);
}

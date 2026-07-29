<?php
/**
 * REST API v1: Photos endpoint.
 * /api/v1/photos - list and retrieve photos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/api.php';

function api_v1_photos_controller(PDO $pdo): void {
    $start = microtime(true);
    header('Content-Type: application/json');

    $apiKey = $_GET['api_key'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (strpos($apiKey, 'Bearer ') === 0) {
        $apiKey = substr($apiKey, 7);
    }

    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'API key required']);
        exit;
    }

    $key = validate_api_key($pdo, $apiKey);
    if (!$key || !has_api_permission($key, 'read:photos')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API key or insufficient permissions']);
        exit;
    }

    $page = (int)($_GET['page'] ?? 1);
    $perPage = min((int)($_GET['per_page'] ?? 50), 250);

    $photos = api_get_photos($pdo, $page, $perPage);

    $elapsed = (int)((microtime(true) - $start) * 1000);
    log_api_request($pdo, $key['id'], '/api/v1/photos', 'GET', 200, $elapsed);

    echo json_encode([
        'success' => true,
        'page' => $page,
        'per_page' => $perPage,
        'count' => count($photos),
        'data' => $photos,
    ]);
}

function api_v1_photo_controller(PDO $pdo, int $photoId): void {
    $start = microtime(true);
    header('Content-Type: application/json');

    $apiKey = $_GET['api_key'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (strpos($apiKey, 'Bearer ') === 0) {
        $apiKey = substr($apiKey, 7);
    }

    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'API key required']);
        exit;
    }

    $key = validate_api_key($pdo, $apiKey);
    if (!$key || !has_api_permission($key, 'read:photos')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API key or insufficient permissions']);
        exit;
    }

    $photo = api_get_photo($pdo, $photoId);

    $elapsed = (int)((microtime(true) - $start) * 1000);
    log_api_request($pdo, $key['id'], "/api/v1/photos/$photoId", 'GET', $photo ? 200 : 404, $elapsed);

    if (!$photo) {
        http_response_code(404);
        echo json_encode(['error' => 'Photo not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $photo,
    ]);
}

<?php
/**
 * REST API v1: Photos endpoint.
 * /api/v1/photos - list and retrieve photos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/api.php';

/**
 * Shared by both endpoints below: extract the key from Authorization: Bearer
 * or ?api_key=, validate it, and check the given permission. Writes the
 * error JSON and exits on failure, so callers only need the happy path --
 * this was ~20 lines copy-pasted identically into both functions before,
 * which is how the caller-visible fields (endpoint path, elapsed time) stay
 * the only per-endpoint code now.
 *
 * @return array<string,mixed> the validated key row
 */
function api_authenticate(PDO $pdo, string $permission): array {
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
    if (!$key || !has_api_permission($key, $permission)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API key or insufficient permissions']);
        exit;
    }

    return $key;
}

function api_v1_photos_controller(PDO $pdo): void {
    $start = microtime(true);
    header('Content-Type: application/json');

    $key = api_authenticate($pdo, 'read:photos');

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

    $key = api_authenticate($pdo, 'read:photos');

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

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/cache_headers.php';
require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/event.php';

/**
 * GET /api/photos?event=slug&session=slug&kart=&driver=&class=
 * Returns just the grid HTML fragment for the JS-driven filter bar
 * (docs/architecture.md section 4, step 3). Reuses fetch_gallery_media()
 * from event.php so filtered results never drift from the full page.
 */
function public_api_photos_controller(PDO $pdo, array $config): void {
    $clientIp = get_client_ip();
    if (!check_rate_limit($pdo, 'api_photos', $clientIp, 60, 50)) {
        http_response_code(429);
        echo 'rate limit exceeded';
        return;
    }

    $eventSlug = (string)($_GET['event'] ?? '');
    if ($eventSlug === '') {
        http_response_code(400);
        echo 'event required';
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM events WHERE slug = ? AND is_published = 1');
    $stmt->execute([$eventSlug]);
    $eventId = $stmt->fetchColumn();
    if (!$eventId) {
        http_response_code(404);
        echo 'not found';
        return;
    }
    $eventId = (int)$eventId;

    $sessionId = null;
    $sessionSlug = (string)($_GET['session'] ?? '');
    if ($sessionSlug !== '') {
        $stmt = $pdo->prepare('SELECT id FROM sessions WHERE event_id = ? AND slug = ?');
        $stmt->execute([$eventId, $sessionSlug]);
        $sessionId = $stmt->fetchColumn();
        if (!$sessionId) {
            http_response_code(404);
            echo 'not found';
            return;
        }
        $sessionId = (int)$sessionId;
    }

    $filters = [
        'kart' => trim((string)($_GET['kart'] ?? '')),
        'driver' => trim((string)($_GET['driver'] ?? '')),
        'class' => trim((string)($_GET['class'] ?? '')),
    ];

    $photos = fetch_gallery_media($pdo, $eventId, $sessionId, 'photo', $filters);

    set_cache_headers('short');
    header('Content-Type: text/html; charset=utf-8');
    render(__DIR__ . '/../../views/public/_photo_grid_items.php', compact('photos'));
}

/**
 * POST /api/photos/view {photo_id} — lightbox-open beacon. Queues a
 * background job to increment photos.view_count and stats_daily.photo_views
 * (architecture section 4 step 4). Fire-and-forget from the client; this
 * endpoint returns immediately without waiting for the job to complete, so
 * view increments are async (less blocking). Missed view counts are silent
 * by design, since they're not worth surfacing as errors to the user.
 */
function public_api_photo_view_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    $clientIp = get_client_ip();
    if (!check_rate_limit($pdo, 'api_photo_view', $clientIp, 1, 1)) {
        http_response_code(429);
        echo json_encode(['error' => 'rate limit exceeded']);
        return;
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    $photoId = isset($input['photo_id']) ? (int)$input['photo_id'] : 0;

    if ($photoId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'photo_id required']);
        return;
    }

    $stmt = $pdo->prepare('SELECT event_id FROM photos WHERE id = ? AND status = ?');
    $stmt->execute([$photoId, 'live']);
    $eventId = $stmt->fetchColumn();
    if (!$eventId) {
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
        return;
    }

    // Queue background job instead of blocking on sync update.
    // This keeps the API response fast even under high view traffic.
    $stmt = $pdo->prepare('
        INSERT INTO jobs (type, payload, status, run_after)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->execute([
        'view_count',
        json_encode(['photo_id' => $photoId, 'event_id' => (int)$eventId]),
        'pending',
    ]);

    echo json_encode(['ok' => true]);
}

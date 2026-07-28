<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/event.php';

/**
 * GET /api/photos?event=slug&session=slug&kart=&driver=&class=
 * Returns just the grid HTML fragment for the JS-driven filter bar
 * (docs/architecture.md section 4, step 3). Reuses fetch_gallery_media()
 * from event.php so filtered results never drift from the full page.
 */
function public_api_photos_controller(PDO $pdo, array $config): void {
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

    header('Content-Type: text/html; charset=utf-8');
    render(__DIR__ . '/../../views/public/_photo_grid_items.php', compact('photos'));
}

/**
 * POST /api/photos/view {photo_id} — lightbox-open beacon. Increments
 * photos.view_count and stats_daily.photo_views (architecture section 4
 * step 4). Fire-and-forget from the client; failures here are silent by
 * design, since a missed view count is not worth surfacing as an error.
 */
function public_api_photo_view_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

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

    $pdo->prepare('UPDATE photos SET view_count = view_count + 1 WHERE id = ?')->execute([$photoId]);

    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        INSERT INTO stats_daily (stat_date, event_id, photo_views) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE photo_views = photo_views + 1
    ');
    $stmt->execute([$today, (int)$eventId]);

    echo json_encode(['ok' => true]);
}

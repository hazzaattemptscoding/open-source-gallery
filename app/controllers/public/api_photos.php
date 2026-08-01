<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/cache_headers.php';
require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../lib/validation.php';
require_once __DIR__ . '/../../lib/wishlist.php';
require_once __DIR__ . '/event.php';

/**
 * GET /api/photos?event=slug&session=slug&kart=&class=
 * Returns just the grid HTML fragment for the JS-driven filter bar
 * (docs/architecture.md section 4, step 3). Reuses fetch_gallery_media()
 * from event.php so filtered results never drift from the full page.
 */
function public_api_photos_controller(PDO $pdo, array $config): void {
    $clientIp = get_client_ip();
    if (!check_rate_limit($pdo, 'api_photos', 'ip:' . $clientIp, 60, 50)) {
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
        'class' => trim((string)($_GET['class'] ?? '')),
        'date_start' => trim((string)($_GET['date_start'] ?? '')),
        'date_end' => trim((string)($_GET['date_end'] ?? '')),
    ];

    // Same endpoint serves two different client actions (see event.js): a
    // filter change replaces #photoGrid's contents entirely and always wants
    // page 1, while "Load more" appends the next page of the same filtered
    // set. Both just pass whatever page they want; this endpoint doesn't
    // need to know which case it's in.
    $page = validate_page($_GET['page'] ?? 1);
    $photos = fetch_gallery_media($pdo, $eventId, $sessionId, 'photo', $filters, $page, GALLERY_PAGE_SIZE);
    $totalPhotos = count_gallery_media($pdo, $eventId, $sessionId, 'photo', $filters);
    $hasMorePhotos = ($page * GALLERY_PAGE_SIZE) < $totalPhotos;

    $favoriteToken = $_COOKIE[FAVORITES_COOKIE_NAME] ?? '';
    $favoritedIds = $favoriteToken !== ''
        ? get_wishlisted_photo_ids($pdo, $favoriteToken, array_column($photos, 'id'))
        : [];

    set_cache_headers('short');
    header('Content-Type: text/html; charset=utf-8');
    // Read by event.js to decide whether to keep showing "Load more" or hide
    // it, without needing the fragment response to be JSON (which would
    // complicate the plain-HTML-fragment contract every other caller of this
    // endpoint already relies on).
    header('X-Has-More: ' . ($hasMorePhotos ? '1' : '0'));
    header('X-Total-Photos: ' . $totalPhotos);
    render(__DIR__ . '/../../views/public/_photo_grid_items.php', compact('photos', 'favoritedIds'));
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
    if (!check_rate_limit($pdo, 'api_photo_view', 'ip:' . $clientIp, 1, 1)) {
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
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ');
    $stmt->execute([
        'view_count',
        json_encode(['photo_id' => $photoId, 'event_id' => (int)$eventId]),
        'pending',
    ]);

    echo json_encode(['ok' => true]);
}

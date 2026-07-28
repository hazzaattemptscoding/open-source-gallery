<?php
declare(strict_types=1);

require __DIR__ . '/../../lib/auth.php';
require __DIR__ . '/../../lib/view.php';
require __DIR__ . '/../../lib/audit.php';

function admin_tagging_controller(PDO $pdo, array $config): void {
    require_admin();
    $adminId = current_admin_id();
    $ip = client_ip();

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
    $method = $_SERVER['REQUEST_METHOD'];
    $sessionId = isset($_GET['session']) ? (int)$_GET['session'] : null;

    if ($path === '/admin/photos/tags' && $method === 'GET') {
        show_tagging_ui($pdo, $config, $sessionId);
    } elseif ($path === '/admin/photos/tags/bulk' && $method === 'POST') {
        handle_bulk_tagging($pdo, $config, $adminId, $ip);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
}

function show_tagging_ui(PDO $pdo, array $config, ?int $sessionId): void {
    if (!$sessionId) {
        http_response_code(400);
        echo 'Session ID required.';
        return;
    }

    $stmt = $pdo->prepare('SELECT event_id FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $eventId = (int)$session['event_id'];
    $siteName = $config['site_name'] ?? 'Gallery';

    $stmt = $pdo->prepare('SELECT id, public_token FROM photos WHERE session_id = ? ORDER BY id ASC');
    $stmt->execute([$sessionId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT DISTINCT kart_number, driver_name, class FROM event_entries WHERE event_id = ? ORDER BY kart_number ASC');
    $stmt->execute([$eventId]);
    $eventEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $selectedPhotos = [];
    render('/admin/photos/tags.php', compact('siteName', 'sessionId', 'photos', 'eventEntries', 'selectedPhotos'));
}

function handle_bulk_tagging(PDO $pdo, array $config, int $adminId, string $ip): void {
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        return;
    }

    $photoIds = $input['photo_ids'] ?? [];
    $tags = $input['tags'] ?? [];

    if (!is_array($photoIds) || !is_array($tags)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid format']);
        return;
    }

    if (empty($photoIds) || empty($tags)) {
        http_response_code(400);
        echo json_encode(['error' => 'No photos or tags provided']);
        return;
    }

    $photoIds = array_unique(array_map('intval', $photoIds));

    $successCount = 0;
    foreach ($photoIds as $photoId) {
        $stmt = $pdo->prepare('SELECT id FROM photos WHERE id = ?');
        $stmt->execute([$photoId]);
        if (!$stmt->fetch()) {
            continue;
        }

        foreach ($tags as $tag) {
            $kart = (string)($tag['kart'] ?? '');
            $driver = (string)($tag['driver'] ?? '');
            $class = (string)($tag['class'] ?? '');

            $stmt = $pdo->prepare('
                INSERT INTO photo_tags (photo_id, kart_number, driver_name, class)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$photoId, $kart, $driver, $class]);
            $successCount++;
        }

        audit_log($pdo, $adminId, 'photo_tagged', 'photo', $photoId, ['tag_count' => count($tags)], $ip);
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'count' => $successCount]);
}

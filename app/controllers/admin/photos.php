<?php
declare(strict_types=1);

require __DIR__ . '/../../lib/auth.php';
require __DIR__ . '/../../lib/csrf.php';
require __DIR__ . '/../../lib/view.php';
require __DIR__ . '/../../lib/audit.php';

function admin_photos_controller(PDO $pdo, array $config): void {
    require_admin();
    $adminId = current_admin_id();
    $ip = client_ip();
    $csrfToken = csrf_token();
    $siteName = $config['site_name'] ?? 'Gallery';

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
    $method = $_SERVER['REQUEST_METHOD'];
    $sessionId = isset($_GET['session']) ? (int)$_GET['session'] : null;

    if ($path === '/admin/photos' && $method === 'GET') {
        list_photos($pdo, $siteName, $csrfToken, $sessionId);
    } elseif (preg_match('#^/admin/photos/(\d+)$#', $path, $m) && $method === 'GET') {
        show_photo_details($pdo, $siteName, $csrfToken, photoId: (int)$m[1]);
    } elseif (preg_match('#^/admin/photos/(\d+)/status$#', $path, $m) && $method === 'POST') {
        update_photo_status($pdo, $config, $adminId, $ip, photoId: (int)$m[1]);
    } elseif (preg_match('#^/admin/photos/(\d+)/delete$#', $path, $m) && $method === 'POST') {
        delete_photo($pdo, $config, $adminId, $ip, photoId: (int)$m[1]);
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
}

function list_photos(PDO $pdo, string $siteName, string $csrfToken, ?int $sessionId): void {
    if (!$sessionId) {
        http_response_code(400);
        echo 'Session ID required.';
        return;
    }

    $stmt = $pdo->prepare('SELECT event_id, slug FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $stmt = $pdo->prepare('
        SELECT id, public_token, status, width_height, hires_size_bytes, view_count
        FROM photos
        WHERE session_id = ?
        ORDER BY id ASC
    ');
    $stmt->execute([$sessionId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $eventId = (int)$session['event_id'];
    $sessionSlug = $session['slug'];
    render('/admin/photos/list.php', compact('siteName', 'csrfToken', 'eventId', 'sessionId', 'sessionSlug', 'photos'));
}

function show_photo_details(PDO $pdo, string $siteName, string $csrfToken, int $photoId): void {
    $stmt = $pdo->prepare('SELECT id, public_token, session_id, status, width_height, hires_size_bytes, view_count FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $sessionId = (int)$photo['session_id'];
    render('/admin/photos/details.php', compact('siteName', 'csrfToken', 'photoId', 'sessionId', 'photo'));
}

function update_photo_status(PDO $pdo, array $config, int $adminId, string $ip, int $photoId): void {
    csrf_verify($_POST['csrf_token'] ?? '');

    $status = (string)($_POST['status'] ?? '');
    if (!in_array($status, ['live', 'hidden', 'processing', 'failed'], true)) {
        http_response_code(400);
        echo 'Invalid status.';
        return;
    }

    $stmt = $pdo->prepare('SELECT id, session_id FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $stmt = $pdo->prepare('UPDATE photos SET status = ? WHERE id = ?');
    $stmt->execute([$status, $photoId]);

    audit_log($pdo, $adminId, 'photo_status_updated', 'photo', $photoId, ['status' => $status], $ip);

    $sessionId = (int)$photo['session_id'];
    header("Location: /admin/photos?session={$sessionId}");
    exit;
}

function delete_photo(PDO $pdo, array $config, int $adminId, string $ip, int $photoId): void {
    csrf_verify($_POST['csrf_token'] ?? '');

    $stmt = $pdo->prepare('SELECT id, session_id, public_token FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $sessionId = (int)$photo['session_id'];
    $stmt = $pdo->prepare('SELECT event_id FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    $eventId = (int)$session['event_id'];

    $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$photoId]);
    audit_log($pdo, $adminId, 'photo_deleted', 'photo', $photoId, ['public_token' => $photo['public_token']], $ip);

    header("Location: /admin/photos?session={$sessionId}");
    exit;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_sessions_controller(PDO $pdo, array $config): void {
    require_admin();
    $adminId = current_admin_id();
    $ip = client_ip();
    $csrfToken = csrf_token();
    $siteName = $config['site']['name'] ?? 'Gallery';

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
    $method = $_SERVER['REQUEST_METHOD'];
    $eventId = isset($_GET['event']) ? (int)$_GET['event'] : null;

    if ($path === '/admin/sessions' && $method === 'GET') {
        list_sessions($pdo, $siteName, $csrfToken, $eventId);
    } elseif ($path === '/admin/sessions/new' && $method === 'GET') {
        show_session_form($pdo, $siteName, $csrfToken, $eventId, isNew: true);
    } elseif ($path === '/admin/sessions' && $method === 'POST') {
        create_session($pdo, $adminId, $ip, $siteName, $csrfToken);
    } elseif (preg_match('#^/admin/sessions/(\d+)$#', $path, $m) && $method === 'GET') {
        show_session_form($pdo, $siteName, $csrfToken, $eventId, isNew: false, sessionId: (int)$m[1]);
    } elseif (preg_match('#^/admin/sessions/(\d+)$#', $path, $m) && $method === 'POST') {
        update_session($pdo, $adminId, $ip, $siteName, $csrfToken, sessionId: (int)$m[1]);
    } elseif (preg_match('#^/admin/sessions/(\d+)/delete$#', $path, $m) && $method === 'POST') {
        delete_session($pdo, $adminId, $ip, sessionId: (int)$m[1]);
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
}

function list_sessions(PDO $pdo, string $siteName, string $csrfToken, ?int $eventId): void {
    if (!$eventId) {
        http_response_code(400);
        echo 'Event ID required.';
        return;
    }

    $stmt = $pdo->prepare('SELECT id, slug FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $stmt = $pdo->prepare('
        SELECT s.id, s.slug, s.name, s.sort_order, COUNT(p.id) as photo_count
        FROM sessions s
        LEFT JOIN photos p ON p.session_id = s.id
        WHERE s.event_id = ?
        GROUP BY s.id, s.slug, s.name, s.sort_order
        ORDER BY s.sort_order ASC
    ');
    $stmt->execute([$eventId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $eventSlug = $event['slug'];
    $error = ($_GET['error'] ?? '') === 'has_photos' ? 'Cannot delete: this session still has photos. Delete the photos first.' : '';
    render(__DIR__ . '/../../views/admin/sessions/list.php', compact('siteName', 'csrfToken', 'eventId', 'eventSlug', 'sessions', 'error'));
}

function show_session_form(PDO $pdo, string $siteName, string $csrfToken, ?int $eventId, bool $isNew, ?int $sessionId = null): void {
    $session = [];
    $error = '';

    if ($isNew && !$eventId) {
        http_response_code(400);
        echo 'Event ID required.';
        return;
    }

    if (!$isNew && $sessionId) {
        $stmt = $pdo->prepare('SELECT id, event_id, slug, name, sort_order FROM sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }
        $eventId = (int)$session['event_id'];
    }

    $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    render(__DIR__ . '/../../views/admin/sessions/form.php', compact('siteName', 'csrfToken', 'eventId', 'session', 'error', 'isNew'));
}

function create_session(PDO $pdo, int $adminId, string $ip, string $siteName, string $csrfToken): void {
    csrf_verify($_POST['csrf_token'] ?? '');
    $isNew = true;

    $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $slug = (string)($_POST['slug'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $error = validate_session_slug($pdo, $slug, $eventId);
    if (!$error && empty($name)) {
        $error = 'Session name is required.';
    }

    if ($error) {
        $session = compact('slug', 'name', 'sortOrder');
        render(__DIR__ . '/../../views/admin/sessions/form.php', compact('siteName', 'csrfToken', 'eventId', 'session', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO sessions (event_id, slug, name, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([$eventId, $slug, $name, $sortOrder]);
    $sessionId = (int)$pdo->lastInsertId();

    audit_log($pdo, 'admin', 'session_created', 'session', $sessionId, ['event_id' => $eventId, 'slug' => $slug], $ip);

    header("Location: /admin/sessions/{$sessionId}");
    exit;
}

function update_session(PDO $pdo, int $adminId, string $ip, string $siteName, string $csrfToken, int $sessionId): void {
    csrf_verify($_POST['csrf_token'] ?? '');
    $isNew = false;

    $stmt = $pdo->prepare('SELECT event_id, slug FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $eventId = (int)$current['event_id'];
    $slug = (string)($_POST['slug'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    $error = '';
    if ($slug !== $current['slug']) {
        $error = validate_session_slug($pdo, $slug, $eventId);
    }
    if (!$error && empty($name)) {
        $error = 'Session name is required.';
    }

    if ($error) {
        $session = compact('slug', 'name', 'sortOrder');
        $session['id'] = $sessionId;
        $session['event_id'] = $eventId;
        render(__DIR__ . '/../../views/admin/sessions/form.php', compact('siteName', 'csrfToken', 'eventId', 'session', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('UPDATE sessions SET slug = ?, name = ?, sort_order = ? WHERE id = ?');
    $stmt->execute([$slug, $name, $sortOrder, $sessionId]);

    audit_log($pdo, 'admin', 'session_updated', 'session', $sessionId, ['slug' => $slug], $ip);

    header("Location: /admin/sessions/{$sessionId}");
    exit;
}

function delete_session(PDO $pdo, int $adminId, string $ip, int $sessionId): void {
    csrf_verify($_POST['csrf_token'] ?? '');

    $stmt = $pdo->prepare('SELECT event_id FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $eventId = (int)$row['event_id'];

    try {
        $pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$sessionId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            header("Location: /admin/sessions?event={$eventId}&error=has_photos");
            exit;
        }
        throw $e;
    }

    audit_log($pdo, 'admin', 'session_deleted', 'session', $sessionId, [], $ip);

    header("Location: /admin/sessions?event={$eventId}");
    exit;
}

function validate_session_slug(PDO $pdo, string $slug, int $eventId): string {
    if (empty($slug)) {
        return 'Slug is required.';
    }
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        return 'Slug must be lowercase letters, numbers, and hyphens only.';
    }
    $stmt = $pdo->prepare('SELECT id FROM sessions WHERE event_id = ? AND slug = ?');
    $stmt->execute([$eventId, $slug]);
    if ($stmt->fetch()) {
        return 'This slug already exists in this event.';
    }
    return '';
}

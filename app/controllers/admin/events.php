<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/currency.php';

function admin_events_controller(PDO $pdo, array $config): void {
    require_admin();
    $adminId = current_admin_id();
    $ip = client_ip();
    $csrfToken = csrf_token();
    $siteName = $config['site']['name'] ?? 'Gallery';
    $currencyCode = $config['currency'] ?? 'GBP';

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
    $method = $_SERVER['REQUEST_METHOD'];

    if ($path === '/admin/events' && $method === 'GET') {
        list_events($pdo, $siteName, $currencyCode, $csrfToken);
    } elseif ($path === '/admin/events/new' && $method === 'GET') {
        show_event_form($pdo, $siteName, $currencyCode, $csrfToken, isNew: true);
    } elseif ($path === '/admin/events' && $method === 'POST') {
        create_event($pdo, $adminId, $ip, $siteName, $currencyCode, $csrfToken);
    } elseif (preg_match('#^/admin/events/(\d+)$#', $path, $m) && $method === 'GET') {
        show_event_form($pdo, $siteName, $currencyCode, $csrfToken, isNew: false, eventId: (int)$m[1]);
    } elseif (preg_match('#^/admin/events/(\d+)$#', $path, $m) && $method === 'POST') {
        update_event($pdo, $adminId, $ip, $siteName, $currencyCode, $csrfToken, eventId: (int)$m[1]);
    } elseif (preg_match('#^/admin/events/(\d+)/delete$#', $path, $m) && $method === 'POST') {
        delete_event($pdo, $adminId, $ip, eventId: (int)$m[1]);
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
}

function list_events(PDO $pdo, string $siteName, string $currencyCode, string $csrfToken): void {
    $stmt = $pdo->prepare('SELECT id, slug, title, venue, event_date, is_published, price_single_pence, price_session_pence, price_event_pence FROM events ORDER BY event_date DESC');
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $error = ($_GET['error'] ?? '') === 'has_sessions' ? 'Cannot delete: this event still has sessions. Delete the sessions first.' : '';
    render(__DIR__ . '/../../views/admin/events/list.php', compact('siteName', 'currencyCode', 'csrfToken', 'events', 'error'));
}

function show_event_form(PDO $pdo, string $siteName, string $currencyCode, string $csrfToken, bool $isNew, ?int $eventId = null): void {
    $event = [];
    $error = '';

    if (!$isNew && $eventId) {
        $stmt = $pdo->prepare('SELECT id, slug, title, venue, event_date, is_published, price_single_pence, price_session_pence, price_event_pence FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }
    } else {
        $event['price_single_pence'] = default_single_price($pdo);
    }

    render(__DIR__ . '/../../views/admin/events/form.php', compact('siteName', 'currencyCode', 'csrfToken', 'event', 'error', 'isNew'));
}

function create_event(PDO $pdo, int $adminId, string $ip, string $siteName, string $currencyCode, string $csrfToken): void {
    csrf_verify($_POST['csrf_token'] ?? '');
    $isNew = true;

    $slug = (string)($_POST['slug'] ?? '');
    $title = trim((string)($_POST['title'] ?? ''));
    $venue = trim((string)($_POST['venue'] ?? ''));
    $eventDate = (string)($_POST['event_date'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $priceSingle = !empty($_POST['price_single_pence']) ? (int)$_POST['price_single_pence'] : default_single_price($pdo);
    $priceSession = !empty($_POST['price_session_pence']) ? (int)$_POST['price_session_pence'] : null;
    $priceEvent = !empty($_POST['price_event_pence']) ? (int)$_POST['price_event_pence'] : null;

    $error = validate_event_slug($pdo, $slug);
    if (!$error && empty($title)) {
        $error = 'Title is required.';
    }
    if (!$error && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $error = 'Invalid date format.';
    }
    if (!$error && $priceSingle < 0) {
        $error = 'Single photo price cannot be negative.';
    }

    if ($error) {
        $event = compact('slug', 'title', 'venue', 'eventDate', 'isPublished', 'priceSingle', 'priceSession', 'priceEvent');
        render(__DIR__ . '/../../views/admin/events/form.php', compact('siteName', 'currencyCode', 'csrfToken', 'event', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO events (slug, title, venue, event_date, is_published, price_single_pence, price_session_pence, price_event_pence) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$slug, $title, $venue, $eventDate, $isPublished, $priceSingle, $priceSession, $priceEvent]);
    $eventId = (int)$pdo->lastInsertId();

    audit_log($pdo, 'admin', 'event_created', 'event', $eventId, ['slug' => $slug], $ip);

    header("Location: /admin/events/{$eventId}");
    exit;
}

function update_event(PDO $pdo, int $adminId, string $ip, string $siteName, string $currencyCode, string $csrfToken, int $eventId): void {
    csrf_verify($_POST['csrf_token'] ?? '');
    $isNew = false;

    $stmt = $pdo->prepare('SELECT slug FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $slug = (string)($_POST['slug'] ?? '');
    $title = trim((string)($_POST['title'] ?? ''));
    $venue = trim((string)($_POST['venue'] ?? ''));
    $eventDate = (string)($_POST['event_date'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $priceSingle = !empty($_POST['price_single_pence']) ? (int)$_POST['price_single_pence'] : default_single_price($pdo);
    $priceSession = !empty($_POST['price_session_pence']) ? (int)$_POST['price_session_pence'] : null;
    $priceEvent = !empty($_POST['price_event_pence']) ? (int)$_POST['price_event_pence'] : null;

    $error = '';
    if ($slug !== $current['slug']) {
        $error = validate_event_slug($pdo, $slug);
    }
    if (!$error && empty($title)) {
        $error = 'Title is required.';
    }
    if (!$error && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $error = 'Invalid date format.';
    }

    if ($error) {
        $event = compact('slug', 'title', 'venue', 'eventDate', 'isPublished', 'priceSingle', 'priceSession', 'priceEvent');
        $event['id'] = $eventId;
        render(__DIR__ . '/../../views/admin/events/form.php', compact('siteName', 'currencyCode', 'csrfToken', 'event', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('UPDATE events SET slug = ?, title = ?, venue = ?, event_date = ?, is_published = ?, price_single_pence = ?, price_session_pence = ?, price_event_pence = ? WHERE id = ?');
    $stmt->execute([$slug, $title, $venue, $eventDate, $isPublished, $priceSingle, $priceSession, $priceEvent, $eventId]);

    audit_log($pdo, 'admin', 'event_updated', 'event', $eventId, ['slug' => $slug], $ip);

    header("Location: /admin/events/{$eventId}");
    exit;
}

function delete_event(PDO $pdo, int $adminId, string $ip, int $eventId): void {
    csrf_verify($_POST['csrf_token'] ?? '');

    $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    try {
        $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$eventId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            header('Location: /admin/events?error=has_sessions');
            exit;
        }
        throw $e;
    }

    audit_log($pdo, 'admin', 'event_deleted', 'event', $eventId, [], $ip);

    header('Location: /admin/events');
    exit;
}

function validate_event_slug(PDO $pdo, string $slug): string {
    if (empty($slug)) {
        return 'Slug is required.';
    }
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        return 'Slug must be lowercase letters, numbers, and hyphens only.';
    }
    $stmt = $pdo->prepare('SELECT id FROM events WHERE slug = ?');
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        return 'This slug already exists.';
    }
    return '';
}

function default_single_price(PDO $pdo): int {
    $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $stmt->execute(['default_price_single_pence']);
    $value = $stmt->fetchColumn();
    return $value !== false ? (int)$value : 0;
}

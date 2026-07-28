<?php
declare(strict_types=1);

require __DIR__ . '/../../lib/auth.php';
require __DIR__ . '/../../lib/csrf.php';
require __DIR__ . '/../../lib/view.php';
require __DIR__ . '/../../lib/audit.php';

function admin_events_controller(PDO $pdo, array $config): void {
    require_admin();
    $adminId = current_admin_id();
    $ip = client_ip();
    $csrfToken = csrf_token();
    $siteName = $config['site_name'] ?? 'Gallery';
    $currencySymbol = $config['currency_symbol'] ?? '£';
    $currencyName = $config['currency_name'] ?? 'GBP';

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
    $method = $_SERVER['REQUEST_METHOD'];

    if ($path === '/admin/events' && $method === 'GET') {
        list_events($pdo, $siteName, $currencySymbol, $currencyName, $csrfToken);
    } elseif ($path === '/admin/events/new' && $method === 'GET') {
        show_event_form($pdo, $siteName, $currencySymbol, $currencyName, $csrfToken, isNew: true);
    } elseif ($path === '/admin/events' && $method === 'POST') {
        create_event($pdo, $config, $adminId, $ip, $siteName, $currencySymbol, $currencyName, $csrfToken);
    } elseif (preg_match('#^/admin/events/(\d+)$#', $path, $m) && $method === 'GET') {
        show_event_form($pdo, $siteName, $currencySymbol, $currencyName, $csrfToken, isNew: false, eventId: (int)$m[1]);
    } elseif (preg_match('#^/admin/events/(\d+)$#', $path, $m) && $method === 'POST') {
        update_event($pdo, $config, $adminId, $ip, $siteName, $currencySymbol, $currencyName, $csrfToken, eventId: (int)$m[1]);
    } elseif (preg_match('#^/admin/events/(\d+)/delete$#', $path, $m) && $method === 'POST') {
        delete_event($pdo, $config, $adminId, $ip, eventId: (int)$m[1]);
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
}

function list_events(PDO $pdo, string $siteName, string $currencySymbol, string $currencyName, string $csrfToken): void {
    $stmt = $pdo->prepare('SELECT id, slug, event_date, is_published, price_single_pence, price_session_pence, price_event_pence FROM events ORDER BY event_date DESC');
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    render('/admin/events/list.php', compact('siteName', 'currencySymbol', 'currencyName', 'csrfToken', 'events'));
}

function show_event_form(PDO $pdo, string $siteName, string $currencySymbol, string $currencyName, string $csrfToken, bool $isNew, ?int $eventId = null): void {
    $event = [];
    $error = '';

    if (!$isNew && $eventId) {
        $stmt = $pdo->prepare('SELECT id, slug, event_date, is_published, price_single_pence, price_session_pence, price_event_pence FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }
    }

    render('/admin/events/form.php', compact('siteName', 'currencySymbol', 'currencyName', 'csrfToken', 'event', 'error', 'isNew'));
}

function create_event(PDO $pdo, array $config, int $adminId, string $ip, string $siteName, string $currencySymbol, string $currencyName, string $csrfToken): void {
    csrf_verify($_POST['csrf_token'] ?? '');

    $slug = (string)($_POST['slug'] ?? '');
    $eventDate = (string)($_POST['event_date'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $priceSingle = !empty($_POST['price_single_pence']) ? (int)$_POST['price_single_pence'] : null;
    $priceSession = !empty($_POST['price_session_pence']) ? (int)$_POST['price_session_pence'] : null;
    $priceEvent = !empty($_POST['price_event_pence']) ? (int)$_POST['price_event_pence'] : null;

    $error = validate_event_slug($pdo, $slug);
    if (!$error && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $error = 'Invalid date format.';
    }
    if (!$error && ($priceSingle !== null && $priceSingle < 0)) {
        $error = 'Single photo price cannot be negative.';
    }

    if ($error) {
        render('/admin/events/form.php', compact('siteName', 'currencySymbol', 'currencyName', 'csrfToken', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO events (slug, event_date, is_published, price_single_pence, price_session_pence, price_event_pence) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$slug, $eventDate, $isPublished, $priceSingle, $priceSession, $priceEvent]);
    $eventId = (int)$pdo->lastInsertId();

    audit_log($pdo, $adminId, 'event_created', 'event', $eventId, ['slug' => $slug], $ip);

    header("Location: /admin/events/{$eventId}");
    exit;
}

function update_event(PDO $pdo, array $config, int $adminId, string $ip, string $siteName, string $currencySymbol, string $currencyName, string $csrfToken, int $eventId): void {
    csrf_verify($_POST['csrf_token'] ?? '');

    $stmt = $pdo->prepare('SELECT slug FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $slug = (string)($_POST['slug'] ?? '');
    $eventDate = (string)($_POST['event_date'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $priceSingle = !empty($_POST['price_single_pence']) ? (int)$_POST['price_single_pence'] : null;
    $priceSession = !empty($_POST['price_session_pence']) ? (int)$_POST['price_session_pence'] : null;
    $priceEvent = !empty($_POST['price_event_pence']) ? (int)$_POST['price_event_pence'] : null;

    $error = '';
    if ($slug !== $current['slug']) {
        $error = validate_event_slug($pdo, $slug);
    }
    if (!$error && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $error = 'Invalid date format.';
    }

    if ($error) {
        $event = compact('slug', 'eventDate', 'isPublished', 'priceSingle', 'priceSession', 'priceEvent');
        $event['id'] = $eventId;
        render('/admin/events/form.php', compact('siteName', 'currencySymbol', 'currencyName', 'csrfToken', 'event', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('UPDATE events SET slug = ?, event_date = ?, is_published = ?, price_single_pence = ?, price_session_pence = ?, price_event_pence = ? WHERE id = ?');
    $stmt->execute([$slug, $eventDate, $isPublished, $priceSingle, $priceSession, $priceEvent, $eventId]);

    audit_log($pdo, $adminId, 'event_updated', 'event', $eventId, ['slug' => $slug], $ip);

    header("Location: /admin/events/{$eventId}");
    exit;
}

function delete_event(PDO $pdo, array $config, int $adminId, string $ip, int $eventId): void {
    csrf_verify($_POST['csrf_token'] ?? '');

    $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$eventId]);
    audit_log($pdo, $adminId, 'event_deleted', 'event', $eventId, [], $ip);

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

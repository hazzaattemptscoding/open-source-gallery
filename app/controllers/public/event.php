<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/cart.php';
require_once __DIR__ . '/../../lib/cache_headers.php';
require_once __DIR__ . '/../../lib/db_compat.php';

/**
 * Event page, matching both /e/{event-slug} (full-event grid across all
 * sessions) and /e/{event-slug}/{session-slug} (one session's grid).
 * Both render the same template — the only difference is whether the
 * photo/video queries are constrained to one session_id.
 *
 * Filters (?kart=&class=) live in the query string so the URL is
 * always a shareable deep link (docs/architecture.md section 4): the
 * server renders the fully-filtered grid for any URL, JS is progressive
 * enhancement on top via /api/photos.
 */
function public_event_controller(PDO $pdo, array $config, string $eventSlug, ?string $sessionSlug = null): void {
    $siteName = $config['site']['name'] ?? 'Gallery';
    $currencyCode = $config['currency'] ?? 'GBP';

    $stmt = $pdo->prepare('
        SELECT id, slug, title, venue, event_date, cover_photo_id,
               price_single_pence, price_session_pence, price_event_pence
        FROM events
        WHERE slug = ? AND is_published = 1
    ');
    $stmt->execute([$eventSlug]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        http_response_code(404);
        require __DIR__ . '/../../views/public/404.php';
        return;
    }
    $eventId = (int)$event['id'];

    set_cache_headers('short');

    $stmt = $pdo->prepare('SELECT id, slug, name, sort_order FROM sessions WHERE event_id = ? ORDER BY sort_order ASC');
    $stmt->execute([$eventId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activeSession = null;
    $sessionId = null;
    if ($sessionSlug !== null) {
        foreach ($sessions as $session) {
            if ($session['slug'] === $sessionSlug) {
                $activeSession = $session;
                $sessionId = (int)$session['id'];
                break;
            }
        }
        if ($activeSession === null) {
            http_response_code(404);
            require __DIR__ . '/../../views/public/404.php';
            return;
        }
    }

    $filters = [
        'kart' => trim((string)($_GET['kart'] ?? '')),
        'class' => trim((string)($_GET['class'] ?? '')),
        'date_start' => trim((string)($_GET['date_start'] ?? '')),
        'date_end' => trim((string)($_GET['date_end'] ?? '')),
    ];

    $heroToken = null;
    if ($event['cover_photo_id']) {
        $stmt = $pdo->prepare('SELECT public_token FROM photos WHERE id = ? AND status = ?');
        $stmt->execute([$event['cover_photo_id'], 'live']);
        $heroToken = $stmt->fetchColumn() ?: null;
    }
    if (!$heroToken) {
        $stmt = $pdo->prepare('SELECT public_token FROM photos WHERE event_id = ? AND status = ? AND media_type = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$eventId, 'live', 'photo']);
        $heroToken = $stmt->fetchColumn() ?: null;
    }

    $photos = fetch_gallery_media($pdo, $eventId, $sessionId, 'photo', $filters);
    $videos = fetch_gallery_media($pdo, $eventId, $sessionId, 'video', $filters);

    $stmt = $pdo->prepare('SELECT DISTINCT kart_number FROM event_entries WHERE event_id = ? AND kart_number <> \'\' ORDER BY kart_number ASC');
    $stmt->execute([$eventId]);
    $kartOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // No driver-name option list: event_entries holds real names (often of
    // minors) and this list would publish every one of them to an
    // unauthenticated page. Kart number is the public discovery key.
    $stmt = $pdo->prepare('SELECT DISTINCT class FROM event_entries WHERE event_id = ? AND class <> \'\' ORDER BY class ASC');
    $stmt->execute([$eventId]);
    $classOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $basePath = $activeSession
        ? "/e/{$event['slug']}/{$activeSession['slug']}"
        : "/e/{$event['slug']}";

    $cartCount = count(cart_get($config));

    // Best-effort view counter; a failed UPDATE here shouldn't break the page.
    try {
        $today = date('Y-m-d');
        if (db_supports_on_duplicate_key($pdo)) {
            $stmt = $pdo->prepare('
                INSERT INTO stats_daily (stat_date, event_id, gallery_views) VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE gallery_views = gallery_views + 1
            ');
            $stmt->execute([$today, $eventId]);
        } else {
            $stmt = $pdo->prepare('UPDATE stats_daily SET gallery_views = gallery_views + 1 WHERE stat_date = ? AND event_id = ?');
            $stmt->execute([$today, $eventId]);
            if ($stmt->rowCount() === 0) {
                $pdo->prepare('INSERT INTO stats_daily (stat_date, event_id, gallery_views) VALUES (?, ?, 1)')
                    ->execute([$today, $eventId]);
            }
        }
    } catch (Throwable $e) {
        // Stats are non-critical; ignore.
    }

    render(__DIR__ . '/../../views/public/event.php', compact(
        'siteName', 'currencyCode', 'event', 'sessions', 'activeSession', 'sessionId',
        'filters', 'heroToken', 'photos', 'videos', 'kartOptions', 'classOptions',
        'basePath', 'cartCount'
    ));
}

/**
 * Shared by the full page render and /api/photos so the two can never
 * return different results for the same filters. Filters on kart/class
 * (photo_tags, motorsport-specific) and created_at date range.
 *
 * Driver name is deliberately absent from both the filter set and the
 * selected columns. Drivers are frequently minors, so their names must
 * never reach a public surface, and a driver filter would also let anyone
 * probe whether a named child appears in a gallery.
 *
 * @return list<array<string, mixed>>
 */
function fetch_gallery_media(PDO $pdo, int $eventId, ?int $sessionId, string $mediaType, array $filters): array {
    $where = ['p.event_id = ?', 'p.status = ?', 'p.media_type = ?'];
    $params = [$eventId, 'live', $mediaType];

    if ($sessionId !== null) {
        $where[] = 'p.session_id = ?';
        $params[] = $sessionId;
    }

    $join = 'LEFT JOIN photo_tags pt ON pt.photo_id = p.id';

    if ($filters['kart'] !== '') {
        $where[] = 'pt.kart_number = ?';
        $params[] = $filters['kart'];
    }
    if ($filters['class'] !== '') {
        $where[] = 'pt.class = ?';
        $params[] = $filters['class'];
    }

    if (!empty($filters['date_start'])) {
        $where[] = 'DATE(p.created_at) >= ?';
        $params[] = $filters['date_start'];
    }
    if (!empty($filters['date_end'])) {
        $where[] = 'DATE(p.created_at) <= ?';
        $params[] = $filters['date_end'];
    }

    $select = "SELECT DISTINCT p.id, p.public_token, p.width, p.height, p.sort_order,
               GROUP_CONCAT(pt.kart_number) as kart_tags,
               GROUP_CONCAT(pt.class) as class_tags";

    $sql = "{$select}
        FROM photos p
        {$join}
        WHERE " . implode(' AND ', $where) . "
        GROUP BY p.id
        ORDER BY p.sort_order ASC, p.id ASC
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

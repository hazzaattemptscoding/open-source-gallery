<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/cart.php';
require_once __DIR__ . '/../../lib/cache_headers.php';
require_once __DIR__ . '/../../lib/db_compat.php';
require_once __DIR__ . '/../../lib/validation.php';
require_once __DIR__ . '/../../lib/wishlist.php';

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

    // ?page= is the no-JS fallback and what keeps every photo crawlable and
    // linkable, per docs/architecture.md section 4's URL-as-state rule
    // already followed for filters above. The "Load more" button appends
    // page+1 via /api/photos without a navigation.
    $page = validate_page($_GET['page'] ?? 1);

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

    $photos = fetch_gallery_media($pdo, $eventId, $sessionId, 'photo', $filters, $page, GALLERY_PAGE_SIZE);
    $totalPhotos = count_gallery_media($pdo, $eventId, $sessionId, 'photo', $filters);
    $hasMorePhotos = ($page * GALLERY_PAGE_SIZE) < $totalPhotos;

    // Videos are a separate section with their own load order (CLAUDE.md:
    // "Videos live in their own section, never mixed into the photo grid's
    // load order"), and typically far fewer per event than photos, so they
    // keep a single generous page rather than the same load-more UI.
    $videos = fetch_gallery_media($pdo, $eventId, $sessionId, 'video', $filters, 1, 500);

    // Only looks up an existing cookie -- never mints one just to render a
    // page, which would set a cookie on every visitor regardless of whether
    // they ever touch favourites.
    $favoriteToken = $_COOKIE[FAVORITES_COOKIE_NAME] ?? '';
    $favoritedIds = $favoriteToken !== ''
        ? get_wishlisted_photo_ids($pdo, $favoriteToken, array_column($photos, 'id'))
        : [];

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

    /*
     * The buying options for this page, widest bundle first.
     *
     * Bundles have been supported by the cart, pricing, order_items and
     * checkout since the initial schema, but nothing ever rendered a way to buy
     * one, so the only reachable product was a single photo. Failing softly
     * here rather than fataling: if pricing cannot be worked out for some
     * reason, the gallery should still render and photos should still be
     * individually buyable.
     */
    $purchaseOptions = [];
    try {
        require_once __DIR__ . '/../../lib/purchase_options.php';
        $purchaseOptions = build_purchase_options($pdo, $config, $event, $activeSession, $currencyCode);
    } catch (Throwable $e) {
        error_log('purchase options failed for event ' . $eventId . ': ' . $e->getMessage());
    }

    render(__DIR__ . '/../../views/public/event.php', compact(
        'siteName', 'currencyCode', 'event', 'sessions', 'activeSession', 'sessionId',
        'filters', 'heroToken', 'photos', 'videos', 'kartOptions', 'classOptions',
        'basePath', 'cartCount', 'page', 'totalPhotos', 'hasMorePhotos', 'favoritedIds',
        'purchaseOptions'
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
/**
 * Shared by fetch_gallery_media() and count_gallery_media() so a filter
 * added to one can't silently stop applying to the other -- the previous
 * shape had this WHERE-building duplicated nowhere yet, but pagination
 * needs a count alongside the page of results, and copy-pasting the filter
 * logic into a second function is exactly how the settings_registry/
 * `settings` table split happened in the first place.
 *
 * @return array{0: string, 1: list<mixed>} [$join, $where SQL, is folded into index 1's trailing element]
 */
function build_gallery_media_where(int $eventId, ?int $sessionId, string $mediaType, array $filters): array
{
    $where = ['p.event_id = ?', 'p.status = ?', 'p.media_type = ?'];
    $params = [$eventId, 'live', $mediaType];

    if ($sessionId !== null) {
        $where[] = 'p.session_id = ?';
        $params[] = $sessionId;
    }

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

    return [implode(' AND ', $where), $params];
}

/**
 * Default page size for a gallery grid. Deliberately not
 * search.results_per_page: that setting governs the /search results list,
 * a different UI with different reading density, and coupling the two would
 * make an admin's search-page preference silently resize their event
 * galleries too.
 */
const GALLERY_PAGE_SIZE = 60;

function fetch_gallery_media(PDO $pdo, int $eventId, ?int $sessionId, string $mediaType, array $filters, int $page = 1, int $perPage = GALLERY_PAGE_SIZE): array {
    [$whereSql, $params] = build_gallery_media_where($eventId, $sessionId, $mediaType, $filters);
    $join = 'LEFT JOIN photo_tags pt ON pt.photo_id = p.id';

    $offset = max(0, ($page - 1) * $perPage);

    $sql = "SELECT DISTINCT p.id, p.public_token, p.width, p.height, p.sort_order,
               GROUP_CONCAT(pt.kart_number) as kart_tags,
               GROUP_CONCAT(pt.class) as class_tags
        FROM photos p
        {$join}
        WHERE {$whereSql}
        GROUP BY p.id
        ORDER BY p.sort_order ASC, p.id ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$params, $perPage, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Total media matching the same filters fetch_gallery_media() would use, for pagination controls. */
function count_gallery_media(PDO $pdo, int $eventId, ?int $sessionId, string $mediaType, array $filters): int
{
    [$whereSql, $params] = build_gallery_media_where($eventId, $sessionId, $mediaType, $filters);
    $join = 'LEFT JOIN photo_tags pt ON pt.photo_id = p.id';

    $sql = "SELECT COUNT(DISTINCT p.id) FROM photos p {$join} WHERE {$whereSql}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

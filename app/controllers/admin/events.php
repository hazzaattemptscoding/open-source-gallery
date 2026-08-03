<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/cache.php';
require_once __DIR__ . '/../../lib/validation.php';
require_once __DIR__ . '/../../lib/entries_import.php';

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
    } elseif (preg_match('#^/admin/events/(\d+)/entries$#', $path, $m) && $method === 'POST') {
        import_event_entries($pdo, $adminId, $ip, eventId: (int)$m[1]);
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
    $pageTitle = 'Events';
    $currentPage = 'events';
    render(__DIR__ . '/../../views/admin/events/list.php', compact('pageTitle', 'currentPage', 'siteName', 'currencyCode', 'csrfToken', 'events', 'error'));
}

function show_event_form(PDO $pdo, string $siteName, string $currencyCode, string $csrfToken, bool $isNew, ?int $eventId = null): void {
    $event = [];
    $error = '';
    $entries = [];

    if (!$isNew && $eventId) {
        $stmt = $pdo->prepare('SELECT id, slug, title, venue, event_date, is_published, price_single_pence, price_session_pence, price_event_pence FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        // LENGTH then value sorts kart numbers naturally (2 before 10) without
        // a CAST, whose spelling differs between MySQL and SQLite.
        $stmt = $pdo->prepare('SELECT kart_number, driver_name, class FROM event_entries WHERE event_id = ? ORDER BY LENGTH(kart_number), kart_number, class');
        $stmt->execute([$eventId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $event['price_single_pence'] = default_single_price($pdo);
    }

    // Import feedback comes back through the query string so a refresh cannot
    // repeat the import.
    $entriesOk = isset($_GET['entries_ok']) ? (int)$_GET['entries_ok'] : null;
    $entriesNotes = trim((string)($_GET['entries_notes'] ?? ''));
    $entriesError = trim((string)($_GET['entries_error'] ?? ''));

    $pageTitle = 'Events';
    $currentPage = 'events';
    render(__DIR__ . '/../../views/admin/events/form.php', compact('pageTitle', 'currentPage', 'siteName', 'currencyCode', 'csrfToken', 'event', 'error', 'isNew', 'entries', 'entriesOk', 'entriesNotes', 'entriesError'));
}

function create_event(PDO $pdo, int $adminId, string $ip, string $siteName, string $currencyCode, string $csrfToken): void {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'CSRF verification failed.';
        return;
    }
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
    if (!$error && !validate_iso_date($eventDate)) {
        $error = 'Invalid event date.';
    }
    if (!$error && $priceSingle < 0) {
        $error = 'Single photo price cannot be negative.';
    }

    if ($error) {
        $event = compact('slug', 'title', 'venue', 'eventDate', 'isPublished', 'priceSingle', 'priceSession', 'priceEvent');
        $pageTitle = 'Events';
        $currentPage = 'events';
        render(__DIR__ . '/../../views/admin/events/form.php', compact('pageTitle', 'currentPage', 'siteName', 'currencyCode', 'csrfToken', 'event', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO events (slug, title, venue, event_date, is_published, price_single_pence, price_session_pence, price_event_pence) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$slug, $title, $venue, $eventDate, $isPublished, $priceSingle, $priceSession, $priceEvent]);
    $eventId = (int)$pdo->lastInsertId();

    cache_invalidate_all();
    audit_log($pdo, 'admin', 'event_created', 'event', $eventId, ['slug' => $slug], $ip);

    header("Location: /admin/events/{$eventId}");
    exit;
}

function update_event(PDO $pdo, int $adminId, string $ip, string $siteName, string $currencyCode, string $csrfToken, int $eventId): void {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'CSRF verification failed.';
        return;
    }
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
    if (!$error && !validate_iso_date($eventDate)) {
        $error = 'Invalid event date.';
    }

    if ($error) {
        $event = compact('slug', 'title', 'venue', 'eventDate', 'isPublished', 'priceSingle', 'priceSession', 'priceEvent');
        $event['id'] = $eventId;
        $pageTitle = 'Events';
        $currentPage = 'events';
        render(__DIR__ . '/../../views/admin/events/form.php', compact('pageTitle', 'currentPage', 'siteName', 'currencyCode', 'csrfToken', 'event', 'error', 'isNew'));
        return;
    }

    $stmt = $pdo->prepare('UPDATE events SET slug = ?, title = ?, venue = ?, event_date = ?, is_published = ?, price_single_pence = ?, price_session_pence = ?, price_event_pence = ? WHERE id = ?');
    $stmt->execute([$slug, $title, $venue, $eventDate, $isPublished, $priceSingle, $priceSession, $priceEvent, $eventId]);

    /*
     * Stamp published_at the first time an event actually goes live.
     *
     * Only on the unpublished-to-published transition, and only when it is not
     * already set. Re-stamping on every save would let an admin fixing a typo
     * on a month-old gallery make it look freshly published, and the
     * gallery-live campaign would then announce it a second time to everyone.
     *
     * Leaving it in place when an event is unpublished and republished is
     * deliberate for the same reason: the gallery was already announced, and
     * toggling the checkbox twice should not send it again.
     */
    if ($isPublished === 1 && (int)($current['is_published'] ?? 0) === 0) {
        try {
            $pdo->prepare('UPDATE events SET published_at = CURRENT_TIMESTAMP WHERE id = ? AND published_at IS NULL')
                ->execute([$eventId]);
        } catch (Throwable $e) {
            error_log('could not stamp published_at for event ' . $eventId . ': ' . $e->getMessage());
        }
    }

    cache_invalidate_all();
    audit_log($pdo, 'admin', 'event_updated', 'event', $eventId, ['slug' => $slug], $ip);

    header("Location: /admin/events/{$eventId}");
    exit;
}

/**
 * POST /admin/events/{id}/entries — import the organiser's entry list.
 *
 * Takes an uploaded .csv or text pasted into the textarea, whichever is
 * present, so a photographer can paste a few rows without saving a file
 * first. Redirects back to the event form with a summary in the query
 * string rather than rendering here, so a refresh cannot re-run the import.
 */
function import_event_entries(PDO $pdo, int $adminId, string $ip, int $eventId): void {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'CSRF verification failed.';
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo '404 Not Found';
        return;
    }

    $csv = '';
    if (!empty($_FILES['entries_file']['tmp_name']) && is_uploaded_file($_FILES['entries_file']['tmp_name'])) {
        $csv = (string)file_get_contents($_FILES['entries_file']['tmp_name']);
    } elseif (trim((string)($_POST['entries_csv'] ?? '')) !== '') {
        $csv = (string)$_POST['entries_csv'];
    } elseif (trim((string)($_POST['entries_url'] ?? '')) !== '') {
        /*
         * Import straight from a published URL, so an organiser's entry list
         * can be pulled in without downloading and re-uploading it.
         *
         * Everything about safety lives in remote_fetch(): this is a
         * server-side request to an address the admin typed, which is the
         * shape of an SSRF hole. See app/lib/remote_fetch.php for why each
         * guard is there, in particular that redirects are followed manually
         * and re-validated, since curl's own following would check only the
         * first URL.
         */
        require_once __DIR__ . '/../../lib/remote_fetch.php';

        $fetched = remote_fetch(trim((string)$_POST['entries_url']));

        if (!$fetched['ok']) {
            audit_log($pdo, 'admin', 'event_entries_url_refused', 'event', $eventId, [
                'url' => substr((string)$_POST['entries_url'], 0, 200),
                'reason' => $fetched['error'],
            ], $ip);
            header("Location: /admin/events/{$eventId}?entries_error=" . urlencode($fetched['error']));
            exit;
        }

        $csv = $fetched['body'];

        /*
         * Only CSV-shaped documents are accepted. An HTML page fetched from a
         * timing site would be parsed as CSV and produce a screenful of
         * nonsense rows, so this fails with an explanation instead. Parsing
         * arbitrary HTML entry lists needs a parser written against the real
         * markup of each provider, which is a separate piece of work.
         */
        if (stripos($fetched['content_type'], 'html') !== false || stripos(ltrim($csv), '<!doctype') === 0 || stripos(ltrim($csv), '<html') === 0) {
            header("Location: /admin/events/{$eventId}?entries_error=" . urlencode(
                'That URL returned a web page, not a CSV file. Use a direct link to a .csv export.'
            ));
            exit;
        }

        audit_log($pdo, 'admin', 'event_entries_url_fetched', 'event', $eventId, [
            'url' => $fetched['final_url'],
            'bytes' => strlen($csv),
        ], $ip);
    }

    if (trim($csv) === '') {
        header("Location: /admin/events/{$eventId}?entries_error=" . urlencode('Paste some rows, choose a CSV file, or give a CSV URL.'));
        exit;
    }

    $parsed = parse_event_entries_csv($csv);
    if (empty($parsed['rows'])) {
        header("Location: /admin/events/{$eventId}?entries_error=" . urlencode('No usable rows found. Each row needs at least a kart number.'));
        exit;
    }

    $replace = ($_POST['import_mode'] ?? 'replace') === 'replace';

    try {
        $result = save_event_entries($pdo, $eventId, $parsed['rows'], $replace);
    } catch (Throwable $e) {
        error_log('Entry list import failed for event ' . $eventId . ': ' . $e->getMessage());
        header("Location: /admin/events/{$eventId}?entries_error=" . urlencode('Import failed, nothing was changed.'));
        exit;
    }

    cache_invalidate_all();
    audit_log($pdo, 'admin', 'event_entries_imported', 'event', $eventId, [
        'inserted' => $result['inserted'],
        'skipped' => $result['skipped'],
        'classes_created' => $result['classes_created'],
        'entrants_created' => $result['entrants_created'],
        'mode' => $replace ? 'replace' : 'append',
    ], $ip);

    $notes = array_slice($parsed['errors'], 0, 5);
    if ($parsed['duplicates'] > 0) {
        $notes[] = $parsed['duplicates'] . ' duplicate row(s) in the file ignored.';
    }
    if ($result['skipped'] > 0) {
        $notes[] = $result['skipped'] . ' row(s) already present.';
    }
    // Say this out loud. It is the difference between an entry list that sits
    // in a table and one drivers can actually search, and if it reads zero on a
    // list full of classes something is wrong and the admin should see it.
    if ($result['entrants_created'] > 0) {
        $notes[] = $result['entrants_created'] . ' driver(s) are now searchable across '
            . ($result['classes_created'] > 0 ? $result['classes_created'] . ' new class(es).' : 'existing classes.');
    }

    $query = 'entries_ok=' . $result['inserted'];
    if ($notes) {
        $query .= '&entries_notes=' . urlencode(implode(' ', $notes));
    }

    header("Location: /admin/events/{$eventId}?{$query}");
    exit;
}

function delete_event(PDO $pdo, int $adminId, string $ip, int $eventId): void {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'CSRF verification failed.';
        return;
    }

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

    cache_invalidate_all();
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

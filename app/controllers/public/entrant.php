<?php
/**
 * Public controllers for driver discovery: the find-me search, the personal
 * page a search resolves to, the season page, and the two endpoints behind
 * "That's me" / "Not me".
 *
 * The journey these implement, in order:
 *
 *   1. /e/{event}/find          type a kart number
 *   2. disambiguation            if that number exists in more than one class,
 *                                pick which one. This step is the entire reason
 *                                the entrants table exists: #7 Cadet and #7
 *                                Senior X30 are different children.
 *   3. /e/{event}/d/{token}      the personal page. Shareable, durable, no
 *                                account, no email gate.
 *   4. /driver/{token}           the same driver across a whole season.
 *
 * Nothing here renders a driver's name. Public identity is number plus class.
 * See docs/PRIVACY-DESIGN.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/entrants.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../lib/currency.php';

/**
 * GET /e/{event-slug}/find
 *
 * Shows the search box, and when ?number= is supplied, resolves it:
 *  - no match     : an explanatory empty state, not a 404. Typing a number that
 *                   is not in the entry list is a normal thing to do.
 *  - one match    : redirect straight to the personal page. No confirmation
 *                   step, because there is nothing to confirm.
 *  - many matches : render the class picker.
 */
function public_entrant_find_controller(PDO $pdo, array $config, string $eventSlug): void
{
    $stmt = $pdo->prepare('SELECT id, slug, title FROM events WHERE slug = ? AND is_published = 1 LIMIT 1');
    $stmt->execute([$eventSlug]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($event === false) {
        http_response_code(404);
        render(__DIR__ . '/../../views/errors/404.php', []);
        return;
    }

    $number = trim((string)($_GET['number'] ?? ''));
    $matches = [];
    $searched = false;

    if ($number !== '') {
        $searched = true;
        $matches = find_entrants_by_number($pdo, (int)$event['id'], $number);

        // Exactly one class carries this number, so the search is already
        // settled. Sending the visitor through a one-option picker would be
        // a pointless extra tap on a phone.
        if (count($matches) === 1) {
            header('Location: /e/' . rawurlencode($event['slug']) . '/d/' . $matches[0]['share_token']);
            return;
        }
    }

    render(__DIR__ . '/../../views/public/entrant_find.php', [
        'pageTitle'   => 'Find your photos: ' . $event['title'],
        'siteName'    => $config['site']['name'] ?? 'Gallery',
        'event'       => $event,
        'number'      => $number,
        'matches'     => $matches,
        'searched'    => $searched,
        'currencyCode'=> config_currency_code($config),
    ]);
}

/**
 * GET /e/{event-slug}/d/{share-token}
 *
 * The personal page. The event slug in the URL is decorative: the token alone
 * identifies the entrant, and it is checked against the event so that a token
 * pasted under the wrong event slug redirects to the right one rather than
 * rendering a page that quietly disagrees with its own URL.
 */
function public_entrant_page_controller(PDO $pdo, array $config, string $eventSlug, string $token): void
{
    $entrant = find_entrant_by_token($pdo, $token);

    if ($entrant === null) {
        http_response_code(404);
        render(__DIR__ . '/../../views/errors/404.php', []);
        return;
    }

    if ($entrant['event_slug'] !== $eventSlug) {
        header('Location: /e/' . rawurlencode($entrant['event_slug']) . '/d/' . $entrant['share_token'], true, 301);
        return;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $total = count_entrant_photos($pdo, $entrant['id']);
    $photos = fetch_entrant_photos($pdo, $entrant['id'], $page);

    render(__DIR__ . '/../../views/public/entrant_page.php', [
        'pageTitle'    => '#' . $entrant['number'] . ' ' . $entrant['class_name'] . ': ' . $entrant['event_title'],
        'siteName'     => $config['site']['name'] ?? 'Gallery',
        'entrant'      => $entrant,
        'photos'       => $photos,
        'totalPhotos'  => $total,
        'page'         => $page,
        'hasMore'      => ($page * ENTRANT_PAGE_SIZE) < $total,
        'sessions'     => fetch_entrant_session_breakdown($pdo, $entrant['id']),
        'maybePhotos'  => fetch_entrant_maybe_photos($pdo, $entrant['id']),
        'csrfToken'    => csrf_token(),
        'currencyCode' => config_currency_code($config),
    ]);
}

/**
 * GET /driver/{share-token}
 *
 * Season identity: every event where the same number and class appear. One
 * durable link a driver can keep for a whole season, which is the foundation
 * the season-pass product will sit on.
 */
function public_driver_season_controller(PDO $pdo, array $config, string $token): void
{
    $entrant = find_entrant_by_token($pdo, $token);

    if ($entrant === null) {
        http_response_code(404);
        render(__DIR__ . '/../../views/errors/404.php', []);
        return;
    }

    $events = fetch_entrant_season($pdo, $entrant['id']);

    render(__DIR__ . '/../../views/public/entrant_season.php', [
        'pageTitle' => '#' . $entrant['number'] . ' ' . $entrant['class_name'] . ': season',
        'siteName'  => $config['site']['name'] ?? 'Gallery',
        'entrant'   => $entrant,
        'events'    => $events,
        'totalPhotos' => array_sum(array_column($events, 'photo_count')),
    ]);
}

/**
 * POST /entrant/review
 *
 * Records "That's me" or "Not me" against one proposed photo.
 *
 * Authorisation is the share token itself: holding it is what proves the caller
 * is the person the link was given to. The update is additionally scoped to
 * that entrant in SQL, so even a valid token cannot be used to attach an
 * arbitrary photo to an arbitrary driver.
 *
 * Rate limited because this is an unauthenticated write. Without a limit,
 * someone holding one token could grind through every photo in an event
 * confirming all of them.
 */
function public_entrant_review_controller(PDO $pdo, array $config): void
{
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Malformed request']);
        return;
    }

    // Reusable, not the one-time csrf_verify(): the personal page embeds one
    // token and the visitor reviews several photos from that single page load,
    // so a token consumed on the first "That's me" would 403 every click after
    // it. Same constant-time comparison, same requirement for a live session
    // token; it just does not invalidate on use. This is the same reason the
    // chunked upload flow uses it.
    if (!csrf_verify_reusable((string)($input['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token. Reload the page and try again.']);
        return;
    }

    $token = (string)($input['token'] ?? '');
    $photoId = (int)($input['photo_id'] ?? 0);
    $verdict = (string)($input['verdict'] ?? '');

    if (!in_array($verdict, ['mine', 'not_mine'], true) || $photoId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        return;
    }

    $entrant = find_entrant_by_token($pdo, $token);
    if ($entrant === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Unknown link']);
        return;
    }

    // Keyed on the token rather than the IP alone: a whole family behind one
    // household IP looking at three different drivers' pages should not
    // exhaust each other's budget.
    $maxReviews = adjust_rate_limit_for_dev($config, 120);
    $rlKey = $entrant['share_token'] . ':' . get_client_ip();
    if (!check_rate_limit($pdo, 'entrant_review', $rlKey, 3600, $maxReviews)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many changes just now. Try again shortly.']);
        return;
    }

    $ok = $verdict === 'mine'
        ? confirm_photo_entrant($pdo, $entrant['id'], $photoId)
        : reject_photo_entrant($pdo, $entrant['id'], $photoId);

    // A false return means the photo was not pending review for this entrant,
    // which is what a double-submit or a tampered photo id looks like. Neither
    // is worth an error page; report it as already handled.
    echo json_encode([
        'ok' => true,
        'changed' => $ok,
        'total' => count_entrant_photos($pdo, $entrant['id']),
    ]);
}

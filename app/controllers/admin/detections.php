<?php
/**
 * Admin: ingest a detection sidecar, and work the review queue it produces.
 *
 * These two live together because they are two halves of one job. The sidecar
 * lands thousands of attributions in seconds; the queue is where a human deals
 * with the handful the pipeline was unsure about. That combination is the
 * difference between six hours of manual tagging and twenty minutes of
 * exception-checking, which is the entire point of the feature.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/detections.php';
require_once __DIR__ . '/../../lib/entrants.php';
require_once __DIR__ . '/../../lib/audit.php';

/** Route dispatcher for /admin/detections and /admin/review. */
function admin_detections_controller(PDO $pdo, array $config): void
{
    require_admin();

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo 'Invalid security token. Go back and try again.';
            return;
        }

        if ($path === '/admin/review') {
            admin_review_submit($pdo, $config);
            return;
        }

        admin_detections_upload($pdo, $config);
        return;
    }

    if ($path === '/admin/review') {
        admin_review_page($pdo, $config);
        return;
    }

    admin_detections_page($pdo, $config, null);
}

/** GET /admin/detections — the sidecar upload form. */
function admin_detections_page(PDO $pdo, array $config, ?array $summary): void
{
    $sessions = $pdo->query(
        "SELECT s.id, s.name, s.slug, e.title AS event_title, c.name AS class_name,
                (SELECT COUNT(*) FROM photos p WHERE p.session_id = s.id) AS photo_count
           FROM sessions s
           JOIN events e ON e.id = s.event_id
           LEFT JOIN classes c ON c.id = s.class_id
          ORDER BY e.event_date DESC, s.sort_order ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    render(__DIR__ . '/../../views/admin/detections.php', [
        'pageTitle' => 'Import detections',
        'currentPage' => 'detections',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'sessions' => $sessions,
        'summary' => $summary,
        'csrfToken' => csrf_token(),
    ]);
}

/** POST /admin/detections — parse and apply an uploaded sidecar. */
function admin_detections_upload(PDO $pdo, array $config): void
{
    $sessionId = (int) ($_POST['session_id'] ?? 0);

    if ($sessionId <= 0) {
        admin_detections_page($pdo, $config, ['errors' => ['Choose a session to import into.']]);
        return;
    }

    // Uploaded file wins, pasted text is the fallback, so a small sidecar can be
    // pasted without saving it to disk first.
    $json = '';
    if (!empty($_FILES['sidecar']['tmp_name']) && is_uploaded_file($_FILES['sidecar']['tmp_name'])) {
        $json = (string) file_get_contents($_FILES['sidecar']['tmp_name']);
    } elseif (!empty($_POST['sidecar_text'])) {
        $json = (string) $_POST['sidecar_text'];
    }

    if (trim($json) === '') {
        admin_detections_page($pdo, $config, ['errors' => ['No file uploaded and nothing pasted.']]);
        return;
    }

    $parsed = parse_detection_sidecar($json);

    // Nothing usable at all: report and stop rather than claiming a successful
    // import of zero rows.
    if (empty($parsed['detections'])) {
        admin_detections_page($pdo, $config, [
            'errors' => $parsed['errors'] ?: ['No usable detections in that file.'],
        ]);
        return;
    }

    $summary = apply_detections($pdo, $sessionId, $parsed['detections'], current_admin_id());
    $summary['errors'] = $parsed['errors'];
    $summary['batch_id'] = $parsed['batch_id'];
    $summary['total'] = count($parsed['detections']);

    admin_detections_page($pdo, $config, $summary);
}

/**
 * GET /admin/review — the review queue, grouped by detected number.
 *
 * Grouping matters. A flat list of 400 uncertain photos is 400 decisions;
 * grouped by number it is usually a dozen groups where each group is right or
 * wrong as a whole, which is what makes bulk confirm worth having.
 */
function admin_review_page(PDO $pdo, array $config): void
{
    $eventId = (int) ($_GET['event'] ?? 0);

    $events = $pdo->query(
        'SELECT id, title, event_date FROM events ORDER BY event_date DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    if ($eventId > 0) {
        $groups = fetch_review_groups($pdo, $eventId);
    }

    render(__DIR__ . '/../../views/admin/review.php', [
        'pageTitle' => 'Review detections',
        'currentPage' => 'review',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'events' => $events,
        'eventId' => $eventId,
        'groups' => $groups,
        'csrfToken' => csrf_token(),
    ]);
}

/**
 * Every unreviewed, below-threshold attribution for an event, grouped by
 * entrant.
 *
 * @return list<array<string,mixed>>
 */
function fetch_review_groups(PDO $pdo, int $eventId): array
{
    $stmt = $pdo->prepare(
        "SELECT e.id AS entrant_id, e.number, e.driver_name, c.name AS class_name,
                COUNT(*) AS pending_count
           FROM photo_entrants pe
           JOIN entrants e ON e.id = pe.entrant_id
           JOIN classes c ON c.id = e.class_id
           JOIN photos p ON p.id = pe.photo_id
          WHERE e.event_id = ?
            AND pe.reviewed_at IS NULL
            AND pe.confidence < ?
            AND p.status = 'live'
          GROUP BY e.id, e.number, e.driver_name, c.name
          ORDER BY pending_count DESC, e.number ASC"
    );
    $stmt->execute([$eventId, ENTRANT_CONFIDENCE_THRESHOLD]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Thumbnails for each group. Capped, because a group of 300 would otherwise
    // put 300 images on one admin page.
    $photoStmt = $pdo->prepare(
        "SELECT p.id, p.public_token, pe.confidence, pe.source
           FROM photo_entrants pe
           JOIN photos p ON p.id = pe.photo_id
          WHERE pe.entrant_id = ?
            AND pe.reviewed_at IS NULL
            AND pe.confidence < ?
            AND p.status = 'live'
          ORDER BY pe.confidence DESC
          LIMIT 40"
    );

    foreach ($groups as &$group) {
        $photoStmt->execute([(int) $group['entrant_id'], ENTRANT_CONFIDENCE_THRESHOLD]);
        $group['photos'] = $photoStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $group['pending_count'] = (int) $group['pending_count'];
    }

    return $groups;
}

/**
 * POST /admin/review — bulk confirm or reject a group.
 *
 * Acts on the whole pending set for an entrant, not on the subset shown on
 * screen, because the page caps its thumbnails at 40 and an admin pressing
 * "confirm all" for #7 means all of #7, not the first 40 of them.
 */
function admin_review_submit(PDO $pdo, array $config): void
{
    $entrantId = (int) ($_POST['entrant_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $eventId = (int) ($_POST['event_id'] ?? 0);

    if ($entrantId <= 0 || !in_array($action, ['confirm_all', 'reject_all'], true)) {
        header('Location: /admin/review?event=' . $eventId);
        exit;
    }

    $confirm = $action === 'confirm_all';

    $stmt = $pdo->prepare(
        $confirm
            ? "UPDATE photo_entrants
                  SET source = 'manual', confidence = 1.0, reviewed_at = CURRENT_TIMESTAMP
                WHERE entrant_id = ? AND reviewed_at IS NULL AND confidence < ?"
            : "UPDATE photo_entrants
                  SET confidence = 0, reviewed_at = CURRENT_TIMESTAMP
                WHERE entrant_id = ? AND reviewed_at IS NULL AND confidence < ?"
    );
    $stmt->execute([$entrantId, ENTRANT_CONFIDENCE_THRESHOLD]);
    $affected = $stmt->rowCount();

    audit_log($pdo, 'admin', $confirm ? 'review_bulk_confirm' : 'review_bulk_reject', 'entrant', $entrantId, [
        'photos' => $affected,
    ], client_ip());

    header('Location: /admin/review?event=' . $eventId . '&done=' . $affected);
    exit;
}

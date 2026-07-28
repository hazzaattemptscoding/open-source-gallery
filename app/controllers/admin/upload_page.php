<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/view.php';

function admin_upload_page_controller(PDO $pdo, array $config): void {
    require_admin();
    $siteName = $config['site']['name'] ?? 'Gallery';

    $stmt = $pdo->prepare('
        SELECT e.id as event_id, e.slug as event_slug, s.id as session_id, s.slug as session_slug
        FROM events e
        LEFT JOIN sessions s ON s.event_id = e.id
        ORDER BY e.event_date DESC, s.sort_order ASC
    ');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sessionsByEvent = [];
    foreach ($rows as $row) {
        $eventId = (int)$row['event_id'];
        $eventSlug = $row['event_slug'];
        if (!isset($sessionsByEvent[$eventId])) {
            $sessionsByEvent[$eventId] = ['slug' => $eventSlug, 'sessions' => []];
        }
        if ($row['session_id']) {
            $sessionsByEvent[$eventId]['sessions'][] = [
                'id' => (int)$row['session_id'],
                'slug' => $row['session_slug'],
            ];
        }
    }

    render(__DIR__ . '/../../views/admin/upload.php', compact('siteName', 'sessionsByEvent'));
}

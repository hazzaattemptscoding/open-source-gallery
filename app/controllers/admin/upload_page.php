<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/csrf.php';

function admin_upload_page_controller(PDO $pdo, array $config): void {
    require_admin();
    $siteName = $config['site']['name'] ?? 'Gallery';
    $csrfToken = csrf_token();

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

    // Load recent upload batch and its files to show persisted state
    $recentBatch = null;
    $recentFiles = [];
    try {
        $stmt = $pdo->prepare('
            SELECT id, admin_id, created_at, completed_at
            FROM upload_batches
            WHERE admin_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$_SESSION['admin_id']]);
        $recentBatch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recentBatch) {
            $stmt = $pdo->prepare('
                SELECT id, original_filename, status, photo_id
                FROM upload_files
                WHERE batch_id = ?
                ORDER BY id
            ');
            $stmt->execute([(int)$recentBatch['id']]);
            $recentFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Silently fail if tables don't exist or other error
        error_log('Failed to load recent upload state: ' . $e->getMessage());
    }

    $pageTitle = 'Upload Photos';
    $currentPage = 'upload';
    render(__DIR__ . '/../../views/admin/upload.php', compact('pageTitle', 'currentPage', 'siteName', 'sessionsByEvent', 'csrfToken', 'recentBatch', 'recentFiles'));
}

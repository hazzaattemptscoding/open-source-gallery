<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';

/**
 * Home page: published events, newest first. No filters, no auth —
 * this is the entry point for anyone with the site's base URL.
 */
function public_home_controller(PDO $pdo, array $config): void {
    $siteName = $config['site']['name'] ?? 'Gallery';

    $stmt = $pdo->prepare('
        SELECT e.id, e.slug, e.title, e.venue, e.event_date, e.cover_photo_id, p.public_token AS cover_token
        FROM events e
        LEFT JOIN photos p ON p.id = e.cover_photo_id
        WHERE e.is_published = 1
        ORDER BY e.event_date DESC
    ');
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    render(__DIR__ . '/../../views/public/home.php', compact('siteName', 'events'));
}

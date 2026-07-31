<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/cache_headers.php';

/**
 * Home page: published events, newest first. No filters, no auth —
 * this is the entry point for anyone with the site's base URL.
 *
 * The single query is partitioned into three bands before rendering rather
 * than being queried three times: the list is already sorted by date and is
 * small enough that slicing it in PHP beats three round trips.
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

    [$hero, $recent, $archiveByYear] = partition_home_events($events);

    set_cache_headers('short');

    render(__DIR__ . '/../../views/public/home.php', compact('siteName', 'hero', 'recent', 'archiveByYear'));
}

/**
 * Split date-sorted events into the three bands the home page shows.
 *
 * The newest event carries the hero, so it is removed from everything below it;
 * previously it rendered twice, once full-bleed and again as the first card.
 * The next three fill the recent strip. Whatever remains becomes the archive,
 * grouped by year because that is how someone hunting an old race meeting
 * narrows it down.
 *
 * @param  list<array<string,mixed>> $events published events, newest first
 * @return array{0: ?array<string,mixed>, 1: list<array<string,mixed>>, 2: array<string, list<array<string,mixed>>>}
 */
function partition_home_events(array $events): array {
    if (empty($events)) {
        return [null, [], []];
    }

    $hero = array_shift($events);
    $recent = array_slice($events, 0, 3);
    $rest = array_slice($events, 3);

    $archiveByYear = [];
    foreach ($rest as $event) {
        /*
         * events.event_date is NOT NULL, so the fallback below should never
         * fire against a healthy database. It exists because the failure mode
         * without it is bad out of proportion to the cost: an unparseable date
         * would bucket the event under 1970, or drop it off the page entirely,
         * and a gallery silently missing from the archive is the kind of bug
         * nobody reports until a customer cannot find their photos.
         */
        $timestamp = !empty($event['event_date']) ? strtotime((string) $event['event_date']) : false;
        $year = $timestamp !== false ? date('Y', $timestamp) : 'Undated';
        $archiveByYear[$year][] = $event;
    }

    /*
     * Years descend; "Undated" sorts to the end regardless of how it would
     * compare against four-digit years.
     *
     * The parameters are int|string, not string: PHP silently casts numeric
     * array keys to integers, so '2026' arrives here as int 2026 while
     * 'Undated' stays a string. Declaring string under this file's
     * strict_types would make that a TypeError and 500 the home page as soon
     * as the archive has anything in it.
     */
    uksort($archiveByYear, function (int|string $a, int|string $b): int {
        if ($a === 'Undated') return 1;
        if ($b === 'Undated') return -1;
        return strcmp((string) $b, (string) $a);
    });

    return [$hero, $recent, $archiveByYear];
}

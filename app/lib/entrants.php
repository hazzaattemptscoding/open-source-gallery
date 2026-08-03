<?php
/**
 * Entrant lookup: the queries behind "find my photos".
 *
 * An entrant is one driver, in one class, at one event. That triple is the real
 * identity in this sport, and modelling it is the whole reason this file
 * exists. A kart number alone is not an identity: #7 in Cadet and #7 in Senior
 * X30 are different people, and any lookup that returns both mixed together is
 * wrong even though it looks like it worked.
 *
 * Everything here is read-only apart from confirm_photo_entrant() and
 * reject_photo_entrant(), which record a visitor pressing "That's me" or
 * "Not me".
 *
 * Privacy: nothing in this file returns driver_name to a caller that renders
 * publicly. The lookup functions deliberately do not select it. See
 * docs/PRIVACY-DESIGN.md; the short version is that a large share of these
 * drivers are minors, so public identity is number plus class and never a name.
 */

declare(strict_types=1);

/**
 * Attributions at or above this confidence are treated as settled and shown as
 * the driver's photos. Below it, they are offered as "are there more of you?"
 * for the visitor to confirm or reject.
 *
 * 0.75 matches the default threshold the detection pipeline is documented to
 * use, so a photo the pipeline was unsure about is the same photo the visitor
 * gets asked about.
 */
const ENTRANT_CONFIDENCE_THRESHOLD = 0.75;

/** How many photos a personal page shows per page. Matches GALLERY_PAGE_SIZE. */
const ENTRANT_PAGE_SIZE = 60;

/**
 * Mint an unguessable share token for a new entrant.
 *
 * 8 random bytes rendered as 16 hex characters, matching the column width and
 * the format the backfill produced. Unguessable matters: the token is the only
 * thing protecting a personal page, and personal-page URLs get posted into
 * group chats, so a sequential or derived identifier would let anyone walk the
 * entire entry list.
 */
function generate_entrant_share_token(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * Give a token to every entrant that does not have one yet.
 *
 * Migration 016 backfills entrants from the existing entry lists and leaves
 * share_token NULL, because SQL has no good source of randomness for this. The
 * obvious SQL spellings are all worse than they look: MySQL's UUID() is a
 * timestamp plus a MAC address rather than a random number, and anything hashed
 * from the row's own identity is enumerable offline. This token is the only
 * thing protecting a personal page, and the people behind those pages are
 * frequently children, so it comes from random_bytes() or it does not exist.
 *
 * Safe to call as often as you like: it only touches rows where the token is
 * still missing, and the UPDATE is guarded on that too, so two cron runs
 * overlapping cannot hand the same entrant two different tokens (the loser
 * updates nothing and moves on).
 *
 * Batched rather than done in one statement because a season's entry lists can
 * be thousands of rows and this runs inside the five-minute cron budget
 * alongside derivative generation, which is what customers are actually
 * waiting for.
 *
 * @param int $limit Most rows to mint in one call.
 * @return int How many tokens were written.
 */
function mint_missing_entrant_share_tokens(PDO $pdo, int $limit = 500): int
{
    $minted = 0;

    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM entrants
              WHERE share_token IS NULL OR share_token = ''
              ORDER BY id ASC
              LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if ($ids === []) {
            return 0;
        }

        $update = $pdo->prepare(
            "UPDATE entrants
                SET share_token = ?
              WHERE id = ?
                AND (share_token IS NULL OR share_token = '')"
        );

        foreach ($ids as $id) {
            /*
             * Retry on collision rather than giving up on the row.
             *
             * 64 bits makes a collision vanishingly unlikely, but "vanishingly
             * unlikely" is not "impossible", and the failure mode without a
             * retry is an entrant permanently without a token, which means a
             * driver whose photos can never be found. Three attempts turns a
             * once-in-a-lifetime event into a non-event.
             */
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $update->execute([generate_entrant_share_token(), (int) $id]);
                    if ($update->rowCount() > 0) {
                        $minted++;
                    }
                    break;
                } catch (PDOException $e) {
                    // Unique violation: 23000 on both MySQL and SQLite.
                    if ($e->getCode() !== '23000' || $attempt === 2) {
                        throw $e;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('mint_missing_entrant_share_tokens failed: ' . $e->getMessage());
    }

    return $minted;
}

/**
 * Normalise a class name into the slug used to identify it within an event.
 *
 * Kept in one function because three places have to agree exactly: this, the
 * classes backfill in migration 016, and the join in the photo_entrants
 * backfill. "Junior X30" and "Junior/X30" are one class typed two ways, and if
 * any of the three normalises differently they silently stop matching.
 */
function entrant_class_slug(string $class): string
{
    return strtolower(str_replace([' ', '/'], ['-', '-'], trim($class)));
}

/**
 * Derive classes and entrants for one event from its entry list.
 *
 * Why this exists
 * ---------------
 * event_entries is what the organiser imports: a flat list of kart number,
 * driver and class. classes and entrants are what the find-me flow searches,
 * and they are a different shape, because a driver's real identity is
 * (event, class, number) and a class needs an id a session can point at.
 *
 * Migration 016 derives them once for the entry lists that already existed. Any
 * event created after that point needs the same derivation, or its entry list
 * imports fine and then nobody can find a single photo. This is that step, run
 * after every import.
 *
 * Additive on purpose
 * -------------------
 * Nothing here deletes. An entrant's share_token is a durable public URL that
 * has been posted into group chats, so re-importing an entry list must not
 * revoke it, and removing a row from the CSV must not break a link somebody is
 * still using. An entrant whose entry disappears simply stops gaining photos.
 *
 * Entries with no class are skipped rather than guessed at. A number without a
 * class is not an identity in this sport: #7 could be any of several children.
 *
 * @return array{classes:int, entrants:int} How many of each were created.
 */
function sync_event_entrants(PDO $pdo, int $eventId): array
{
    $classesMade = 0;
    $entrantsMade = 0;

    try {
        $stmt = $pdo->prepare(
            'SELECT kart_number, driver_name, class FROM event_entries WHERE event_id = ?'
        );
        $stmt->execute([$eventId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // slug => id, for classes this event already has.
        $stmt = $pdo->prepare('SELECT id, slug FROM classes WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $classIds = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $classIds[(string) $row['slug']] = (int) $row['id'];
        }

        // "class_id\0number" for entrants this event already has, so a re-import
        // is a no-op rather than a duplicate-key error.
        $stmt = $pdo->prepare('SELECT class_id, number FROM entrants WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $seen = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $seen[$row['class_id'] . "\0" . $row['number']] = true;
        }

        $insertClass = $pdo->prepare(
            'INSERT INTO classes (event_id, name, slug, sort_order) VALUES (?, ?, ?, 0)'
        );
        $insertEntrant = $pdo->prepare(
            'INSERT INTO entrants (event_id, class_id, number, driver_name, team, share_token)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($entries as $entry) {
            $class = trim((string) $entry['class']);
            $number = trim((string) $entry['kart_number']);
            if ($class === '' || $number === '') {
                continue;
            }

            $slug = entrant_class_slug($class);
            if ($slug === '') {
                continue;
            }

            if (!isset($classIds[$slug])) {
                $insertClass->execute([$eventId, $class, $slug]);
                $classIds[$slug] = (int) $pdo->lastInsertId();
                $classesMade++;
            }
            $classId = $classIds[$slug];

            $key = $classId . "\0" . $number;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $insertEntrant->execute([
                $eventId,
                $classId,
                $number,
                (string) $entry['driver_name'],
                '',
                generate_entrant_share_token(),
            ]);
            $entrantsMade++;
        }
    } catch (Throwable $e) {
        error_log('sync_event_entrants failed: ' . $e->getMessage());
    }

    return ['classes' => $classesMade, 'entrants' => $entrantsMade];
}

/**
 * Find every entrant at one event carrying a given kart number.
 *
 * Returns a list because the number is genuinely ambiguous until a class is
 * chosen. One row means the search is settled and the caller can go straight to
 * the personal page. More than one means the caller has to ask which class,
 * which is the disambiguation step that makes the composite key visible to the
 * user instead of silently picking a winner.
 *
 * The number is compared as text, not cast to an integer: '7a' and '07' are
 * real kart numbers and both would be destroyed by an integer cast.
 *
 * @return list<array{id:int, number:string, share_token:string, class_id:int, class_name:string, class_slug:string, photo_count:int}>
 */
function find_entrants_by_number(PDO $pdo, int $eventId, string $number): array
{
    $number = trim($number);
    if ($number === '') {
        return [];
    }

    // photo_count comes from a correlated subquery rather than a JOIN + GROUP BY
    // so that an entrant with no photos yet still appears. Being told "#7 Cadet
    // has no photos" is a useful answer; being silently dropped from the
    // disambiguation list is not, because the visitor then thinks they typed
    // the wrong number.
    $sql = "
        SELECT e.id,
               e.number,
               e.share_token,
               e.class_id,
               c.name AS class_name,
               c.slug AS class_slug,
               (SELECT COUNT(*) FROM photo_entrants pe
                 WHERE pe.entrant_id = e.id AND pe.confidence >= ?) AS photo_count
          FROM entrants e
          JOIN classes c ON c.id = e.class_id
         WHERE e.event_id = ?
           AND e.number = ?
         ORDER BY c.sort_order ASC, c.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([ENTRANT_CONFIDENCE_THRESHOLD, $eventId, $number]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['class_id'] = (int) $row['class_id'];
        $row['photo_count'] = (int) $row['photo_count'];
        $row['share_token'] = (string) ($row['share_token'] ?? '');
    }
    unset($row);

    /*
     * Close the gap between migration 016 and the first cron run.
     *
     * The migration leaves backfilled entrants with no share_token, and cron
     * mints them within five minutes. A search landing inside that window would
     * otherwise render a link to /e/{event}/d/ with nothing on the end. This is
     * a write on a read path, which is normally worth avoiding, but it runs
     * only while that backlog exists, only for the handful of rows a single
     * search returned, and the alternative is a broken link on the one journey
     * the whole feature is for.
     */
    if (array_filter($rows, static fn(array $r): bool => $r['share_token'] === '') !== []) {
        mint_missing_entrant_share_tokens($pdo, 100);

        $stmt->execute([ENTRANT_CONFIDENCE_THRESHOLD, $eventId, $number]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$reread) {
            $reread['id'] = (int) $reread['id'];
            $reread['class_id'] = (int) $reread['class_id'];
            $reread['photo_count'] = (int) $reread['photo_count'];
            $reread['share_token'] = (string) ($reread['share_token'] ?? '');
        }
        unset($reread);
    }

    // Anything still without a token cannot be linked to, so it is not a usable
    // match. Dropping it is better than offering a dead link.
    return array_values(array_filter(
        $rows,
        static fn(array $r): bool => $r['share_token'] !== ''
    ));
}

/**
 * Load one entrant by its share token, which is how every personal-page URL
 * identifies its subject.
 *
 * Returns null when the token matches nothing, so the caller can 404 rather
 * than leaking whether a token merely has no photos yet.
 *
 * driver_name is not selected. The personal page has no use for it and must not
 * be able to render it by accident.
 *
 * @return array{id:int, event_id:int, number:string, share_token:string, class_name:string, class_slug:string, event_slug:string, event_title:string}|null
 */
function find_entrant_by_token(PDO $pdo, string $token): ?array
{
    // Cheap shape check before touching the database. The column is 16 hex
    // characters, so anything else cannot match and should not become a query.
    if (preg_match('/^[0-9a-f]{16}$/', $token) !== 1) {
        return null;
    }

    $sql = "
        SELECT e.id,
               e.event_id,
               e.number,
               e.share_token,
               c.name AS class_name,
               c.slug AS class_slug,
               ev.slug AS event_slug,
               ev.title AS event_title
          FROM entrants e
          JOIN classes c ON c.id = e.class_id
          JOIN events ev ON ev.id = e.event_id
         WHERE e.share_token = ?
         LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // PDO returns false, not null, when there is no row.
    if ($row === false) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['event_id'] = (int) $row['event_id'];
    return $row;
}

/**
 * One page of an entrant's confirmed photos.
 *
 * "Confirmed" means at or above ENTRANT_CONFIDENCE_THRESHOLD. Only live photos
 * are returned, so an unpublished or failed upload never appears on a page a
 * customer can reach.
 *
 * @return list<array<string,mixed>>
 */
function fetch_entrant_photos(PDO $pdo, int $entrantId, int $page = 1, int $perPage = ENTRANT_PAGE_SIZE): array
{
    $offset = max(0, ($page - 1) * $perPage);

    $sql = "
        SELECT p.id, p.public_token, p.width, p.height, p.sort_order,
               s.name AS session_name, s.slug AS session_slug
          FROM photo_entrants pe
          JOIN photos p ON p.id = pe.photo_id
          LEFT JOIN sessions s ON s.id = p.session_id
         WHERE pe.entrant_id = ?
           AND pe.confidence >= ?
           AND p.status = 'live'
           AND p.media_type = 'photo'
         ORDER BY p.sort_order ASC, p.id ASC
         LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $entrantId, PDO::PARAM_INT);
    $stmt->bindValue(2, ENTRANT_CONFIDENCE_THRESHOLD);
    $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Total confirmed, live photos for one entrant, for pagination and the header count. */
function count_entrant_photos(PDO $pdo, int $entrantId): int
{
    $sql = "
        SELECT COUNT(*)
          FROM photo_entrants pe
          JOIN photos p ON p.id = pe.photo_id
         WHERE pe.entrant_id = ?
           AND pe.confidence >= ?
           AND p.status = 'live'
           AND p.media_type = 'photo'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entrantId, ENTRANT_CONFIDENCE_THRESHOLD]);
    return (int) $stmt->fetchColumn();
}

/**
 * How an entrant's confirmed photos break down by session.
 *
 * This is what turns a personal page from a wall of images into the shape of
 * someone's day: practice, heats, final. People remember their day that way.
 *
 * @return list<array{session_name:string, session_slug:?string, photo_count:int}>
 */
function fetch_entrant_session_breakdown(PDO $pdo, int $entrantId): array
{
    $sql = "
        SELECT COALESCE(s.name, 'Other') AS session_name,
               s.slug AS session_slug,
               COUNT(*) AS photo_count
          FROM photo_entrants pe
          JOIN photos p ON p.id = pe.photo_id
          LEFT JOIN sessions s ON s.id = p.session_id
         WHERE pe.entrant_id = ?
           AND pe.confidence >= ?
           AND p.status = 'live'
           AND p.media_type = 'photo'
         GROUP BY s.id, s.name, s.slug, s.sort_order
         ORDER BY s.sort_order ASC, s.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entrantId, ENTRANT_CONFIDENCE_THRESHOLD]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['photo_count'] = (int) $row['photo_count'];
    }
    return $rows;
}

/**
 * Photos the system thinks might be this entrant but is not sure about: the
 * "are there more of you?" set.
 *
 * Below the confidence threshold, and not yet reviewed by a human. Once someone
 * answers either way, reviewed_at is set and the photo stops being offered, so
 * nobody is asked the same question twice.
 *
 * @return list<array<string,mixed>>
 */
function fetch_entrant_maybe_photos(PDO $pdo, int $entrantId, int $limit = 24): array
{
    $sql = "
        SELECT p.id, p.public_token, p.width, p.height,
               pe.confidence, pe.source
          FROM photo_entrants pe
          JOIN photos p ON p.id = pe.photo_id
         WHERE pe.entrant_id = ?
           AND pe.confidence < ?
           AND pe.reviewed_at IS NULL
           AND p.status = 'live'
           AND p.media_type = 'photo'
         ORDER BY pe.confidence DESC, p.id ASC
         LIMIT ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $entrantId, PDO::PARAM_INT);
    $stmt->bindValue(2, ENTRANT_CONFIDENCE_THRESHOLD);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Record "That's me": promote a low-confidence attribution to confirmed.
 *
 * Writes source='manual' and confidence=1.0 because a human has now said so,
 * which outranks anything the detector produced, and stamps reviewed_at so the
 * photo is not offered again.
 *
 * Scoped to the entrant in the WHERE clause, so a caller holding one token can
 * only ever confirm photos already proposed for that entrant. It cannot be used
 * to attach an arbitrary photo to an arbitrary driver.
 *
 * @return bool True when a row was actually updated.
 */
function confirm_photo_entrant(PDO $pdo, int $entrantId, int $photoId): bool
{
    $sql = "
        UPDATE photo_entrants
           SET source = 'manual',
               confidence = 1.0,
               reviewed_at = CURRENT_TIMESTAMP
         WHERE entrant_id = ?
           AND photo_id = ?
           AND reviewed_at IS NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entrantId, $photoId]);
    return $stmt->rowCount() > 0;
}

/**
 * Record "Not me": mark the attribution reviewed and wrong.
 *
 * The row is kept rather than deleted, with confidence zeroed. Keeping it is
 * what stops the same wrong guess being re-proposed after the next detection
 * run, and it leaves evidence that a human rejected it, which is worth more
 * than the row costs.
 *
 * @return bool True when a row was actually updated.
 */
function reject_photo_entrant(PDO $pdo, int $entrantId, int $photoId): bool
{
    $sql = "
        UPDATE photo_entrants
           SET confidence = 0,
               reviewed_at = CURRENT_TIMESTAMP
         WHERE entrant_id = ?
           AND photo_id = ?
           AND reviewed_at IS NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entrantId, $photoId]);
    return $stmt->rowCount() > 0;
}

/**
 * Every event this entrant's share token also covers, for the season page.
 *
 * Entrants are per-event rows, so "the same driver across a season" has to be
 * reconstructed. This matches on (number, class slug), which is the identity a
 * driver actually keeps between rounds of the same championship. It deliberately
 * does not match on driver_name: names are inconsistently spelled in organiser
 * CSVs, and matching on them would mean reading name data on a public path.
 *
 * @return list<array{id:int, share_token:string, event_slug:string, event_title:string, event_date:?string, photo_count:int}>
 */
function fetch_entrant_season(PDO $pdo, int $entrantId): array
{
    $sql = "
        SELECT e2.id,
               e2.share_token,
               ev.slug AS event_slug,
               ev.title AS event_title,
               ev.event_date,
               (SELECT COUNT(*) FROM photo_entrants pe
                  JOIN photos p ON p.id = pe.photo_id
                 WHERE pe.entrant_id = e2.id
                   AND pe.confidence >= ?
                   AND p.status = 'live') AS photo_count
          FROM entrants e1
          JOIN classes c1 ON c1.id = e1.class_id
          JOIN entrants e2 ON e2.number = e1.number
          JOIN classes c2 ON c2.id = e2.class_id AND c2.slug = c1.slug
          JOIN events ev ON ev.id = e2.event_id
         WHERE e1.id = ?
           AND ev.is_published = 1
         ORDER BY ev.event_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([ENTRANT_CONFIDENCE_THRESHOLD, $entrantId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['photo_count'] = (int) $row['photo_count'];
    }
    return $rows;
}

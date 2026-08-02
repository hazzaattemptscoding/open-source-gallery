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
    }
    return $rows;
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

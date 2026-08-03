<?php
/**
 * Ingest for kart-number detections produced off-site.
 *
 * The boundary this file defends
 * -----------------------------
 * Detection runs on the photographer's own machine, where a GPU and a YOLO/OCR
 * pipeline live. The web application never runs inference: it runs on shared
 * hosting with a 5-minute cron and no GPU, and it would be the wrong place even
 * if it could. What arrives here is a sidecar file of results.
 *
 * Keeping that boundary explicit is what lets the detection side be rewritten,
 * retrained or replaced without touching the gallery, and lets the gallery be
 * upgraded without breaking the pipeline. The contract is the JSON below and
 * nothing else.
 *
 * The sidecar format
 * ------------------
 *   {
 *     "batch_id": "buckmore-2026-08-01",
 *     "generated_at": "2026-08-01T21:14:00Z",
 *     "detections": [
 *       {"filename": "IMG_4821.jpg", "number": "7", "confidence": 0.94, "method": "ocr"},
 *       {"filename": "IMG_4822.jpg", "number": "7", "confidence": 0.61, "method": "propagated"}
 *     ]
 *   }
 *
 * method is recorded as photo_entrants.source and is one of:
 *   ocr         a number was read off the kart
 *   propagated  inferred from a neighbouring frame in the same burst, where the
 *               number may not be visible at all
 *   livery      matched on helmet or kart livery rather than a number
 *   manual      a human said so
 *
 * What this deliberately will not do
 * ----------------------------------
 * Guess. A detection that cannot be resolved to exactly one entrant is reported
 * as unresolved and no row is written. The obvious temptation is to pick the
 * first matching class when a number exists in several, which silently
 * attributes a child's photos to a different child. Ambiguity goes to a human.
 */

declare(strict_types=1);

require_once __DIR__ . '/entrants.php';
require_once __DIR__ . '/audit.php';

/** Detection sources the ingest will accept, matching photo_entrants.source. */
const DETECTION_METHODS = ['ocr', 'propagated', 'livery', 'manual'];

/** Refuse absurdly large sidecars before doing any work. */
const DETECTION_MAX_BYTES = 8 * 1024 * 1024;

/**
 * Parse and validate a sidecar document.
 *
 * Validation is deliberately strict and reports every bad row rather than
 * stopping at the first: a photographer who has just run a 3,000-image batch
 * needs to know everything that is wrong in one pass, not one problem per
 * attempt.
 *
 * @return array{batch_id:string, detections:list<array{filename:string, number:string, confidence:float, method:string}>, errors:list<string>}
 */
function parse_detection_sidecar(string $json): array
{
    $result = ['batch_id' => '', 'detections' => [], 'errors' => []];

    if (strlen($json) > DETECTION_MAX_BYTES) {
        $result['errors'][] = 'File is too large (limit ' . (DETECTION_MAX_BYTES / 1024 / 1024) . ' MB).';
        return $result;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        $result['errors'][] = 'Not valid JSON.';
        return $result;
    }

    $result['batch_id'] = (string) ($data['batch_id'] ?? '');

    if (!isset($data['detections']) || !is_array($data['detections'])) {
        $result['errors'][] = 'No "detections" array found.';
        return $result;
    }

    foreach ($data['detections'] as $index => $row) {
        $label = 'detection ' . ($index + 1);

        if (!is_array($row)) {
            $result['errors'][] = "{$label}: not an object.";
            continue;
        }

        $filename = trim((string) ($row['filename'] ?? ''));
        $number = trim((string) ($row['number'] ?? ''));
        $method = strtolower(trim((string) ($row['method'] ?? 'ocr')));

        if ($filename === '') {
            $result['errors'][] = "{$label}: missing filename.";
            continue;
        }
        if ($number === '') {
            $result['errors'][] = "{$label} ({$filename}): missing number.";
            continue;
        }
        if (!in_array($method, DETECTION_METHODS, true)) {
            $result['errors'][] = "{$label} ({$filename}): unknown method '{$method}'.";
            continue;
        }

        // Confidence is required and must be a real 0..1 value. Defaulting a
        // missing one to 1.0 would push unreviewed guesses straight to live;
        // defaulting to 0 would silently bury good detections. Neither is a
        // decision this code should make on the pipeline's behalf.
        if (!isset($row['confidence']) || !is_numeric($row['confidence'])) {
            $result['errors'][] = "{$label} ({$filename}): missing or non-numeric confidence.";
            continue;
        }

        $confidence = (float) $row['confidence'];
        if ($confidence < 0 || $confidence > 1) {
            $result['errors'][] = "{$label} ({$filename}): confidence {$confidence} is outside 0..1.";
            continue;
        }

        $result['detections'][] = [
            'filename' => $filename,
            'number' => $number,
            'confidence' => $confidence,
            'method' => $method,
        ];
    }

    return $result;
}

/**
 * Apply parsed detections to one session's photos.
 *
 * Scoped to a session because that is what makes a number resolvable: the
 * session names the class, and (event, class, number) is the real identity of a
 * driver. Where a session has no class set, the number is looked up across the
 * event and is only accepted when exactly one entrant carries it.
 *
 * Returns counts and the unresolved rows, so the admin screen can show what
 * needs a human rather than just declaring success.
 *
 * @param list<array{filename:string, number:string, confidence:float, method:string}> $detections
 * @return array{applied:int, confirmed:int, review:int, unknown_photo:list<string>, unknown_entrant:list<string>, ambiguous:list<string>}
 */
function apply_detections(PDO $pdo, int $sessionId, array $detections, ?int $adminId = null): array
{
    $summary = [
        'applied' => 0,
        'confirmed' => 0,
        'review' => 0,
        'unknown_photo' => [],
        'unknown_entrant' => [],
        'ambiguous' => [],
    ];

    $session = fetch_session_for_detection($pdo, $sessionId);
    if ($session === null) {
        return $summary;
    }

    $eventId = (int) $session['event_id'];
    $sessionClassId = $session['class_id'] !== null ? (int) $session['class_id'] : null;

    // Filename to photo id, for this session only. Built once: a 3,000-row
    // sidecar would otherwise issue 3,000 lookups, which on shared hosting is
    // the difference between a few seconds and a timeout.
    $photoIds = fetch_session_photo_ids_by_filename($pdo, $sessionId);

    // Number to entrant, resolved once per distinct number rather than per row.
    $entrantCache = [];

    foreach ($detections as $detection) {
        $filename = $detection['filename'];
        $number = $detection['number'];

        $key = strtolower($filename);
        if (!isset($photoIds[$key])) {
            $summary['unknown_photo'][] = $filename;
            continue;
        }
        $photoId = $photoIds[$key];

        if (!array_key_exists($number, $entrantCache)) {
            $entrantCache[$number] = resolve_detection_entrant($pdo, $eventId, $sessionClassId, $number);
        }
        $resolved = $entrantCache[$number];

        if ($resolved === null) {
            $summary['unknown_entrant'][] = "#{$number} ({$filename})";
            continue;
        }
        if ($resolved === 'ambiguous') {
            $summary['ambiguous'][] = "#{$number} ({$filename})";
            continue;
        }

        if (record_detection($pdo, $photoId, (int) $resolved, $detection)) {
            $summary['applied']++;
            if ($detection['confidence'] >= ENTRANT_CONFIDENCE_THRESHOLD) {
                $summary['confirmed']++;
            } else {
                $summary['review']++;
            }
        }
    }

    audit_log($pdo, 'admin', 'detections_ingested', 'session', $sessionId, [
        'applied' => $summary['applied'],
        'confirmed' => $summary['confirmed'],
        'review' => $summary['review'],
        'unresolved' => count($summary['unknown_photo'])
            + count($summary['unknown_entrant'])
            + count($summary['ambiguous']),
    ], null);

    return $summary;
}

/** Session row with the event and class needed to resolve a number. */
function fetch_session_for_detection(PDO $pdo, int $sessionId): ?array
{
    $stmt = $pdo->prepare('SELECT id, event_id, class_id FROM sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * Filename to photo id for one session, lower-cased for matching.
 *
 * Case-insensitive because cameras write IMG_0001.JPG and pipelines routinely
 * hand back img_0001.jpg, and a case mismatch that silently drops every
 * detection is a miserable thing to debug.
 *
 * @return array<string,int>
 */
function fetch_session_photo_ids_by_filename(PDO $pdo, int $sessionId): array
{
    $stmt = $pdo->prepare('SELECT id, original_filename FROM photos WHERE session_id = ?');
    $stmt->execute([$sessionId]);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[strtolower((string) $row['original_filename'])] = (int) $row['id'];
    }
    return $map;
}

/**
 * Resolve a detected number to exactly one entrant.
 *
 * With a class on the session this is unambiguous. Without one, the number is
 * looked up across the event and accepted only if a single entrant carries it.
 *
 * @return int|string|null entrant id, the string 'ambiguous', or null if unknown.
 */
function resolve_detection_entrant(PDO $pdo, int $eventId, ?int $classId, string $number)
{
    if ($classId !== null) {
        $stmt = $pdo->prepare(
            'SELECT id FROM entrants WHERE event_id = ? AND class_id = ? AND number = ? LIMIT 1'
        );
        $stmt->execute([$eventId, $classId, $number]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    $matches = find_entrants_by_number($pdo, $eventId, $number);

    if (count($matches) === 0) {
        return null;
    }
    if (count($matches) > 1) {
        // Two drivers share this number in this event and the session does not
        // say which class it belongs to. Guessing here would attribute one
        // child's photos to another.
        return 'ambiguous';
    }

    return (int) $matches[0]['id'];
}

/**
 * Write one attribution.
 *
 * Never downgrades an existing row. A photo already confirmed by a human, or
 * already carrying a higher-confidence detection, keeps what it has: a later
 * pipeline run at 0.55 must not undo a person pressing "That's me", and
 * re-running a batch must not walk confidence backwards.
 */
function record_detection(PDO $pdo, int $photoId, int $entrantId, array $detection): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT confidence, reviewed_at FROM photo_entrants WHERE photo_id = ? AND entrant_id = ?'
        );
        $stmt->execute([$photoId, $entrantId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            // Reviewed by a human, or already at least as confident: leave it.
            if ($existing['reviewed_at'] !== null
                || (float) $existing['confidence'] >= $detection['confidence']) {
                return false;
            }

            $update = $pdo->prepare(
                'UPDATE photo_entrants SET confidence = ?, source = ?
                  WHERE photo_id = ? AND entrant_id = ? AND reviewed_at IS NULL'
            );
            $update->execute([$detection['confidence'], $detection['method'], $photoId, $entrantId]);
            return $update->rowCount() > 0;
        }

        $insert = $pdo->prepare(
            'INSERT INTO photo_entrants (photo_id, entrant_id, source, confidence, reviewed_at)
             VALUES (?, ?, ?, ?, NULL)'
        );
        $insert->execute([$photoId, $entrantId, $detection['method'], $detection['confidence']]);
        return true;
    } catch (Throwable $e) {
        error_log('record_detection failed: ' . $e->getMessage());
        return false;
    }
}

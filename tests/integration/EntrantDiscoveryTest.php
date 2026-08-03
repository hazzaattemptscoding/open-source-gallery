<?php
/**
 * Integration tests for driver discovery: the find-me lookup, the personal
 * page's queries, and the "That's me" / "Not me" review writes.
 *
 * The case that matters most here is the composite key. A kart number is not
 * unique within an event, and the whole feature is wrong if a lookup on #7
 * returns Cadet's #7 and Senior's #7 as one undifferentiated set. Several of
 * these tests exist purely to keep that from regressing.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

class EntrantDiscoveryTest extends TestCase {

    /** Insert a class and return its id. */
    private function createClass(int $eventId, string $name, string $slug): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO classes (event_id, name, slug, sort_order) VALUES (?, ?, ?, 0)'
        );
        $stmt->execute([$eventId, $name, $slug]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Insert an entrant and return [id, share_token]. */
    private function createEntrant(int $eventId, int $classId, string $number, string $driverName = ''): array {
        require_once APP_ROOT . '/app/lib/entrants.php';
        $token = generate_entrant_share_token();
        $stmt = $this->pdo->prepare(
            'INSERT INTO entrants (event_id, class_id, number, driver_name, team, share_token)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$eventId, $classId, $number, $driverName, '', $token]);
        return [(int) $this->pdo->lastInsertId(), $token];
    }

    /** Attribute a photo to an entrant at a given confidence. */
    private function attribute(int $photoId, int $entrantId, float $confidence, ?string $reviewedAt = null): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO photo_entrants (photo_id, entrant_id, source, confidence, reviewed_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$photoId, $entrantId, 'ocr', $confidence, $reviewedAt]);
    }

    /**
     * The headline case: the same number in two classes must resolve to two
     * distinct entrants, not one merged result.
     */
    public function testSameNumberInTwoClassesResolvesToTwoEntrants(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $cadet = $this->createClass($eventId, 'Cadet', 'cadet');
        $senior = $this->createClass($eventId, 'Senior X30', 'senior-x30');

        $this->createEntrant($eventId, $cadet, '7');
        $this->createEntrant($eventId, $senior, '7');

        $matches = find_entrants_by_number($this->pdo, $eventId, '7');

        $this->assertCount(2, $matches, '#7 exists in two classes and both must be offered');
        $classNames = array_column($matches, 'class_name');
        $this->assertContains('Cadet', $classNames);
        $this->assertContains('Senior X30', $classNames);
        $this->assertNotEquals(
            $matches[0]['share_token'],
            $matches[1]['share_token'],
            'two different drivers must not share a personal-page link'
        );
    }

    /** An unambiguous number returns exactly one entrant. */
    public function testUnambiguousNumberReturnsSingleEntrant(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $cadet = $this->createClass($eventId, 'Cadet', 'cadet');
        $this->createEntrant($eventId, $cadet, '42');

        $matches = find_entrants_by_number($this->pdo, $eventId, '42');
        $this->assertCount(1, $matches);
        $this->assertSame('42', $matches[0]['number']);
    }

    /** Numbers are text: '7a' and '07' are real and must not be integer-cast. */
    public function testNonNumericKartNumbersSurvive(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $cls = $this->createClass($eventId, 'Cadet', 'cadet');
        $this->createEntrant($eventId, $cls, '7a');

        $this->assertCount(1, find_entrants_by_number($this->pdo, $eventId, '7a'));
        // '7' must not match '7a', which is what an integer cast would do.
        $this->assertCount(0, find_entrants_by_number($this->pdo, $eventId, '7'));
    }

    /** A number nobody entered returns nothing rather than erroring. */
    public function testUnknownNumberReturnsEmpty(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $this->assertSame([], find_entrants_by_number($this->pdo, $eventId, '9999'));
        $this->assertSame([], find_entrants_by_number($this->pdo, $eventId, ''));
    }

    /** Token lookup round-trips, and anything malformed returns null. */
    public function testShareTokenLookup(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $cls = $this->createClass($eventId, 'Junior', 'junior');
        [$entrantId, $token] = $this->createEntrant($eventId, $cls, '15');

        $found = find_entrant_by_token($this->pdo, $token);
        $this->assertNotNull($found);
        $this->assertSame($entrantId, $found['id']);
        $this->assertSame('15', $found['number']);
        $this->assertSame('Junior', $found['class_name']);

        $this->assertNull(find_entrant_by_token($this->pdo, 'not-a-token'));
        $this->assertNull(find_entrant_by_token($this->pdo, ''));
        $this->assertNull(find_entrant_by_token($this->pdo, str_repeat('f', 16)));
        $this->assertNull(find_entrant_by_token($this->pdo, "' OR 1=1 --"));
    }

    /** The personal page must never expose the driver's name. */
    public function testTokenLookupDoesNotReturnDriverName(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $cls = $this->createClass($eventId, 'Cadet', 'cadet');
        [, $token] = $this->createEntrant($eventId, $cls, '3', 'Alice Example');

        $found = find_entrant_by_token($this->pdo, $token);
        $this->assertNotNull($found);
        $this->assertArrayNotHasKey('driver_name', $found);
        $this->assertNotContains('Alice Example', array_map('strval', array_values($found)));
    }

    /** Only attributions at or above the threshold count as the driver's photos. */
    public function testOnlyConfidentAttributionsCountAsConfirmed(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $cls = $this->createClass($eventId, 'Cadet', 'cadet');
        [$entrantId] = $this->createEntrant($eventId, $cls, '9');

        $sure = $this->createPhoto($sessionId, ['status' => 'live']);
        $unsure = $this->createPhoto($sessionId, ['status' => 'live']);

        $this->attribute($sure, $entrantId, 0.98);
        $this->attribute($unsure, $entrantId, 0.40);

        $this->assertSame(1, count_entrant_photos($this->pdo, $entrantId));
        $this->assertCount(1, fetch_entrant_maybe_photos($this->pdo, $entrantId));
    }

    /** A photo that is not live must never appear on a public personal page. */
    public function testNonLivePhotosAreExcluded(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $cls = $this->createClass($eventId, 'Cadet', 'cadet');
        [$entrantId] = $this->createEntrant($eventId, $cls, '11');

        $pending = $this->createPhoto($sessionId, ['status' => 'pending']);
        $this->attribute($pending, $entrantId, 1.0);

        $this->assertSame(0, count_entrant_photos($this->pdo, $entrantId));
        $this->assertSame([], fetch_entrant_photos($this->pdo, $entrantId));
    }

    /** "That's me" promotes the attribution and stops it being offered again. */
    public function testConfirmPromotesAttribution(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $cls = $this->createClass($eventId, 'Cadet', 'cadet');
        [$entrantId] = $this->createEntrant($eventId, $cls, '21');

        $photoId = $this->createPhoto($sessionId, ['status' => 'live']);
        $this->attribute($photoId, $entrantId, 0.55);

        $this->assertSame(0, count_entrant_photos($this->pdo, $entrantId));
        $this->assertTrue(confirm_photo_entrant($this->pdo, $entrantId, $photoId));
        $this->assertSame(1, count_entrant_photos($this->pdo, $entrantId));
        $this->assertCount(0, fetch_entrant_maybe_photos($this->pdo, $entrantId));

        // Second submit is a no-op rather than an error, which is what a
        // double tap on a slow connection produces.
        $this->assertFalse(confirm_photo_entrant($this->pdo, $entrantId, $photoId));
    }

    /** "Not me" keeps the row as evidence but zeroes it and marks it reviewed. */
    public function testRejectKeepsRowButZeroesConfidence(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $cls = $this->createClass($eventId, 'Cadet', 'cadet');
        [$entrantId] = $this->createEntrant($eventId, $cls, '33');

        $photoId = $this->createPhoto($sessionId, ['status' => 'live']);
        $this->attribute($photoId, $entrantId, 0.60);

        $this->assertTrue(reject_photo_entrant($this->pdo, $entrantId, $photoId));

        $stmt = $this->pdo->prepare(
            'SELECT confidence, reviewed_at FROM photo_entrants WHERE entrant_id = ? AND photo_id = ?'
        );
        $stmt->execute([$entrantId, $photoId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'the row is kept so the guess is not re-proposed later');
        $this->assertEquals(0, (float) $row['confidence']);
        $this->assertNotNull($row['reviewed_at']);
        $this->assertSame(0, count_entrant_photos($this->pdo, $entrantId));
    }

    /**
     * Holding one entrant's token must not let a caller attach photos to a
     * different entrant. The scoping lives in the UPDATE's WHERE clause.
     */
    public function testConfirmIsScopedToItsOwnEntrant(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $cadet = $this->createClass($eventId, 'Cadet', 'cadet');
        $senior = $this->createClass($eventId, 'Senior X30', 'senior-x30');

        [$mine] = $this->createEntrant($eventId, $cadet, '7');
        [$theirs] = $this->createEntrant($eventId, $senior, '7');

        $photoId = $this->createPhoto($sessionId, ['status' => 'live']);
        $this->attribute($photoId, $theirs, 0.50);

        // Attempting to confirm the other entrant's proposed photo changes
        // nothing and creates nothing.
        $this->assertFalse(confirm_photo_entrant($this->pdo, $mine, $photoId));
        $this->assertSame(0, count_entrant_photos($this->pdo, $mine));

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM photo_entrants WHERE entrant_id = ?');
        $stmt->execute([$mine]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    /** The session breakdown groups an entrant's photos by session. */
    public function testSessionBreakdown(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $heat = $this->createSession($eventId, ['name' => 'Heat 1', 'slug' => 'heat-1']);
        $final = $this->createSession($eventId, ['name' => 'Final', 'slug' => 'final']);
        $cls = $this->createClass($eventId, 'Cadet', 'cadet');
        [$entrantId] = $this->createEntrant($eventId, $cls, '5');

        $this->attribute($this->createPhoto($heat, ['status' => 'live']), $entrantId, 1.0);
        $this->attribute($this->createPhoto($heat, ['status' => 'live']), $entrantId, 1.0);
        $this->attribute($this->createPhoto($final, ['status' => 'live']), $entrantId, 1.0);

        $breakdown = fetch_entrant_session_breakdown($this->pdo, $entrantId);
        $counts = [];
        foreach ($breakdown as $row) {
            $counts[$row['session_name']] = $row['photo_count'];
        }

        $this->assertSame(2, $counts['Heat 1'] ?? null);
        $this->assertSame(1, $counts['Final'] ?? null);
    }

    /** Share tokens are unguessable and unique. */
    public function testShareTokensAreDistinctAndWellFormed(): void {
        require_once APP_ROOT . '/app/lib/entrants.php';

        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $token = generate_entrant_share_token();
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $token);
            $this->assertArrayNotHasKey($token, $seen, 'share tokens must not repeat');
            $seen[$token] = true;
        }
    }
}

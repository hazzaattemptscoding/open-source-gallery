<?php
/**
 * Covers the two gaps found when a real MySQL install failed on migration 016.
 *
 * 1. Nothing derived classes and entrants from an entry list. Migration 016
 *    backfilled the lists that already existed and that was all, so any event
 *    created afterwards imported its entries cleanly and then no kart number
 *    found a single photo. A silent failure of the feature the project is for.
 *
 * 2. The migration minted share tokens in SQL, as SUBSTRING(MD5(UUID()), 1, 16).
 *    MySQL's UUID() is a timestamp plus a MAC address, so that token is a
 *    deterministic function of guessable inputs rather than a random number,
 *    while looking exactly like the random_bytes() tokens beside it. Tokens are
 *    now left NULL by the migration and minted in PHP.
 */

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/entrants.php';
require_once __DIR__ . '/../../app/lib/entries_import.php';

final class EntrantSyncTest extends TestCase {

    private function makeEvent(string $slug = 'test-event'): int {
        $this->pdo->prepare(
            "INSERT INTO events (title, slug, event_date, is_published, price_single_pence)
             VALUES ('Test Event', ?, '2026-08-01', 1, 500)"
        )->execute([$slug]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param list<array{0:string,1:string,2:string}> $rows kart, driver, class */
    private function addEntries(int $eventId, array $rows): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO event_entries (event_id, kart_number, driver_name, class) VALUES (?, ?, ?, ?)'
        );
        foreach ($rows as [$kart, $driver, $class]) {
            $stmt->execute([$eventId, $kart, $driver, $class]);
        }
    }

    // ---------------------------------------------------------------
    // sync_event_entrants()
    // ---------------------------------------------------------------

    public function testEntriesBecomeSearchableEntrants(): void {
        $eventId = $this->makeEvent();
        $this->addEntries($eventId, [
            ['7',  'A Driver', 'Cadet'],
            ['12', 'B Driver', 'Cadet'],
            ['7',  'C Driver', 'Senior X30'],
        ]);

        $result = sync_event_entrants($this->pdo, $eventId);

        $this->assertSame(2, $result['classes'], 'Cadet and Senior X30');
        $this->assertSame(3, $result['entrants']);

        $found = find_entrants_by_number($this->pdo, $eventId, '7');
        $this->assertCount(2, $found, '#7 exists in two classes and must disambiguate, not collide');
    }

    /** The composite key is the whole point: #7 Cadet is not #7 Senior X30. */
    public function testSameNumberInTwoClassesGetsTwoIdentities(): void {
        $eventId = $this->makeEvent();
        $this->addEntries($eventId, [
            ['7', 'A Driver', 'Cadet'],
            ['7', 'C Driver', 'Senior X30'],
        ]);
        sync_event_entrants($this->pdo, $eventId);

        $found = find_entrants_by_number($this->pdo, $eventId, '7');
        $tokens = array_column($found, 'share_token');

        $this->assertCount(2, array_unique($tokens), 'two children must not share one personal page');
    }

    /**
     * Two spellings of one class are one class.
     *
     * This is the case that aborted the migration during development: a
     * DISTINCT on the raw text treats them as two, and both then hit
     * UNIQUE (event_id, slug).
     */
    public function testClassSpellingVariantsCollapseToOneClass(): void {
        $eventId = $this->makeEvent();
        $this->addEntries($eventId, [
            ['7',  'A Driver', 'Junior X30'],
            ['12', 'B Driver', 'Junior/X30'],
        ]);

        $result = sync_event_entrants($this->pdo, $eventId);

        $this->assertSame(1, $result['classes'], '"Junior X30" and "Junior/X30" are one class typed two ways');
        $this->assertSame(2, $result['entrants']);
    }

    /** A number with no class is not an identity, so it is skipped, not guessed. */
    public function testEntriesWithNoClassAreSkipped(): void {
        $eventId = $this->makeEvent();
        $this->addEntries($eventId, [
            ['7',  'A Driver', ''],
            ['12', 'B Driver', 'Cadet'],
        ]);

        $result = sync_event_entrants($this->pdo, $eventId);

        $this->assertSame(1, $result['entrants'], 'only the row that names a class becomes an identity');
    }

    public function testResyncIsIdempotent(): void {
        $eventId = $this->makeEvent();
        $this->addEntries($eventId, [['7', 'A Driver', 'Cadet']]);

        sync_event_entrants($this->pdo, $eventId);
        $second = sync_event_entrants($this->pdo, $eventId);

        $this->assertSame(0, $second['entrants'], 'a re-import must not duplicate identities');
        $this->assertSame(0, $second['classes']);
    }

    /**
     * A share token is a public URL somebody has posted into a group chat.
     * Re-importing the entry list must not revoke it.
     */
    public function testResyncPreservesExistingShareTokens(): void {
        $eventId = $this->makeEvent();
        $this->addEntries($eventId, [['7', 'A Driver', 'Cadet']]);
        sync_event_entrants($this->pdo, $eventId);

        $before = $this->pdo->query('SELECT share_token FROM entrants')->fetchColumn();

        $this->addEntries($eventId, [['12', 'B Driver', 'Cadet']]);
        sync_event_entrants($this->pdo, $eventId);

        $after = $this->pdo->query('SELECT share_token FROM entrants ORDER BY id ASC')->fetchColumn();
        $this->assertSame($before, $after, 'an existing personal-page link must survive a re-import');
    }

    /** The import path itself must derive identities, not just save rows. */
    public function testSaveEventEntriesDerivesIdentities(): void {
        $eventId = $this->makeEvent();

        $result = save_event_entries($this->pdo, $eventId, [
            ['kart_number' => '7',  'driver_name' => 'A Driver', 'class' => 'Cadet'],
            ['kart_number' => '12', 'driver_name' => 'B Driver', 'class' => 'Cadet'],
        ], true);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame(2, $result['entrants_created'], 'importing an entry list must make it searchable');
        $this->assertSame(1, $result['classes_created']);

        $this->assertCount(1, find_entrants_by_number($this->pdo, $eventId, '7'));
    }

    // ---------------------------------------------------------------
    // mint_missing_entrant_share_tokens()
    // ---------------------------------------------------------------

    /** Inserts an entrant with no token, the shape migration 016 leaves behind. */
    private function makeTokenlessEntrant(int $eventId, string $number): int {
        $this->pdo->prepare(
            "INSERT INTO classes (event_id, name, slug, sort_order) VALUES (?, 'Cadet', 'cadet', 0)"
        )->execute([$eventId]);
        $classId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            "INSERT INTO entrants (event_id, class_id, number, driver_name, team, share_token)
             VALUES (?, ?, ?, 'A Driver', '', NULL)"
        )->execute([$eventId, $classId, $number]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testTokenlessEntrantsGetATokenMinted(): void {
        $eventId = $this->makeEvent();
        $id = $this->makeTokenlessEntrant($eventId, '7');

        $minted = mint_missing_entrant_share_tokens($this->pdo);

        $this->assertSame(1, $minted);

        $token = $this->pdo->query("SELECT share_token FROM entrants WHERE id = $id")->fetchColumn();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $token);
    }

    /** The gap between backfill and mint must fail closed, not open. */
    public function testAnEntrantWithNoTokenCannotBeReached(): void {
        $eventId = $this->makeEvent();
        $this->makeTokenlessEntrant($eventId, '7');

        $this->assertNull(find_entrant_by_token($this->pdo, ''), 'an empty token must never resolve');
        $this->assertNull(find_entrant_by_token($this->pdo, '0000000000000000'));
    }

    /**
     * A search landing between the migration and the first cron run must still
     * produce a working link, not one ending in an empty token.
     */
    public function testSearchMintsATokenRatherThanReturningABrokenLink(): void {
        $eventId = $this->makeEvent();
        $this->makeTokenlessEntrant($eventId, '7');

        $found = find_entrants_by_number($this->pdo, $eventId, '7');

        $this->assertCount(1, $found);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{16}$/',
            $found[0]['share_token'],
            'a match returned to the find page must be linkable'
        );
    }

    public function testMintingIsIdempotent(): void {
        $eventId = $this->makeEvent();
        $this->makeTokenlessEntrant($eventId, '7');

        mint_missing_entrant_share_tokens($this->pdo);
        $token = $this->pdo->query('SELECT share_token FROM entrants')->fetchColumn();

        $this->assertSame(0, mint_missing_entrant_share_tokens($this->pdo), 'nothing left to mint');
        $this->assertSame($token, $this->pdo->query('SELECT share_token FROM entrants')->fetchColumn(),
            'a second run must not reissue a token that has already been shared');
    }

    /** Tokens must be unpredictable, which is the whole reason they left SQL. */
    public function testMintedTokensAreDistinct(): void {
        $eventId = $this->makeEvent();
        $this->pdo->prepare(
            "INSERT INTO classes (event_id, name, slug, sort_order) VALUES (?, 'Cadet', 'cadet', 0)"
        )->execute([$eventId]);
        $classId = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO entrants (event_id, class_id, number, driver_name, team, share_token)
             VALUES (?, ?, ?, '', '', NULL)"
        );
        for ($i = 1; $i <= 50; $i++) {
            $stmt->execute([$eventId, $classId, (string) $i]);
        }

        $this->assertSame(50, mint_missing_entrant_share_tokens($this->pdo));

        $tokens = $this->pdo->query('SELECT share_token FROM entrants')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(50, array_unique($tokens));
    }

    // ---------------------------------------------------------------
    // The migration must not mint tokens in SQL again
    // ---------------------------------------------------------------

    public function testMigration016GeneratesNoTokensInSql(): void {
        foreach (['016_add_entrant_identity.sql', '016_add_entrant_identity.sqlite.sql'] as $file) {
            $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/' . $file);

            // Comment lines explain why these are absent, so only code counts.
            $code = implode("\n", array_filter(
                explode("\n", $sql),
                static fn(string $line): bool => !str_starts_with(ltrim($line), '--')
            ));

            foreach (['MD5', 'UUID(', 'RANDOMBLOB'] as $banned) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $banned,
                    $code,
                    "$file must not mint a share token in SQL: a bearer token protecting a child's photo page comes from random_bytes() or it does not exist"
                );
            }
        }
    }
}

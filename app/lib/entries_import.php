<?php
/**
 * CSV import for event_entries (the grid/entry list for one event).
 *
 * Why this exists: event_entries is read by the admin tagging screen (kart
 * number to driver/class lookup) and by the public kart filter, but until
 * now the only thing that ever wrote to it was the dev seeder. On a real
 * install the table was permanently empty, so both of those features
 * silently did nothing. Organisers publish entry lists as spreadsheets, so
 * CSV is the format that actually arrives.
 *
 * Parsing is kept separate from the database write so the awkward part (real
 * organiser CSVs: BOMs, header rows in any order, blank lines, duplicates)
 * can be exercised without a database.
 */

declare(strict_types=1);

// Needed by save_event_entries(): an import is not finished until the searchable
// identities have been derived from it. See sync_event_entrants().
require_once __DIR__ . '/entrants.php';

/** Column limits mirror migrations/001_initial_schema.sql. */
const ENTRY_MAX_KART = 8;
const ENTRY_MAX_DRIVER = 120;
const ENTRY_MAX_CLASS = 80;

/**
 * Parse an entry-list CSV into rows ready for insertion.
 *
 * Accepts either a header row naming the columns (in any order, so
 * "Driver,Kart,Class" works) or, with no recognisable header, positional
 * kart,driver,class. A row needs a kart number to be useful, since that is
 * the key the tagging screen and the public filter look up; rows without one
 * are reported rather than silently dropped.
 *
 * Duplicates are resolved on (kart_number, class), matching the table's
 * unique key: the same kart legitimately races in two classes, so those are
 * distinct entries, but a repeat of the same pair is not.
 *
 * @param string $csv Raw CSV text.
 * @return array{rows: list<array{kart_number: string, driver_name: string, class: string}>, errors: list<string>, duplicates: int}
 */
function parse_event_entries_csv(string $csv): array
{
    $rows = [];
    $errors = [];
    $duplicates = 0;
    $seen = [];

    // Strip a UTF-8 BOM: Excel writes one, and it would otherwise become part
    // of the first header cell and stop that column being recognised.
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
    $csv = str_replace(["\r\n", "\r"], "\n", $csv);

    $lines = explode("\n", $csv);
    $map = null;
    $lineNumber = 0;

    foreach ($lines as $line) {
        $lineNumber++;

        if (trim($line) === '') {
            continue;
        }

        $cells = str_getcsv($line);
        $cells = array_map(static fn($c) => trim((string)$c), $cells);

        // First non-blank line: use it as a header if it names the columns,
        // otherwise fall back to positional and re-process it as data.
        if ($map === null) {
            $map = detect_entry_columns($cells);
            if ($map !== null) {
                continue;
            }
            $map = ['kart' => 0, 'driver' => 1, 'class' => 2];
        }

        $kart = $map['kart'] !== null ? ($cells[$map['kart']] ?? '') : '';
        $driver = $map['driver'] !== null ? ($cells[$map['driver']] ?? '') : '';
        $class = $map['class'] !== null ? ($cells[$map['class']] ?? '') : '';

        if ($kart === '') {
            $errors[] = "Line {$lineNumber}: no kart number, skipped.";
            continue;
        }

        if (mb_strlen($kart) > ENTRY_MAX_KART) {
            $errors[] = "Line {$lineNumber}: kart number \"{$kart}\" is longer than " . ENTRY_MAX_KART . " characters, skipped.";
            continue;
        }

        // Over-long names and classes are trimmed rather than dropped: losing
        // the whole entry over a long name would be worse than storing it cut.
        if (mb_strlen($driver) > ENTRY_MAX_DRIVER) {
            $driver = mb_substr($driver, 0, ENTRY_MAX_DRIVER);
            $errors[] = "Line {$lineNumber}: driver name shortened to fit.";
        }
        if (mb_strlen($class) > ENTRY_MAX_CLASS) {
            $class = mb_substr($class, 0, ENTRY_MAX_CLASS);
            $errors[] = "Line {$lineNumber}: class shortened to fit.";
        }

        $key = $kart . "\0" . $class;
        if (isset($seen[$key])) {
            $duplicates++;
            continue;
        }
        $seen[$key] = true;

        $rows[] = [
            'kart_number' => $kart,
            'driver_name' => $driver,
            'class' => $class,
        ];
    }

    return ['rows' => $rows, 'errors' => $errors, 'duplicates' => $duplicates];
}

/**
 * Work out which column holds what from a header row.
 *
 * Returns null when the row does not look like a header, which is the signal
 * to treat it as data under positional column order.
 *
 * @param list<string> $cells
 * @return array{kart: int|null, driver: int|null, class: int|null}|null
 */
function detect_entry_columns(array $cells): ?array
{
    $map = ['kart' => null, 'driver' => null, 'class' => null];

    foreach ($cells as $i => $cell) {
        $name = strtolower(preg_replace('/[^a-z]/i', '', $cell) ?? '');
        if ($name === '') {
            continue;
        }

        if ($map['kart'] === null && (str_contains($name, 'kart') || str_contains($name, 'number') || $name === 'no' || $name === 'num')) {
            $map['kart'] = $i;
        } elseif ($map['driver'] === null && (str_contains($name, 'driver') || str_contains($name, 'name'))) {
            $map['driver'] = $i;
        } elseif ($map['class'] === null && (str_contains($name, 'class') || str_contains($name, 'category'))) {
            $map['class'] = $i;
        }
    }

    // A header has to at least identify the kart column to be worth trusting.
    return $map['kart'] === null ? null : $map;
}

/**
 * Write parsed entries for one event.
 *
 * Replace mode clears the event's existing entries first, which is the usual
 * case: organisers reissue a corrected list rather than a delta. Append mode
 * keeps what is there and skips anything already present.
 *
 * Existing (kart, class) pairs are read and filtered in PHP rather than
 * relying on INSERT ... ON DUPLICATE KEY / ON CONFLICT, because those spell
 * differently on MySQL and SQLite and this codebase supports both.
 *
 * @param list<array{kart_number: string, driver_name: string, class: string}> $rows
 * @return array{inserted: int, skipped: int}
 */
function save_event_entries(PDO $pdo, int $eventId, array $rows, bool $replace): array
{
    $inserted = 0;
    $skipped = 0;

    // Only own the transaction if there isn't one already. beginTransaction()
    // throws when nested, and commit()/rollBack() on somebody else's
    // transaction would end it early. Same pattern as release_credit().
    $ownTransaction = !$pdo->inTransaction();

    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        if ($replace) {
            $pdo->prepare('DELETE FROM event_entries WHERE event_id = ?')->execute([$eventId]);
            $existing = [];
        } else {
            $stmt = $pdo->prepare('SELECT kart_number, class FROM event_entries WHERE event_id = ?');
            $stmt->execute([$eventId]);
            $existing = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[$row['kart_number'] . "\0" . $row['class']] = true;
            }
        }

        $insert = $pdo->prepare('INSERT INTO event_entries (event_id, kart_number, driver_name, class) VALUES (?, ?, ?, ?)');
        foreach ($rows as $row) {
            $key = $row['kart_number'] . "\0" . $row['class'];
            if (isset($existing[$key])) {
                $skipped++;
                continue;
            }
            $existing[$key] = true;
            $insert->execute([$eventId, $row['kart_number'], $row['driver_name'], $row['class']]);
            $inserted++;
        }

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    /*
     * Derive the searchable identities from what was just imported.
     *
     * event_entries alone does nothing for a visitor: the find-me flow searches
     * classes and entrants, which are a different shape. Without this an entry
     * list imports cleanly and then no kart number finds a single photo, which
     * is a silent failure of the one feature this whole project is for.
     *
     * Outside the transaction above deliberately. The entries are saved and
     * that is the operation the admin asked for; if deriving identities hits a
     * problem it must not roll back the import. sync_event_entrants() is
     * additive and safe to re-run, so the recovery is to import again.
     */
    $derived = sync_event_entrants($pdo, $eventId);

    return [
        'inserted' => $inserted,
        'skipped' => $skipped,
        'classes_created' => $derived['classes'],
        'entrants_created' => $derived['entrants'],
    ];
}

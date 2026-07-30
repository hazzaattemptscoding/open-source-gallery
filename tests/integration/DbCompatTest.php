<?php
/**
 * Covers app/lib/db_compat.php's date helpers. db_date_sub_sql(),
 * db_date_add_sql(), and db_date_format_sql() referenced $_GLOBALS['pdo']
 * (an undefined plain variable, not PHP's $GLOBALS superglobal) instead of
 * taking a PDO argument, so any call would pass null into is_mysql(PDO $pdo)
 * and fatal with a TypeError. Nothing called them, so this was never caught.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/db_compat.php';
require_once __DIR__ . '/../../app/lib/db.php';

final class DbCompatTest extends TestCase {
    public function testDateSubProducesValidSqlOnCurrentDriver(): void {
        $sql = db_date_sub_sql($this->pdo, 'CURRENT_TIMESTAMP', 15, 'minute');
        $stmt = $this->pdo->query("SELECT {$sql}");
        $this->assertNotFalse($stmt->fetchColumn(), 'the generated expression must be valid SQL, not just a string');
    }

    public function testDateSubMinuteIntervalIsActuallyFifteenMinutesNotDays(): void {
        // Regression guard: before 'minute' was added to the unit map, an
        // unrecognized unit silently fell back to DAY/days, so a 15-minute
        // stall check would have compared against 15 days instead.
        $stmt = $this->pdo->query('SELECT ' . db_date_sub_sql($this->pdo, 'NOW()', 15, 'minute'));
        $result = strtotime((string)$stmt->fetchColumn());
        $expected = time() - (15 * 60);

        $this->assertLessThan(60, abs($result - $expected), 'expected roughly "now minus 15 minutes", not 15 days');
    }

    public function testDateAddProducesValidSql(): void {
        $sql = db_date_add_sql($this->pdo, 'CURRENT_TIMESTAMP', 30, 'day');
        $stmt = $this->pdo->query("SELECT {$sql}");
        $this->assertNotFalse($stmt->fetchColumn());
    }

    public function testUpsertSqlInsertsThenUpdatesOnConflict(): void {
        [$sql, $values] = array_values(db_upsert_sql($this->pdo, 'settings', ['skey' => 'test_upsert_key', 'svalue' => 'first'], 'skey'));
        $this->pdo->prepare($sql)->execute($values);

        [$sql, $values] = array_values(db_upsert_sql($this->pdo, 'settings', ['skey' => 'test_upsert_key', 'svalue' => 'second'], 'skey'));
        $this->pdo->prepare($sql)->execute($values);

        $stmt = $this->pdo->prepare('SELECT svalue, COUNT(*) as cnt FROM settings WHERE skey = ? GROUP BY svalue');
        $stmt->execute(['test_upsert_key']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('second', $row['svalue']);
        $this->assertSame(1, (int)$row['cnt'], 'exactly one row should exist after the conflicting upsert, not two');
    }
}

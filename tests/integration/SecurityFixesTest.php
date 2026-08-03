<?php
/**
 * Denial tests for the three findings fixed after the 2026-08-03 audit.
 *
 * Every test here exists because a *passing* test already covered the same
 * function while the bug was present. That is the point: the audit's L3 rule
 * says "fixed" means nothing without a test that fails when the fix is
 * reverted, and the tests that were there did not.
 *
 *   OSG-2026-001  the SSRF guard refused a private URL correctly, and still
 *                 re-resolved the hostname when it made the connection
 *   OSG-2026-002  a truncated response returned ok:true with a short body
 *   OSG-2026-003  a credit's currency was stored and never read again
 *
 * Each test below is written to fail if its fix is undone, not merely to
 * exercise the happy path.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/currency.php';
require_once __DIR__ . '/../../app/lib/credit.php';
require_once __DIR__ . '/../../app/lib/remote_fetch.php';

final class SecurityFixesTest extends TestCase {

    // ---------------------------------------------------------------
    // OSG-2026-001 — SSRF: the validated address must be pinned
    // ---------------------------------------------------------------

    /**
     * The validator has to hand back the port as well as the address.
     *
     * CURLOPT_RESOLVE entries are keyed on host:port. An entry with the wrong
     * port does not apply, and curl resolves the name itself, which is the
     * whole bug. So the port is part of the contract, not incidental.
     */
    public function testValidateUrlReturnsPortForPinning(): void {
        $result = remote_fetch_validate_url('https://example.com/entries.csv');

        $this->assertTrue($result['ok'], 'a public https URL should validate');
        $this->assertArrayHasKey('ip', $result);
        $this->assertArrayHasKey('port', $result, 'the port is needed to build a CURLOPT_RESOLVE entry');
        $this->assertSame(443, $result['port'], 'https with no explicit port is 443');
    }

    public function testValidateUrlDefaultsHttpToPort80(): void {
        $result = remote_fetch_validate_url('http://example.com/entries.csv');

        $this->assertTrue($result['ok']);
        $this->assertSame(80, $result['port']);
    }

    public function testValidateUrlHonoursAnExplicitPort(): void {
        $result = remote_fetch_validate_url('https://example.com:8443/entries.csv');

        $this->assertTrue($result['ok']);
        $this->assertSame(8443, $result['port'], 'pinning the wrong port silently disables the pin');
    }

    /**
     * The fix itself: remote_fetch() must set CURLOPT_RESOLVE.
     *
     * This is a source-level assertion rather than a behavioural one, and that
     * is deliberate. Proving DNS rebinding behaviourally needs a DNS server
     * that answers differently on two consecutive lookups, which is not
     * something an integration test on shared hosting can stand up. What can be
     * pinned mechanically is that the connection is made to the address that
     * was checked, and its absence is exactly what made every other SSRF test
     * in this file pass while the hole was open.
     */
    public function testRemoteFetchPinsTheValidatedAddress(): void {
        $source = file_get_contents(__DIR__ . '/../../app/lib/remote_fetch.php');

        $this->assertStringContainsString(
            'CURLOPT_RESOLVE',
            $source,
            'remote_fetch() must pin the validated address, or curl re-resolves the name and DNS rebinding defeats every check above it'
        );
        $this->assertMatchesRegularExpression(
            '/CURLOPT_RESOLVE\s*=>\s*\["\{\$check\[.host.\]\}:\{\$check\[.port.\]\}:\{\$check\[.ip.\]\}"\]/',
            $source,
            'the pin must use the host, port and IP that remote_fetch_validate_url() just checked'
        );
    }

    /** Loopback, private and link-local addresses stay refused. */
    public function testBlockedRangesAreStillRefused(): void {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '172.16.0.1', '169.254.169.254', '::1'] as $ip) {
            $this->assertTrue(
                remote_fetch_address_is_blocked($ip),
                "$ip must be refused"
            );
        }
        $this->assertFalse(remote_fetch_address_is_blocked('93.184.216.34'), 'a public address must be allowed');
    }

    public function testNonHttpSchemesAreRefused(): void {
        foreach (['file:///etc/passwd', 'gopher://example.com/', 'ftp://example.com/x.csv'] as $url) {
            $result = remote_fetch_validate_url($url);
            $this->assertFalse($result['ok'], "$url must be refused");
        }
    }

    // ---------------------------------------------------------------
    // OSG-2026-002 — a truncated body must not be reported as complete
    // ---------------------------------------------------------------

    /**
     * The write callback has to record that it aborted.
     *
     * Also source-level, and for the same honest reason: reproducing it needs
     * an HTTP server that streams more than REMOTE_FETCH_MAX_BYTES without a
     * Content-Length. What is assertable is the shape of the fix, and the shape
     * is the whole finding: every signal that could be used to infer truncation
     * afterwards reports success, so the callback must say so at the time.
     */
    public function testTruncationIsRecordedRatherThanInferred(): void {
        $source = file_get_contents(__DIR__ . '/../../app/lib/remote_fetch.php');

        $this->assertStringContainsString(
            'use (&$truncated)',
            $source,
            'the write callback must report truncation by reference; body length cannot detect it, because the over-limit chunk is never appended'
        );
        $this->assertStringContainsString(
            'if ($truncated) {',
            $source,
            'the truncation flag must be checked before the body is treated as a document'
        );
        $this->assertStringContainsString(
            'if ($ok === false) {',
            $source,
            'a curl failure that arrived with a partial body is still a failure'
        );

        // Checked on code lines only. The comment above the fix quotes the old
        // guard verbatim to explain what was wrong with it, and a naive
        // whole-file search would match that and fail on the documentation.
        $this->assertSame(
            [],
            self::codeLinesContaining($source, "\$ok === false && \$body === ''"),
            'the old permissive guard let a partial body through as a complete document'
        );
    }

    /**
     * Line numbers where a needle appears outside comments.
     *
     * Deliberately crude: it skips lines starting with *, // or /*, which is
     * enough for the docblock-and-block-comment style used throughout this
     * codebase, and it is not trying to be a PHP parser.
     *
     * @return list<int>
     */
    private static function codeLinesContaining(string $source, string $needle): array {
        $hits = [];
        foreach (explode("\n", $source) as $n => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '*'
                || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            if (str_contains($line, $needle)) {
                $hits[] = $n + 1;
            }
        }
        return $hits;
    }

    // ---------------------------------------------------------------
    // OSG-2026-003 — credit currency must be checked at redemption
    // ---------------------------------------------------------------

    /** Creates an active credit with a balance, ready to spend. */
    private function makeActiveCredit(string $currency, int $pence): array {
        $credit = create_pending_credit($this->pdo, 'buyer@example.com', $pence, $currency, null);
        $this->assertNotNull($credit, 'fixture credit should have been created');

        $this->pdo->prepare(
            "UPDATE credits SET status = 'active', balance_pence = amount_pence WHERE id = ?"
        )->execute([$credit['id']]);

        return $credit;
    }

    /** Creates a pending order to spend against. */
    private function makeOrder(string $currency, int $totalPence): int {
        $this->pdo->prepare(
            "INSERT INTO orders (public_token, email, currency, total_pence, status)
             VALUES (?, ?, ?, ?, 'pending')"
        )->execute([bin2hex(random_bytes(11)), 'buyer@example.com', $currency, $totalPence]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testCreditIsFoundInItsOwnCurrency(): void {
        $credit = $this->makeActiveCredit('GBP', 2000);

        $found = find_spendable_credit($this->pdo, $credit['code'], 'GBP');

        $this->assertNotNull($found, 'a GBP credit must be spendable against a GBP order');
        $this->assertSame(2000, (int) $found['balance_pence']);
    }

    /** The finding: 2000 GBP pence must not turn into 2000 EUR cents. */
    public function testCreditIsNotFoundInAnotherCurrency(): void {
        $credit = $this->makeActiveCredit('GBP', 2000);

        $this->assertNull(
            find_spendable_credit($this->pdo, $credit['code'], 'EUR'),
            'credit sold in GBP must not be spendable against an order priced in EUR'
        );
    }

    public function testCurrencyMatchIsCaseInsensitive(): void {
        $credit = $this->makeActiveCredit('gbp', 2000);

        $this->assertNotNull(
            find_spendable_credit($this->pdo, $credit['code'], 'GBP'),
            'currency comparison must not turn into a casing bug that refuses valid credit'
        );
    }

    public function testSpendRefusesACurrencyMismatchEvenWhenTheCreditIdIsKnown(): void {
        $credit = $this->makeActiveCredit('GBP', 2000);
        $orderId = $this->makeOrder('EUR', 1500);

        $taken = spend_credit($this->pdo, (int) $credit['id'], $orderId, 1500, 'EUR');

        $this->assertSame(0, $taken, 'the currency condition belongs on the UPDATE, not only on the lookup');

        $balance = $this->pdo->query(
            'SELECT balance_pence FROM credits WHERE id = ' . (int) $credit['id']
        )->fetchColumn();
        $this->assertSame(2000, (int) $balance, 'a refused spend must not move the balance');
    }

    public function testSpendStillWorksInTheMatchingCurrency(): void {
        $credit = $this->makeActiveCredit('GBP', 2000);
        $orderId = $this->makeOrder('GBP', 1500);

        $taken = spend_credit($this->pdo, (int) $credit['id'], $orderId, 1500, 'GBP');

        $this->assertSame(1500, $taken, 'the fix must not break the ordinary path');

        $balance = $this->pdo->query(
            'SELECT balance_pence FROM credits WHERE id = ' . (int) $credit['id']
        )->fetchColumn();
        $this->assertSame(500, (int) $balance, 'only the requested amount comes off');
    }

    // ---------------------------------------------------------------
    // config_currency_code() — the reason the mismatch was reachable
    // ---------------------------------------------------------------

    /**
     * Seven call sites read $config['currency']['code'] ?? 'GBP'.
     *
     * config.php stores currency as a bare top-level string, so that is an
     * offset of a string: PHP yields null, ?? swallows it, and the site behaves
     * as GBP whatever the operator configured. A self-hoster in euros was
     * selling credit stamped GBP against orders stamped EUR, which is how the
     * mismatch above became reachable without anybody doing anything unusual.
     */
    public function testConfigCurrencyCodeReadsTheFlatShapeConfigActuallyUses(): void {
        $this->assertSame('EUR', config_currency_code(['currency' => 'EUR']));
        $this->assertSame('USD', config_currency_code(['currency' => 'USD']));
    }

    public function testConfigCurrencyCodeToleratesTheNestedShape(): void {
        $this->assertSame('EUR', config_currency_code(['currency' => ['code' => 'EUR']]));
    }

    public function testConfigCurrencyCodeFallsBackWhenUnset(): void {
        $this->assertSame('GBP', config_currency_code([]));
        $this->assertSame('GBP', config_currency_code(['currency' => '']));
    }

    /** No call site may go back to the offset-of-a-string read. */
    public function testNoCallSiteReadsCurrencyAsANestedOffset(): void {
        $hits = [];
        $root = dirname(__DIR__, 2);

        foreach (['app', 'public'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator("$root/$dir")
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $source = file_get_contents($file->getPathname());
                    // The accessor's own docblock quotes the bad pattern, so
                    // comment lines are excluded rather than the file.
                    foreach (explode("\n", $source) as $n => $line) {
                        $trimmed = ltrim($line);
                        if ($trimmed !== '' && ($trimmed[0] === '*' || str_starts_with($trimmed, '//'))) {
                            continue;
                        }
                        if (str_contains($line, "['currency']['code']")) {
                            $hits[] = $file->getPathname() . ':' . ($n + 1);
                        }
                    }
                }
            }
        }

        $this->assertSame([], $hits, 'use config_currency_code(); a nested read silently returns GBP');
    }
}

<?php
/**
 * Covers a class of bug found while running a full security audit
 * (docs/security-audit-output/security-audit-report.md): several admin
 * mutation functions called csrf_verify() but discarded its boolean
 * return value, so an invalid or missing token never actually blocked
 * anything -- the code looked protected on a casual read but wasn't.
 * Several others never called csrf_verify() at all. Both classes are
 * covered here so a future regression (adding a new mutation without
 * checking the return value, or without checking at all) fails loudly.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/csrf.php';
require_once __DIR__ . '/../../app/controllers/admin/events.php';

final class CsrfProtectionTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'a-real-session-token';
    }

    protected function tearDown(): void {
        unset($_SESSION['csrf_token']);
        parent::tearDown();
    }

    /**
     * Regression guard for the discarded-return-value class of bug:
     * delete_event() (app/controllers/admin/events.php) previously called
     * csrf_verify() without checking its result, so a wrong token still
     * deleted the event. Captures output to confirm the function returns
     * early with a 403 rather than proceeding to the DELETE.
     */
    public function testDeleteEventRejectsWrongCsrfToken(): void {
        $eventId = $this->createEvent();
        $_POST['csrf_token'] = 'wrong-token';

        ob_start();
        delete_event($this->pdo, 1, '127.0.0.1', $eventId);
        $output = ob_get_clean();

        $this->assertStringContainsString('CSRF', $output);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $this->assertSame(1, (int)$stmt->fetchColumn(), 'the event must NOT be deleted when the CSRF token is wrong');
    }

    public function testCsrfVerifyIsOneTimeUse(): void {
        $this->assertTrue(csrf_verify('a-real-session-token'));
        // Same token, second call: must fail, since the first call
        // invalidates it (replay protection).
        $_SESSION['csrf_token'] = 'a-real-session-token';
        $this->assertTrue(csrf_verify('a-real-session-token'));
        $this->assertFalse(csrf_verify('a-real-session-token'));
    }

    public function testCsrfVerifyReusableDoesNotInvalidateToken(): void {
        $_SESSION['csrf_token'] = 'upload-flow-token';
        $this->assertTrue(csrf_verify_reusable('upload-flow-token'));
        // Must still pass a second time -- this is the whole point of the
        // reusable variant (chunked upload: init, N chunks, finalize, all
        // under one embedded token).
        $this->assertTrue(csrf_verify_reusable('upload-flow-token'));
        $this->assertTrue(csrf_verify_reusable('upload-flow-token'));
    }

    public function testCsrfVerifyRejectsEmptySessionToken(): void {
        unset($_SESSION['csrf_token']);
        $this->assertFalse(csrf_verify('anything'));
        $this->assertFalse(csrf_verify_reusable('anything'));
    }
}

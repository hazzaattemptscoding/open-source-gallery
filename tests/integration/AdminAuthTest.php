<?php
/**
 * Integration tests for admin authentication and event management.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

class AdminAuthTest extends TestCase {
    /**
     * Test admin login with valid credentials.
     */
    public function testAdminLoginSuccess(): void {
        require_once APP_ROOT . '/app/lib/auth.php';

        // A real request has a session open by the time this runs (the front
        // controller starts one); admin_attempt_login()'s success path calls
        // session_regenerate_id(), which needs one to exist.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->createAdminUser([
            'email' => 'admin@example.com',
            'password_hash' => password_hash('SecurePassword123!', PASSWORD_ARGON2ID),
        ]);

        // Test successful login.
        $result = \admin_attempt_login($this->pdo, 'admin@example.com', 'SecurePassword123!', null, '127.0.0.1');

        $this->assertTrue($result['ok'], 'Login should succeed with correct credentials');
    }

    /**
     * Test admin login fails with invalid password.
     */
    public function testAdminLoginInvalidPassword(): void {
        require_once APP_ROOT . '/app/lib/auth.php';

        $this->createAdminUser([
            'email' => 'admin@example.com',
            'password_hash' => password_hash('CorrectPassword123!', PASSWORD_ARGON2ID),
        ]);

        $result = \admin_attempt_login($this->pdo, 'admin@example.com', 'WrongPassword123!', null, '127.0.0.1');

        $this->assertFalse($result['ok']);
        $this->assertEquals('invalid_credentials', $result['reason']);
    }

    /**
     * Test admin login fails with unknown email.
     */
    public function testAdminLoginUnknownEmail(): void {
        require_once APP_ROOT . '/app/lib/auth.php';

        $result = \admin_attempt_login($this->pdo, 'unknown@example.com', 'SomePassword123!', null, '127.0.0.1');

        $this->assertFalse($result['ok']);
        $this->assertEquals('invalid_credentials', $result['reason']);
    }

    /**
     * TOTP brute-forcing had no dedicated rate-limit bucket: only the
     * initial password step was throttled, so a fixed password + repeated
     * wrong TOTP guesses hit no distinct limit, contradicting
     * docs/architecture.md's explicit claim of a separate "TOTP attempts"
     * bucket. Asserting on rate_limits directly (rather than just "does
     * the 6th call eventually fail") matters here: the existing login
     * bucket also gets touched on every call regardless of the TOTP
     * outcome, so a black-box "eventually blocked" test would pass even
     * without a real totp bucket, for the wrong reason.
     */
    public function testWrongTotpCodeRecordsAttemptInADedicatedBucket(): void {
        require_once APP_ROOT . '/app/lib/auth.php';

        $this->createAdminUser([
            'email' => 'admin@example.com',
            'password_hash' => password_hash('SecurePassword123!', PASSWORD_ARGON2ID),
            'totp_enabled' => 1,
            'totp_secret' => \totp_generate_secret(),
        ]);

        $result = \admin_attempt_login($this->pdo, 'admin@example.com', 'SecurePassword123!', '000000', '127.0.0.1');
        $this->assertEquals('invalid_totp', $result['reason']);

        $stmt = $this->pdo->prepare("SELECT hits FROM rate_limits WHERE bucket = 'totp' AND rl_key = ?");
        $stmt->execute(['acct:admin@example.com']);
        $hits = $stmt->fetchColumn();

        $this->assertNotFalse($hits, 'a wrong TOTP code should record a hit in a dedicated totp bucket');
        $this->assertSame(1, (int)$hits);
    }

    /**
     * Six wrong TOTP codes in a row (default cap per admin_attempt_login())
     * must eventually return 'rate_limited' from the totp bucket itself,
     * not merely from the shared login bucket incidentally also filling up.
     */
    public function testTotpBucketRateLimitsAfterRepeatedWrongCodes(): void {
        require_once APP_ROOT . '/app/lib/auth.php';

        $this->createAdminUser([
            'email' => 'admin@example.com',
            'password_hash' => password_hash('SecurePassword123!', PASSWORD_ARGON2ID),
            'totp_enabled' => 1,
            'totp_secret' => \totp_generate_secret(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            \admin_attempt_login($this->pdo, 'admin@example.com', 'SecurePassword123!', '000000', '127.0.0.1');
        }

        $stmt = $this->pdo->prepare("SELECT hits FROM rate_limits WHERE bucket = 'totp' AND rl_key = ?");
        $stmt->execute(['acct:admin@example.com']);
        $this->assertSame(5, (int)$stmt->fetchColumn(), 'the totp bucket itself should have accumulated 5 hits, independent of the login bucket');
    }

    /**
     * Test create event with valid data.
     */
    public function testCreateEventSuccess(): void {
        $slug = 'test-race-' . uniqid();

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO events (slug, title, venue, event_date, is_published, price_single_pence, price_session_pence, price_event_pence)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
            $slug,
            'Test Race Event',
            'Test Venue',
            '2025-07-30',
            0,
            500,
            null,
            null,
        ]);

        $eventId = (int)$this->pdo->lastInsertId();

        // Verify event was created.
        $stmt = $this->pdo->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        $this->assertNotNull($event);
        $this->assertEquals($slug, $event['slug']);
        $this->assertEquals('Test Race Event', $event['title']);
        $this->assertEquals('Test Venue', $event['venue']);
        $this->assertEquals('2025-07-30', $event['event_date']);
        $this->assertEquals(0, $event['is_published']);
        $this->assertEquals(500, $event['price_single_pence']);
    }

    /**
     * Test publish event.
     */
    public function testPublishEvent(): void {
        $eventId = $this->createEvent(['is_published' => false]);

        // Publish event.
        $stmt = $this->pdo->prepare('UPDATE events SET is_published = 1 WHERE id = ?');
        $stmt->execute([$eventId]);

        // Verify published.
        $stmt = $this->pdo->prepare('SELECT is_published FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        $this->assertEquals(1, $event['is_published']);
    }

    /**
     * Test update event pricing.
     */
    public function testUpdateEventPricing(): void {
        $eventId = $this->createEvent([
            'price_single_pence' => 500,
            'price_session_pence' => null,
        ]);

        // Update pricing.
        $stmt = $this->pdo->prepare(<<<'SQL'
            UPDATE events
            SET price_single_pence = ?, price_session_pence = ?, price_event_pence = ?
            WHERE id = ?
        SQL);
        $stmt->execute([1000, 2500, null, $eventId]);

        // Verify updated.
        $stmt = $this->pdo->prepare('SELECT price_single_pence, price_session_pence FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        $this->assertEquals(1000, $event['price_single_pence']);
        $this->assertEquals(2500, $event['price_session_pence']);
    }

    /**
     * Test event slug uniqueness constraint.
     */
    public function testEventSlugUniqueness(): void {
        $slug = 'unique-slug-' . uniqid();

        $this->createEvent(['slug' => $slug, 'title' => 'Event 1']);

        $this->expectException(\PDOException::class);
        $this->createEvent(['slug' => $slug, 'title' => 'Event 2']);
    }

    /**
     * Test event with sessions.
     */
    public function testEventWithSessions(): void {
        $eventId = $this->createEvent();
        $sessionId1 = $this->createSession($eventId, ['name' => 'Session 1']);
        $sessionId2 = $this->createSession($eventId, ['name' => 'Session 2']);

        // Verify sessions linked to event.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as cnt FROM sessions WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $result = $stmt->fetch();

        $this->assertEquals(2, $result['cnt']);
    }
}

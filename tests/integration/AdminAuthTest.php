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

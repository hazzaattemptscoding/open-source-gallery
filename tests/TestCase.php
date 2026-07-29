<?php
/**
 * Base test case for integration tests.
 * Provides database fixtures, transaction isolation, and common assertions.
 */

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase {
    protected PDO $pdo;

    protected function setUp(): void {
        parent::setUp();
        $this->pdo = $GLOBALS['test_pdo'];

        // Start transaction for test isolation.
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void {
        // Roll back transaction after each test.
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    /**
     * Create a test event.
     */
    protected function createEvent(array $overrides = []): int {
        $data = array_merge([
            'title' => 'Test Event',
            'slug' => 'test-event-' . uniqid(),
            'event_date' => date('Y-m-d'),
            'is_published' => false,
            'price_single_pence' => 100,
            'price_session_pence' => null,
            'price_event_pence' => null,
        ], $overrides);

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO events (title, slug, event_date, is_published, price_single_pence, price_session_pence, price_event_pence)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['event_date'],
            (int)$data['is_published'],
            $data['price_single_pence'],
            $data['price_session_pence'],
            $data['price_event_pence'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Create a test session.
     */
    protected function createSession(int $eventId, array $overrides = []): int {
        $data = array_merge([
            'name' => 'Test Session',
            'slug' => 'test-session-' . uniqid(),
            'sort_order' => 1,
        ], $overrides);

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO sessions (event_id, name, slug, sort_order)
            VALUES (?, ?, ?, ?)
        SQL);

        $stmt->execute([
            $eventId,
            $data['name'],
            $data['slug'],
            $data['sort_order'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Create a test photo. photos.event_id is denormalised from the session
     * for filter speed (see migrations/001_initial_schema.sql), so it's
     * looked up from $sessionId rather than passed separately.
     */
    protected function createPhoto(int $sessionId, array $overrides = []): int {
        $stmt = $this->pdo->prepare('SELECT event_id FROM sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $eventId = $stmt->fetchColumn();
        if ($eventId === false) {
            throw new \RuntimeException("createPhoto: no session with id $sessionId (create it with createSession() first)");
        }

        $data = array_merge([
            'public_token' => substr(bin2hex(random_bytes(6)), 0, 12),
            'original_filename' => 'test.jpg',
            'width' => 1920,
            'height' => 1080,
            'hires_size_bytes' => 2048000,
            'status' => 'live',
            'view_count' => 0,
        ], $overrides);

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO photos (event_id, session_id, public_token, original_filename, width, height, hires_size_bytes, status, view_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
            (int)$eventId,
            $sessionId,
            $data['public_token'],
            $data['original_filename'],
            $data['width'],
            $data['height'],
            $data['hires_size_bytes'],
            $data['status'],
            $data['view_count'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Create a test admin user. Single-admin model (one row in admin_users
     * per docs/architecture.md) — there is no separate "users" table.
     */
    protected function createAdminUser(array $overrides = []): int {
        $data = array_merge([
            'email' => 'admin-' . uniqid() . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_ARGON2ID),
            'totp_enabled' => 0,
            'totp_secret' => null,
        ], $overrides);

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO admin_users (email, password_hash, totp_enabled, totp_secret)
            VALUES (?, ?, ?, ?)
        SQL);

        $stmt->execute([
            $data['email'],
            $data['password_hash'],
            (int)$data['totp_enabled'],
            $data['totp_secret'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}

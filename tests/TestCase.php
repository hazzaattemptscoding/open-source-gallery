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
     * Create a test photo.
     */
    protected function createPhoto(int $sessionId, array $overrides = []): int {
        $data = array_merge([
            'public_token' => 'token-' . uniqid(),
            'original_filename' => 'test.jpg',
            'width' => 1920,
            'height' => 1080,
            'hires_size_bytes' => 2048000,
            'status' => 'live',
            'view_count' => 0,
        ], $overrides);

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO photos (session_id, public_token, original_filename, width, height, hires_size_bytes, status, view_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
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
     * Create test user.
     */
    protected function createUser(array $overrides = []): int {
        $data = array_merge([
            'username' => 'testuser-' . uniqid(),
            'email' => 'test-' . uniqid() . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_ARGON2ID),
            'is_admin' => false,
            'totp_secret' => null,
        ], $overrides);

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (username, email, password_hash, is_admin, totp_secret)
            VALUES (?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
            $data['username'],
            $data['email'],
            $data['password_hash'],
            (int)$data['is_admin'],
            $data['totp_secret'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}

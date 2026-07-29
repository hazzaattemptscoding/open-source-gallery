<?php
/**
 * Integration tests for bulk operations: tagging, pricing, status changes.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

class BulkOperationsTest extends TestCase {
    /**
     * Test bulk tagging photos with multi-row INSERT.
     */
    public function testBulkTagPhotos(): void {
        // Create test data.
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoIds = [
            $this->createPhoto($sessionId),
            $this->createPhoto($sessionId),
            $this->createPhoto($sessionId),
        ];

        // Load bulk operations library.
        require_once APP_ROOT . '/app/lib/bulk.php';

        // Tag photos.
        $tags = [
            'kart' => '123',
            'driver' => 'Test Driver',
            'class' => 'Formula K',
        ];

        $updated = \bulk_tag_photos($this->pdo, $photoIds, $tags);

        // Assert all photos tagged.
        $this->assertEquals(3, $updated, 'Should tag 3 photos');

        // Verify tags in database.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as cnt FROM photo_tags WHERE photo_id = ?');
        foreach ($photoIds as $photoId) {
            $stmt->execute([$photoId]);
            $row = $stmt->fetch();
            $this->assertEquals(1, $row['cnt'], "Photo $photoId should have 1 tag entry");
        }
    }

    /**
     * Test bulk tagging is idempotent (ON DUPLICATE KEY UPDATE).
     */
    public function testBulkTagPhotosIdempotent(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoIds = [$this->createPhoto($sessionId)];

        require_once APP_ROOT . '/app/lib/bulk.php';

        $tags = ['kart' => '100', 'driver' => 'John', 'class' => 'KF'];

        // First tagging.
        $updated1 = \bulk_tag_photos($this->pdo, $photoIds, $tags);
        $this->assertEquals(1, $updated1);

        // Second tagging (should update, not insert).
        $updated2 = \bulk_tag_photos($this->pdo, $photoIds, $tags);
        $this->assertEquals(1, $updated2);

        // Verify only 1 tag entry exists.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as cnt FROM photo_tags WHERE photo_id = ?');
        $stmt->execute($photoIds);
        $row = $stmt->fetch();
        $this->assertEquals(1, $row['cnt']);
    }

    /**
     * Test bulk update photo prices.
     */
    public function testBulkUpdatePrices(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoIds = [
            $this->createPhoto($sessionId),
            $this->createPhoto($sessionId),
        ];

        require_once APP_ROOT . '/app/lib/bulk.php';

        $newPrice = 500; // 5 pounds
        $updated = \bulk_update_prices($this->pdo, $photoIds, $newPrice);

        $this->assertEquals(2, $updated);

        // Verify prices updated.
        $stmt = $this->pdo->prepare('SELECT price_pence FROM photos WHERE id = ? LIMIT 1');
        foreach ($photoIds as $photoId) {
            $stmt->execute([$photoId]);
            $row = $stmt->fetch();
            $this->assertEquals($newPrice, $row['price_pence']);
        }
    }

    /**
     * Test bulk change photo status.
     */
    public function testBulkChangeStatus(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoIds = [
            $this->createPhoto($sessionId, ['status' => 'draft']),
            $this->createPhoto($sessionId, ['status' => 'draft']),
        ];

        require_once APP_ROOT . '/app/lib/bulk.php';

        $changed = \bulk_change_status($this->pdo, $photoIds, 'live');

        $this->assertEquals(2, $changed);

        // Verify status changed.
        $stmt = $this->pdo->prepare('SELECT status FROM photos WHERE id = ? LIMIT 1');
        foreach ($photoIds as $photoId) {
            $stmt->execute([$photoId]);
            $row = $stmt->fetch();
            $this->assertEquals('live', $row['status']);
        }
    }

    /**
     * Test bulk delete photos.
     */
    public function testBulkDeletePhotos(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoIds = [
            $this->createPhoto($sessionId),
            $this->createPhoto($sessionId),
        ];

        require_once APP_ROOT . '/app/lib/bulk.php';

        $deleted = \bulk_delete_photos($this->pdo, $photoIds);

        $this->assertEquals(2, $deleted);

        // Verify deleted.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as cnt FROM photos WHERE id IN (?, ?)');
        $stmt->execute($photoIds);
        $row = $stmt->fetch();
        $this->assertEquals(0, $row['cnt']);
    }

    /**
     * Test empty bulk operations return 0.
     */
    public function testBulkOperationsWithEmptyInput(): void {
        require_once APP_ROOT . '/app/lib/bulk.php';

        $this->assertEquals(0, \bulk_tag_photos($this->pdo, [], ['kart' => '100']));
        $this->assertEquals(0, \bulk_update_prices($this->pdo, [], 500));
        $this->assertEquals(0, \bulk_delete_photos($this->pdo, []));
        $this->assertEquals(0, \bulk_change_status($this->pdo, [], 'live'));
    }
}

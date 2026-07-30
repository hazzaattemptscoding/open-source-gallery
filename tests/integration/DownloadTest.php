<?php
/**
 * Covers order-item entitlement expansion (which photos a paid order item
 * actually grants access to) and download-link creation. Both regressed the
 * same way: correct for one order-item shape but silently broken for
 * another, and nothing caught it because no test exercised every shape.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/orders.php';
require_once __DIR__ . '/../../app/controllers/public/download.php';

final class DownloadTest extends TestCase {
    /**
     * Mirrors create_order()'s own insert shape for a session-bundle line
     * (session_id set, photo_id and event_id both null) without going
     * through create_order() itself, since that needs a full cart-line
     * shape this test doesn't otherwise care about.
     */
    private function createOrderItem(int $orderId, array $overrides = []): int {
        $data = array_merge([
            'item_type' => 'session_bundle',
            'photo_id' => null,
            'session_id' => null,
            'event_id' => null,
            'description' => 'Test item',
            'unit_price_pence' => 2000,
        ], $overrides);

        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, item_type, photo_id, session_id, event_id, description, unit_price_pence, line_total_pence)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderId, $data['item_type'], $data['photo_id'], $data['session_id'], $data['event_id'],
            $data['description'], $data['unit_price_pence'], $data['unit_price_pence'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function createPaidOrder(): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (public_token, email, currency, total_pence, status)
             VALUES (?, 'buyer@example.com', 'GBP', 2000, 'paid')"
        );
        $stmt->execute([bin2hex(random_bytes(11))]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testResolveOrderItemPhotoIdsExpandsSessionBundle(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoId1 = $this->createPhoto($sessionId);
        $photoId2 = $this->createPhoto($sessionId);
        // A photo in a different session must not leak into this bundle.
        $otherSessionId = $this->createSession($eventId);
        $this->createPhoto($otherSessionId);

        $orderId = $this->createPaidOrder();
        $itemId = $this->createOrderItem($orderId, ['item_type' => 'session_bundle', 'session_id' => $sessionId]);

        $item = ['photo_id' => null, 'session_id' => $sessionId, 'event_id' => null];
        $photoIds = resolve_order_item_photo_ids($this->pdo, $item);

        sort($photoIds);
        $expected = [$photoId1, $photoId2];
        sort($expected);
        $this->assertSame($expected, $photoIds);
    }

    public function testResolveOrderItemPhotoIdsExpandsEventBundle(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoId1 = $this->createPhoto($sessionId);
        $photoId2 = $this->createPhoto($sessionId);

        $item = ['photo_id' => null, 'session_id' => null, 'event_id' => $eventId];
        $photoIds = resolve_order_item_photo_ids($this->pdo, $item);

        sort($photoIds);
        $expected = [$photoId1, $photoId2];
        sort($expected);
        $this->assertSame($expected, $photoIds);
    }

    public function testResolveOrderItemPhotoIdsSinglePhoto(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoId = $this->createPhoto($sessionId);

        $item = ['photo_id' => $photoId, 'session_id' => null, 'event_id' => null];
        $photoIds = resolve_order_item_photo_ids($this->pdo, $item);

        $this->assertSame([$photoId], $photoIds);
    }

    public function testGetOrCreateDownloadLinkIsIdempotentForSameOrder(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $orderId = $this->createPaidOrder();
        $this->createOrderItem($orderId, ['item_type' => 'session_bundle', 'session_id' => $sessionId]);

        $config = ['site' => ['base_url' => 'https://gallery.test']];

        $urlFirst = get_or_create_download_link($this->pdo, $orderId, $config);
        $urlSecond = get_or_create_download_link($this->pdo, $orderId, $config);

        $this->assertSame($urlFirst, $urlSecond, 'repeated calls for the same order must return the same link');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM download_links WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $this->assertSame(1, (int)$stmt->fetchColumn(), 'exactly one download_links row should exist per order');
    }
}

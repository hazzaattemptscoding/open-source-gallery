<?php
/**
 * Covers a collection-scoping gap found while running a full security
 * audit (docs/security-audit-output/security-audit-report.md): the REST
 * API's photo endpoints (app/lib/api.php, used by /api/v1/photos) filtered
 * on photos.status='live' but never checked events.is_published, unlike
 * every other read path in the app (app/controllers/public/event.php,
 * app/lib/search.php). An API key holder could read photo metadata
 * (filename, price, dimensions, camera info, description) for events the
 * admin has not published yet.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/api.php';

final class ApiPhotosScopeTest extends TestCase {
    public function testApiGetPhotosExcludesUnpublishedEvents(): void {
        $publishedEvent = $this->createEvent(['is_published' => true]);
        $publishedSession = $this->createSession($publishedEvent);
        $visiblePhotoId = $this->createPhoto($publishedSession, ['status' => 'live']);

        $unpublishedEvent = $this->createEvent(['is_published' => false]);
        $unpublishedSession = $this->createSession($unpublishedEvent);
        $hiddenPhotoId = $this->createPhoto($unpublishedSession, ['status' => 'live']);

        $photos = api_get_photos($this->pdo, 1, 50);
        $ids = array_column($photos, 'id');

        $this->assertContains($visiblePhotoId, $ids);
        $this->assertNotContains($hiddenPhotoId, $ids, 'a photo belonging to an unpublished event must not be returned by the API');
    }

    public function testApiGetPhotoReturnsNullForUnpublishedEvent(): void {
        $unpublishedEvent = $this->createEvent(['is_published' => false]);
        $unpublishedSession = $this->createSession($unpublishedEvent);
        $photoId = $this->createPhoto($unpublishedSession, ['status' => 'live']);

        $this->assertNull(api_get_photo($this->pdo, $photoId));
    }

    public function testApiGetPhotoReturnsPublishedPhoto(): void {
        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $photoId = $this->createPhoto($sessionId, ['status' => 'live']);

        $photo = api_get_photo($this->pdo, $photoId);

        $this->assertNotNull($photo);
        $this->assertSame($photoId, $photo['id']);
    }
}

<?php
/**
 * Integration tests for photo search and filtering.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

class SearchTest extends TestCase {
    /**
     * Test basic photo search by filename.
     */
    public function testSearchByFilename(): void {
        require_once APP_ROOT . '/app/lib/search.php';

        // Create published event and session.
        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);

        // Create photos with distinctive filenames.
        $this->createPhoto($sessionId, [
            'original_filename' => 'test-photo-001.jpg',
            'status' => 'live',
        ]);
        $this->createPhoto($sessionId, [
            'original_filename' => 'another-image-002.jpg',
            'status' => 'live',
        ]);

        // Search for "test".
        $results = \search_photos($this->pdo, 'test', [], 1, 20);

        // Should find the first photo.
        $this->assertGreaterThanOrEqual(1, count($results['photos']));
        $this->assertTrue(
            in_array('test-photo-001.jpg', array_column($results['photos'], 'original_filename')),
            'Should find photo by filename'
        );
    }

    /**
     * Test search respects published status.
     */
    public function testSearchRespectesPublishedStatus(): void {
        require_once APP_ROOT . '/app/lib/search.php';

        // Create unpublished event.
        $unpublishedEvent = $this->createEvent(['is_published' => false]);
        $unpublishedSession = $this->createSession($unpublishedEvent);
        $this->createPhoto($unpublishedSession, [
            'original_filename' => 'unpublished-photo.jpg',
            'status' => 'live',
        ]);

        // Create published event.
        $publishedEvent = $this->createEvent(['is_published' => true]);
        $publishedSession = $this->createSession($publishedEvent);
        $this->createPhoto($publishedSession, [
            'original_filename' => 'published-photo.jpg',
            'status' => 'live',
        ]);

        // Search - should only find published.
        $results = \search_photos($this->pdo, 'photo', [], 1, 20);

        $filenames = array_column($results['photos'], 'original_filename');
        $this->assertContains('published-photo.jpg', $filenames);
        $this->assertNotContains('unpublished-photo.jpg', $filenames);
    }

    /**
     * Test search respects photo status (only 'live').
     */
    public function testSearchRespectesPhotoStatus(): void {
        require_once APP_ROOT . '/app/lib/search.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);

        // Create live photo.
        $this->createPhoto($sessionId, [
            'original_filename' => 'live-photo.jpg',
            'status' => 'live',
        ]);

        // Create a non-live photo (should not appear in search). 'hidden' is
        // a real value on the photos.status ENUM (processing/live/hidden/failed);
        // the admin bulk-status UI's 'draft'/'archived' vocabulary is not, so
        // those aren't usable here (see BulkOperationsTest::testBulkChangeStatus).
        $this->createPhoto($sessionId, [
            'original_filename' => 'hidden-photo.jpg',
            'status' => 'hidden',
        ]);

        $results = \search_photos($this->pdo, 'photo', [], 1, 20);

        $filenames = array_column($results['photos'], 'original_filename');
        $this->assertContains('live-photo.jpg', $filenames);
        $this->assertNotContains('hidden-photo.jpg', $filenames);
    }

    /**
     * Test search with pagination.
     */
    public function testSearchPagination(): void {
        require_once APP_ROOT . '/app/lib/search.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);

        // Create 25 photos.
        for ($i = 1; $i <= 25; $i++) {
            $this->createPhoto($sessionId, [
                'original_filename' => "photo-$i.jpg",
                'status' => 'live',
            ]);
        }

        // Get first page (20 per page).
        $page1 = \search_photos($this->pdo, 'photo', [], 1, 20);
        $this->assertEquals(20, count($page1['photos']));

        // Get second page.
        $page2 = \search_photos($this->pdo, 'photo', [], 2, 20);
        $this->assertEquals(5, count($page2['photos']));
    }

    /**
     * Test search with short query returns empty.
     */
    public function testSearchTooShortQuery(): void {
        require_once APP_ROOT . '/app/lib/search.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $this->createPhoto($sessionId, [
            'original_filename' => 'test-photo.jpg',
            'status' => 'live',
        ]);

        // Single character search.
        $results = \search_photos($this->pdo, 'a', [], 1, 20);

        $this->assertEquals(0, count($results['photos']), 'Single-char query should return no results');
    }

    /**
     * Test search with empty query returns empty.
     */
    public function testSearchEmptyQuery(): void {
        require_once APP_ROOT . '/app/lib/search.php';

        $eventId = $this->createEvent(['is_published' => true]);
        $sessionId = $this->createSession($eventId);
        $this->createPhoto($sessionId, [
            'original_filename' => 'test-photo.jpg',
            'status' => 'live',
        ]);

        $results = \search_photos($this->pdo, '', [], 1, 20);

        $this->assertEquals(0, count($results['photos']), 'Empty query should return no results');
    }

    /**
     * Test search returns event and session info.
     */
    public function testSearchReturnsEventAndSessionInfo(): void {
        require_once APP_ROOT . '/app/lib/search.php';

        $eventId = $this->createEvent([
            'title' => 'Grand Prix',
            'is_published' => true,
        ]);
        $sessionId = $this->createSession($eventId, [
            'name' => 'Qualifying',
            'slug' => 'qualifying',
        ]);

        $this->createPhoto($sessionId, [
            'original_filename' => 'qualifying-photo.jpg',
            'status' => 'live',
        ]);

        $results = \search_photos($this->pdo, 'qualifying', [], 1, 20);

        $this->assertGreaterThan(0, count($results['photos']));
        $photo = $results['photos'][0];
        $this->assertEquals('Grand Prix', $photo['event_title']);
        $this->assertEquals('qualifying', $photo['session_slug']);
    }
}

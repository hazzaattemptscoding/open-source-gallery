<?php
/**
 * Covers app/lib/cron.php's job processors. process_zip_build_job() only
 * ever handled order items with photo_id set — session_id/event_id bundle
 * items were silently skipped ("if (!$photoId) continue;"), so bundle
 * orders never got a pre-built zip and every bundle download fell back to
 * building one synchronously inside the request, defeating the point of
 * pre-building at all.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use ZipArchive;

require_once __DIR__ . '/../../app/lib/cron.php';

final class CronJobsTest extends TestCase {
    private array $writtenFiles = [];

    protected function tearDown(): void {
        foreach ($this->writtenFiles as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    /**
     * Writes a real, tiny hires file at the exact path download.php and
     * cron.php both expect, so the job under test can find it via a real
     * file_exists() check rather than a mock.
     */
    private function writeHiresFile(int $eventId, string $token, string $ext = 'jpg'): void {
        $dir = dirname(__DIR__, 2) . "/storage/hires/{$eventId}";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = "{$dir}/{$token}.{$ext}";
        file_put_contents($path, 'fake-jpeg-bytes');
        $this->writtenFiles[] = $path;
    }

    private function createPaidOrderWithItem(array $itemOverrides): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (public_token, email, currency, total_pence, status)
             VALUES (?, 'buyer@example.com', 'GBP', 2000, 'paid')"
        );
        $stmt->execute([bin2hex(random_bytes(11))]);
        $orderId = (int)$this->pdo->lastInsertId();

        $data = array_merge([
            'item_type' => 'session_bundle',
            'photo_id' => null,
            'session_id' => null,
            'event_id' => null,
            'description' => 'Test item',
            'unit_price_pence' => 2000,
        ], $itemOverrides);

        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, item_type, photo_id, session_id, event_id, description, unit_price_pence, line_total_pence)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderId, $data['item_type'], $data['photo_id'], $data['session_id'], $data['event_id'],
            $data['description'], $data['unit_price_pence'], $data['unit_price_pence'],
        ]);

        return $orderId;
    }

    public function testZipBuildExpandsSessionBundle(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $token1 = 'tok' . substr(bin2hex(random_bytes(4)), 0, 9);
        $token2 = 'tok' . substr(bin2hex(random_bytes(4)), 0, 9);
        $this->createPhoto($sessionId, ['public_token' => $token1]);
        $this->createPhoto($sessionId, ['public_token' => $token2]);
        $this->writeHiresFile($eventId, $token1);
        $this->writeHiresFile($eventId, $token2);

        $orderId = $this->createPaidOrderWithItem(['item_type' => 'session_bundle', 'session_id' => $sessionId]);

        $result = process_zip_build_job($this->pdo, ['order_id' => $orderId]);
        $this->assertTrue($result, 'process_zip_build_job should succeed for a session bundle');

        $zipPath = dirname(__DIR__, 2) . "/storage/zips/{$orderId}.zip";
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $zip->open($zipPath);
        $this->assertSame(2, $zip->numFiles, 'both photos in the session bundle should be in the pre-built zip');
        $zip->close();
        @unlink($zipPath);
    }

    public function testZipBuildExpandsEventBundle(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $token = 'tok' . substr(bin2hex(random_bytes(4)), 0, 9);
        $this->createPhoto($sessionId, ['public_token' => $token]);
        $this->writeHiresFile($eventId, $token);

        $orderId = $this->createPaidOrderWithItem(['item_type' => 'event_bundle', 'event_id' => $eventId]);

        $result = process_zip_build_job($this->pdo, ['order_id' => $orderId]);
        $this->assertTrue($result);

        $zipPath = dirname(__DIR__, 2) . "/storage/zips/{$orderId}.zip";
        $this->assertFileExists($zipPath);
        @unlink($zipPath);
    }

    public function testViewCountJobWorksUnderCurrentDbDriver(): void {
        $eventId = $this->createEvent();
        $sessionId = $this->createSession($eventId);
        $photoId = $this->createPhoto($sessionId, ['view_count' => 0]);

        $result = process_view_count_job($this->pdo, ['photo_id' => $photoId, 'event_id' => $eventId]);
        $this->assertTrue($result);

        // Calling it twice on the same day must accumulate, not overwrite —
        // this is exactly what the ON DUPLICATE KEY UPDATE / portable
        // upsert branch needs to get right.
        process_view_count_job($this->pdo, ['photo_id' => $photoId, 'event_id' => $eventId]);

        $stmt = $this->pdo->prepare('SELECT photo_views FROM stats_daily WHERE event_id = ? AND stat_date = ?');
        $stmt->execute([$eventId, date('Y-m-d')]);
        $this->assertSame(2, (int)$stmt->fetchColumn());

        $stmt = $this->pdo->prepare('SELECT view_count FROM photos WHERE id = ?');
        $stmt->execute([$photoId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }
}

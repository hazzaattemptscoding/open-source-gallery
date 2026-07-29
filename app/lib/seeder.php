<?php
/**
 * Development seeder: Create test data if the database is empty.
 * Runs once on first access, then never again.
 */

declare(strict_types=1);

function seed_test_data(PDO $pdo): void
{
    try {
        // Check if events already exist
        $count = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        if ($count > 0) {
            return; // Already seeded
        }

        // Create test event
        $stmt = $pdo->prepare('
            INSERT INTO events (slug, title, venue, event_date, is_published, price_single_pence)
            VALUES (:slug, :title, :venue, :date, 1, :price)
        ');

        $stmt->execute([
            'slug' => 'test-event-2024',
            'title' => 'Test Event 2024',
            'venue' => 'Test Venue',
            'date' => date('Y-m-d'),
            'price' => 1999,
        ]);

        $event_id = (int) $pdo->lastInsertId();

        // Create test session
        $stmt = $pdo->prepare('
            INSERT INTO sessions (event_id, slug, name)
            VALUES (:event_id, :slug, :name)
        ');

        $stmt->execute([
            'event_id' => $event_id,
            'slug' => 'test-session',
            'name' => 'Test Session',
        ]);

        $session_id = (int) $pdo->lastInsertId();

        // Create test photos
        for ($i = 1; $i <= 8; $i++) {
            $stmt = $pdo->prepare('
                INSERT INTO photos (public_token, event_id, session_id, original_filename, status)
                VALUES (:token, :event_id, :session_id, :filename, :status)
            ');

            $stmt->execute([
                'token' => 'test' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
                'event_id' => $event_id,
                'session_id' => $session_id,
                'filename' => 'test-photo-' . $i . '.jpg',
                'status' => 'live',
            ]);
        }
    } catch (Exception $e) {
        // Silently fail if seeding doesn't work (maybe schema not imported yet)
    }
}

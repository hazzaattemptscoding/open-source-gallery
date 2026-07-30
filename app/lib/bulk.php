<?php
/**
 * Bulk operations for photos: tag, price, delete, organize.
 */

declare(strict_types=1);

require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/db_compat.php';

/**
 * Bulk tag photos.
 */
function bulk_tag_photos(PDO $pdo, array $photoIds, array $tags): int {
    if (empty($photoIds) || empty($tags)) {
        return 0;
    }

    $inserted = 0;
    $placeholders = implode(',', array_map(fn($id) => '(?, ?, ?, ?)', $photoIds));
    $params = [];
    foreach ($photoIds as $photoId) {
        $params[] = (int)$photoId;
        $params[] = $tags['kart'] ?? null;
        $params[] = $tags['driver'] ?? null;
        $params[] = $tags['class'] ?? null;
    }

    if (db_supports_on_duplicate_key($pdo)) {
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO photo_tags (photo_id, kart_number, driver_name, class)
            VALUES $placeholders
            ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
        SQL);
        $stmt->execute($params);
        $inserted = $stmt->rowCount();
    } else {
        foreach ($photoIds as $photoId) {
            try {
                $stmt = $pdo->prepare('INSERT INTO photo_tags (photo_id, kart_number, driver_name, class) VALUES (?, ?, ?, ?)');
                $stmt->execute([
                    (int)$photoId,
                    $tags['kart'] ?? null,
                    $tags['driver'] ?? null,
                    $tags['class'] ?? null,
                ]);
                $inserted += $stmt->rowCount();
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                    continue;
                }
                throw $e;
            }
        }
    }

    return $inserted;
}

/**
 * Bulk set a per-photo price override. NULL means "inherit the event's
 * price_single_pence" (see migrations/009_add_photo_price_override.sql);
 * this function only ever sets an explicit override, never clears one back
 * to NULL — clearing is a distinct admin action, not a bulk-price value.
 */
function bulk_update_prices(PDO $pdo, array $photoIds, int $pricePence): int {
    if (empty($photoIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare("UPDATE photos SET price_pence = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$pricePence], array_map('intval', $photoIds)));

    return $stmt->rowCount();
}

/**
 * Bulk delete photos.
 */
function bulk_delete_photos(PDO $pdo, array $photoIds): int {
    if (empty($photoIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM photos WHERE id IN ($placeholders)");
    $deleted = $stmt->execute(array_map('intval', $photoIds)) ? $stmt->rowCount() : 0;

    // Deleted photos can no longer show up in cached facets/trending.
    if ($deleted > 0) {
        cache_invalidate_all();
    }

    return $deleted;
}

/**
 * Bulk change photo status.
 * Valid statuses: processing (uploading), live (published), hidden (unpublished), failed (upload failed).
 */
function bulk_change_status(PDO $pdo, array $photoIds, string $status): int {
    // Valid statuses match schema: processing, live, hidden, failed
    $validStatuses = ['processing', 'live', 'hidden', 'failed'];
    if (empty($photoIds) || !in_array($status, $validStatuses)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare("UPDATE photos SET status = ? WHERE id IN ($placeholders)");
    $params = array_merge([$status], array_map('intval', $photoIds));
    $changed = $stmt->execute($params) ? $stmt->rowCount() : 0;

    // Status changes affect what's "live" for facets/trending — cached
    // results would otherwise keep showing photos that just went hidden.
    if ($changed > 0) {
        cache_invalidate_all();
    }

    return $changed;
}

/**
 * Get bulk operation limits for current role.
 */
function get_bulk_limits(PDO $pdo): array {
    $isAdmin = is_admin($pdo);
    return [
        'max_per_operation' => $isAdmin ? 10000 : 500,
        'max_daily_operations' => $isAdmin ? 1000 : 100,
        'can_delete' => $isAdmin,
        'can_bulk_price' => true,
        'can_bulk_tag' => true,
    ];
}

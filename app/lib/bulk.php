<?php
/**
 * Bulk operations for photos: tag, price, delete, organize.
 */

declare(strict_types=1);

/**
 * Bulk tag photos.
 */
function bulk_tag_photos(PDO $pdo, array $photoIds, array $tags): int {
    if (empty($photoIds) || empty($tags)) {
        return 0;
    }

    $updated = 0;
    $stmt = $pdo->prepare(<<<'SQL'
        INSERT INTO photo_tags (photo_id, kart_number, driver_name, class)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    SQL);

    foreach ($photoIds as $photoId) {
        if ($stmt->execute([
            (int)$photoId,
            $tags['kart'] ?? null,
            $tags['driver'] ?? null,
            $tags['class'] ?? null,
        ])) {
            $updated++;
        }
    }

    return $updated;
}

/**
 * Bulk update photo prices.
 */
function bulk_update_prices(PDO $pdo, array $photoIds, int $pricePence): int {
    if (empty($photoIds) || $pricePence < 0) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare("UPDATE photos SET price_pence = ? WHERE id IN ($placeholders)");
    $params = array_merge([$pricePence], array_map('intval', $photoIds));
    return $stmt->execute($params) ? $stmt->rowCount() : 0;
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
    return $stmt->execute(array_map('intval', $photoIds)) ? $stmt->rowCount() : 0;
}

/**
 * Bulk change photo status.
 */
function bulk_change_status(PDO $pdo, array $photoIds, string $status): int {
    if (empty($photoIds) || !in_array($status, ['draft', 'live', 'archived'])) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare("UPDATE photos SET status = ? WHERE id IN ($placeholders)");
    $params = array_merge([$status], array_map('intval', $photoIds));
    return $stmt->execute($params) ? $stmt->rowCount() : 0;
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

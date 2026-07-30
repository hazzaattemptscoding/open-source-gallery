<?php
declare(strict_types=1);

/**
 * Photo tagging system: client name, location, style, featured, custom tags.
 * Tags are many-to-many; photos and tags are independent, bulk-taggable.
 */

/**
 * Get all tags for a photo (grouped by type: client, location, style, custom, featured).
 * @return list<array{type: string, value: string}>
 */
function photo_get_tags(PDO $pdo, int $photoId): array {
    $stmt = $pdo->prepare('
        SELECT tag_type, tag_value
        FROM photo_tags
        WHERE photo_id = ?
        ORDER BY tag_type ASC, tag_value ASC
    ');
    $stmt->execute([$photoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Get all unique tag values for a given type (e.g., all clients, all locations).
 * @return list<string>
 */
function photo_get_tag_values(PDO $pdo, int $eventId, string $tagType): array {
    $stmt = $pdo->prepare('
        SELECT DISTINCT tag_value
        FROM photo_tags pt
        JOIN photos p ON pt.photo_id = p.id
        WHERE p.event_id = ? AND pt.tag_type = ?
        ORDER BY tag_value ASC
    ');
    $stmt->execute([$eventId, $tagType]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Add a tag to a photo. If tag already exists for this photo, no-op.
 */
function photo_add_tag(PDO $pdo, int $photoId, string $tagType, string $tagValue): bool {
    $stmt = $pdo->prepare('
        INSERT IGNORE INTO photo_tags (photo_id, tag_type, tag_value)
        VALUES (?, ?, ?)
    ');
    return (bool)$stmt->execute([$photoId, $tagType, $tagValue]);
}

/**
 * Remove a tag from a photo.
 */
function photo_remove_tag(PDO $pdo, int $photoId, string $tagType, string $tagValue): bool {
    $stmt = $pdo->prepare('
        DELETE FROM photo_tags
        WHERE photo_id = ? AND tag_type = ? AND tag_value = ?
    ');
    return (bool)$stmt->execute([$photoId, $tagType, $tagValue]);
}

/**
 * Bulk-tag multiple photos with the same tags. Idempotent.
 * @param list<int> $photoIds
 * @param array{client?: string, location?: string, style?: string, featured?: bool} $tags
 */
function photos_bulk_add_tags(PDO $pdo, array $photoIds, array $tags): bool {
    if (empty($photoIds) || empty($tags)) {
        return true;
    }

    $values = [];
    $params = [];
    foreach ($photoIds as $photoId) {
        foreach ($tags as $type => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($type === 'featured') {
                $value = $value ? '1' : '0';
            }
            $values[] = '(?, ?, ?)';
            $params[] = $photoId;
            $params[] = $type;
            $params[] = $value;
        }
    }

    if (empty($values)) {
        return true;
    }

    $sql = 'INSERT IGNORE INTO photo_tags (photo_id, tag_type, tag_value) VALUES ' . implode(',', $values);
    $stmt = $pdo->prepare($sql);
    return (bool)$stmt->execute($params);
}

/**
 * Remove all tags of a given type from multiple photos.
 * @param list<int> $photoIds
 */
function photos_bulk_remove_tags(PDO $pdo, array $photoIds, string $tagType): bool {
    if (empty($photoIds)) {
        return true;
    }
    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM photo_tags WHERE photo_id IN ({$placeholders}) AND tag_type = ?");
    return (bool)$stmt->execute([...$photoIds, $tagType]);
}

/**
 * Get all photos matching a set of tag filters (AND logic: all filters must match).
 * @param array{client?: string, location?: string, style?: string, date_start?: string, date_end?: string, featured?: bool} $filters
 * @return list<array{id: int, public_token: string}>
 */
function photos_filter_by_tags(PDO $pdo, int $eventId, ?int $sessionId, array $filters, string $mediaType = 'photo'): array {
    $where = ['p.event_id = ?', 'p.status = ?', 'p.media_type = ?'];
    $params = [$eventId, 'live', $mediaType];

    if ($sessionId !== null) {
        $where[] = 'p.session_id = ?';
        $params[] = $sessionId;
    }

    if (!empty($filters['date_start'])) {
        $where[] = 'DATE(p.created_at) >= ?';
        $params[] = $filters['date_start'];
    }
    if (!empty($filters['date_end'])) {
        $where[] = 'DATE(p.created_at) <= ?';
        $params[] = $filters['date_end'];
    }

    $tagJoins = [];
    $tagIndex = 0;
    foreach (['client', 'location', 'style'] as $type) {
        if (!empty($filters[$type])) {
            $alias = "pt{$tagIndex}";
            $tagJoins[] = "INNER JOIN photo_tags {$alias} ON {$alias}.photo_id = p.id AND {$alias}.tag_type = ? AND {$alias}.tag_value = ?";
            $params[] = $type;
            $params[] = $filters[$type];
            $tagIndex++;
        }
    }

    if (isset($filters['featured']) && $filters['featured']) {
        $alias = "pt{$tagIndex}";
        $tagJoins[] = "INNER JOIN photo_tags {$alias} ON {$alias}.photo_id = p.id AND {$alias}.tag_type = 'featured' AND {$alias}.tag_value = '1'";
        $tagIndex++;
    }

    $join = implode(' ', $tagJoins);
    $sql = "
        SELECT DISTINCT p.id, p.public_token
        FROM photos p
        {$join}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.sort_order ASC, p.id ASC
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

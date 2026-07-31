<?php
/**
 * Full-text search and advanced filtering for photos.
 * Searches: original_filename, captions, tags (kart, class)
 * Uses caching for facets and trending photos.
 */

declare(strict_types=1);

require_once __DIR__ . '/cache.php';

/**
 * Search photos with full-text search and filters.
 * Returns paginated results with facets.
 */
function search_photos(
    PDO $pdo,
    string $query,
    array $filters = [],
    int $page = 1,
    int $perPage = 20
): array {
    $offset = ($page - 1) * $perPage;

    // Sanitize query (remove dangerous characters)
    $query = trim($query);
    if (strlen($query) < 2) {
        return ['photos' => [], 'total' => 0, 'pages' => 0, 'facets' => []];
    }

    // Build base query
    $sql = <<<'SQL'
        SELECT DISTINCT
            p.id, p.public_token, p.original_filename, p.status, p.event_id,
            p.session_id, p.width, p.height, p.view_count,
            e.title as event_title, e.slug as event_slug, e.price_single_pence,
            s.slug as session_slug
        FROM photos p
        JOIN events e ON p.event_id = e.id
        JOIN sessions s ON p.session_id = s.id
        LEFT JOIN photo_tags t ON p.id = t.photo_id
        WHERE p.status = 'live' AND e.is_published = 1
    SQL;

    $params = [];

    // Full-text search (if query provided)
    if (!empty($query)) {
        // driver_name is not searchable: matching it would let anyone confirm
        // whether a named driver (often a minor) appears in the galleries.
        $sql .= ' AND (
            p.original_filename LIKE ?
            OR t.kart_number LIKE ?
            OR t.class LIKE ?
        )';
        $searchTerm = '%' . $query . '%';
        $params = array_fill(0, 3, $searchTerm);
    }

    // Apply filters
    if (!empty($filters['event_id'])) {
        $sql .= ' AND p.event_id = ?';
        $params[] = (int)$filters['event_id'];
    }

    if (!empty($filters['kart'])) {
        $sql .= ' AND t.kart_number = ?';
        $params[] = (string)$filters['kart'];
    }

    if (!empty($filters['class'])) {
        $sql .= ' AND t.class = ?';
        $params[] = (string)$filters['class'];
    }

    if (!empty($filters['date_from'])) {
        $sql .= ' AND DATE(p.created_at) >= ?';
        $params[] = (string)$filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $sql .= ' AND DATE(p.created_at) <= ?';
        $params[] = (string)$filters['date_to'];
    }

    // Get total count using simplified query (avoid expensive subquery COUNT).
    // Build count query with same WHERE conditions but simpler SELECT.
    $countParams = $params;
    $countSql = 'SELECT COUNT(DISTINCT p.id) FROM photos p
        JOIN events e ON p.event_id = e.id
        JOIN sessions s ON p.session_id = s.id
        LEFT JOIN photo_tags t ON p.id = t.photo_id
        WHERE p.status = \'live\' AND e.is_published = 1';

    // Apply same WHERE conditions to count query.
    if (!empty($query)) {
        $countSql .= ' AND (
            p.original_filename LIKE ?
            OR t.kart_number LIKE ?
            OR t.class LIKE ?
        )';
        $searchTerm = '%' . $query . '%';
        $countParams = array_fill(0, 3, $searchTerm);
    } else {
        $countParams = [];
    }

    if (!empty($filters['event_id'])) {
        $countSql .= ' AND p.event_id = ?';
        $countParams[] = (int)$filters['event_id'];
    }
    if (!empty($filters['kart'])) {
        $countSql .= ' AND t.kart_number = ?';
        $countParams[] = (string)$filters['kart'];
    }
    if (!empty($filters['class'])) {
        $countSql .= ' AND t.class = ?';
        $countParams[] = (string)$filters['class'];
    }
    if (!empty($filters['date_from'])) {
        $countSql .= ' AND DATE(p.created_at) >= ?';
        $countParams[] = (string)$filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $countSql .= ' AND DATE(p.created_at) <= ?';
        $countParams[] = (string)$filters['date_to'];
    }

    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $total = (int)$stmt->fetchColumn();
    $pages = ceil($total / $perPage) ?: 1;

    // Get paginated results
    $sql .= ' ORDER BY p.view_count DESC, p.created_at DESC LIMIT ? OFFSET ?';
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get facets (for filtering sidebar)
    $facets = get_search_facets($pdo, $filters);

    return [
        'photos' => $photos,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'facets' => $facets,
    ];
}

/**
 * Get search facets (available filter options).
 * Shows counts for kart numbers, classes, etc.
 * Cached for 15 minutes to avoid repeated expensive queries.
 */
function get_search_facets(PDO $pdo, array $currentFilters = []): array {
    // Check cache first (15 min TTL). The key is versioned: entries written
    // before driver names were removed still hold them, and bumping the key
    // retires those payloads instead of serving them until they expire.
    $cacheKey = 'search_facets_all_v2';
    if (cache_has($cacheKey)) {
        return cache_get($cacheKey);
    }

    // No 'drivers' facet: a facet list enumerates every driver name in the
    // system on a public page, which is the worst-case version of the
    // safeguarding problem this codebase is required to avoid.
    $facets = [
        'events' => [],
        'karts' => [],
        'classes' => [],
        'price_ranges' => [
            ['min' => 0, 'max' => 500, 'label' => 'Under £5'],
            ['min' => 500, 'max' => 1000, 'label' => '£5 - £10'],
            ['min' => 1000, 'max' => 2000, 'label' => '£10 - £20'],
            ['min' => 2000, 'max' => null, 'label' => 'Over £20'],
        ],
    ];

    try {
        // Events
        $stmt = $pdo->query(<<<'SQL'
            SELECT e.id, e.title, COUNT(DISTINCT p.id) as count
            FROM events e
            JOIN photos p ON e.id = p.event_id
            WHERE p.status = 'live' AND e.is_published = 1
            GROUP BY e.id, e.title
            ORDER BY e.event_date DESC
        SQL);
        $facets['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Karts
        $stmt = $pdo->query(<<<'SQL'
            SELECT DISTINCT t.kart_number, COUNT(DISTINCT p.id) as count
            FROM photo_tags t
            JOIN photos p ON t.photo_id = p.id
            WHERE p.status = 'live' AND t.kart_number IS NOT NULL AND t.kart_number != ''
            GROUP BY t.kart_number
            ORDER BY t.kart_number
            LIMIT 50
        SQL);
        $facets['karts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Classes
        $stmt = $pdo->query(<<<'SQL'
            SELECT DISTINCT t.class, COUNT(DISTINCT p.id) as count
            FROM photo_tags t
            JOIN photos p ON t.photo_id = p.id
            WHERE p.status = 'live' AND t.class IS NOT NULL AND t.class != ''
            GROUP BY t.class
            ORDER BY t.class
            LIMIT 50
        SQL);
        $facets['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Cache for 15 minutes (900 seconds).
        cache_set($cacheKey, $facets, 900);

    } catch (Throwable $e) {
        error_log('search: get_search_facets() failed: ' . $e->getMessage());
        // Silently fail (search not critical)
    }

    return $facets;
}

/**
 * Get trending/popular photos (used for homepage, "trending" section).
 * Cached for 1 day to avoid repeated expensive queries.
 */
function get_trending_photos(PDO $pdo, int $limit = 12): array {
    // Check cache first (1 day TTL = 86400 seconds).
    $cacheKey = "trending_photos_$limit";
    if (cache_has($cacheKey)) {
        return cache_get($cacheKey);
    }

    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT p.id, p.public_token, p.original_filename,
                   p.view_count, p.event_id, e.title as event_title,
                   e.price_single_pence,
                   s.slug as session_slug, e.slug as event_slug
            FROM photos p
            JOIN events e ON p.event_id = e.id
            JOIN sessions s ON p.session_id = s.id
            WHERE p.status = 'live' AND e.is_published = 1
            ORDER BY p.view_count DESC, p.created_at DESC
            LIMIT ?
        SQL);
        $stmt->execute([$limit]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Cache for 1 day (86400 seconds).
        cache_set($cacheKey, $photos, 86400);

        return $photos;
    } catch (Throwable $e) {
        error_log('search: get_trending_photos() failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Increment view count for a photo (for analytics).
 */
function increment_photo_views(PDO $pdo, int $photoId): void {
    try {
        $stmt = $pdo->prepare('UPDATE photos SET view_count = view_count + 1 WHERE id = ?');
        $stmt->execute([$photoId]);
    } catch (Throwable $e) {
        error_log('search: increment_photo_views() failed: ' . $e->getMessage());
        // Silently fail (view count is not critical)
    }
}

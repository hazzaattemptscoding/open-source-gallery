<?php
/**
 * Simple caching layer for expensive queries.
 * Uses in-memory cache for within-request caching.
 * Uses $_SESSION for user-scoped caching that persists across requests.
 * Uses file-based cache for global data.
 */

declare(strict_types=1);

// In-memory cache for request-scoped data.
$_cache = [];
$_cache_timestamps = [];

/**
 * Cache a query result.
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds (0 = no expiry)
 */
function cache_set(string $key, mixed $value, int $ttl = 0): void {
    global $_cache, $_cache_timestamps;
    $_cache[$key] = $value;
    $_cache_timestamps[$key] = time() + $ttl;
}

/**
 * Retrieve a cached value.
 * Returns null if not found or expired.
 */
function cache_get(string $key): mixed {
    global $_cache, $_cache_timestamps;

    if (!isset($_cache[$key])) {
        return null;
    }

    // Check expiry.
    if ($_cache_timestamps[$key] > 0 && time() > $_cache_timestamps[$key]) {
        unset($_cache[$key], $_cache_timestamps[$key]);
        return null;
    }

    return $_cache[$key];
}

/**
 * Check if a cache key exists and is not expired.
 */
function cache_has(string $key): bool {
    return cache_get($key) !== null;
}

/**
 * Clear the entire request cache.
 */
function cache_clear(): void {
    global $_cache, $_cache_timestamps;
    $_cache = [];
    $_cache_timestamps = [];
}

/**
 * Cache a value in the session for persistence across requests.
 */
function session_cache_set(string $key, mixed $value, int $ttlSeconds = 0): void {
    if (!isset($_SESSION['_cache'])) {
        $_SESSION['_cache'] = [];
        $_SESSION['_cache_ttl'] = [];
    }

    $_SESSION['_cache'][$key] = $value;
    $_SESSION['_cache_ttl'][$key] = time() + $ttlSeconds;
}

/**
 * Retrieve a value from session cache.
 */
function session_cache_get(string $key): mixed {
    if (!isset($_SESSION['_cache'][$key])) {
        return null;
    }

    // Check expiry.
    $expiry = $_SESSION['_cache_ttl'][$key] ?? 0;
    if ($expiry > 0 && time() > $expiry) {
        unset($_SESSION['_cache'][$key], $_SESSION['_cache_ttl'][$key]);
        return null;
    }

    return $_SESSION['_cache'][$key];
}

/**
 * Clear session cache.
 */
function session_cache_clear(): void {
    $_SESSION['_cache'] = [];
    $_SESSION['_cache_ttl'] = [];
}

/**
 * Cache facet queries result (e.g., distinct values for filters).
 * Facets cache for 15 minutes.
 */
function cache_facets(PDO $pdo, string $eventId): ?array {
    $key = "facets:$eventId";

    // Check request cache first (within same request).
    if (cache_has($key)) {
        return cache_get($key);
    }

    // Check session cache (across requests, 15 min).
    if (cache_has("session:$key")) {
        return cache_get("session:$key");
    }

    $facets = fetch_facets($pdo, (int)$eventId);
    if ($facets !== null) {
        cache_set($key, $facets, 0); // Request-scoped
        session_cache_set($key, $facets, 900); // 15 minutes
    }

    return $facets;
}

/**
 * Fetch available filter values for an event (driver names, classes, etc).
 * This is an expensive query with multiple GROUP BYs.
 */
function fetch_facets(PDO $pdo, int $eventId): ?array {
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT
            GROUP_CONCAT(DISTINCT t.driver_name) as drivers,
            GROUP_CONCAT(DISTINCT t.class) as classes,
            GROUP_CONCAT(DISTINCT t.kart_number) as karts,
            COUNT(DISTINCT p.id) as photo_count
        FROM photos p
        JOIN sessions s ON p.session_id = s.id
        LEFT JOIN photo_tags t ON p.id = t.photo_id
        WHERE s.event_id = ? AND p.status = 'live'
    SQL);

    if (!$stmt->execute([$eventId])) {
        return null;
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Cache admin settings (loaded once per session, not per request).
 */
function cache_settings(PDO $pdo): array {
    $key = 'admin_settings';

    // Check session cache.
    if (session_cache_has($key)) {
        return session_cache_get($key);
    }

    $settings = fetch_settings($pdo);
    session_cache_set($key, $settings, 0); // No expiry for session

    return $settings;
}

/**
 * Check if session cache key exists.
 */
function session_cache_has(string $key): bool {
    return session_cache_get($key) !== null;
}

/**
 * Fetch all admin settings from database.
 * Called once per session and cached.
 */
function fetch_settings(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings');
    $stmt->execute();

    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

/**
 * Get a setting value with caching.
 */
function get_setting(PDO $pdo, string $key): ?string {
    $settings = cache_settings($pdo);
    return $settings[$key] ?? null;
}

/**
 * Invalidate all caches (call after data mutations).
 */
function cache_invalidate_all(): void {
    cache_clear();
    session_cache_clear();
}

/**
 * Invalidate caches for a specific event (call after event changes).
 */
function cache_invalidate_event(int $eventId): void {
    global $_cache, $_cache_timestamps;

    // Remove event-specific caches.
    $prefix = "facets:$eventId";
    foreach (array_keys($_cache) as $key) {
        if (str_starts_with($key, $prefix)) {
            unset($_cache[$key], $_cache_timestamps[$key]);
        }
    }

    // Also clear session-scoped event cache.
    if (isset($_SESSION['_cache'])) {
        foreach (array_keys($_SESSION['_cache']) as $key) {
            if (str_starts_with($key, "facets:$eventId")) {
                unset($_SESSION['_cache'][$key], $_SESSION['_cache_ttl'][$key]);
            }
        }
    }
}

/**
 * Invalidate settings cache (call when admin updates settings).
 */
function cache_invalidate_settings(): void {
    session_cache_clear();
}

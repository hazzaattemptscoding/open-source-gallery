<?php
/**
 * Caching layer for expensive, rarely-changing queries (search facets,
 * trending photos). Two tiers:
 *   - In-memory: avoids re-reading the same key twice within one request.
 *   - File-backed: survives across requests without a daemon or extension
 *     (APCu/Redis aren't guaranteed on shared hosting; this needs nothing
 *     beyond a writable directory, per CLAUDE.md's "no daemons" constraint).
 * $_SESSION-backed caching is kept separate (session_cache_*) for values that
 * are genuinely per-visitor rather than shared site-wide state.
 */

declare(strict_types=1);

const CACHE_DIR = __DIR__ . '/../../storage/cache';

// Request-scoped: avoids hitting the filesystem twice for the same key.
$_cache_memory = [];

/**
 * Cache a value. $ttl in seconds; 0 means "does not expire on its own"
 * (still cleared by cache_clear()/cache_forget()).
 */
function cache_set(string $key, mixed $value, int $ttl = 0): void {
    global $_cache_memory;
    $expiresAt = $ttl > 0 ? time() + $ttl : 0;
    $_cache_memory[$key] = ['value' => $value, 'expires' => $expiresAt];
    cache_file_write($key, $value, $expiresAt);
}

/** Retrieve a cached value, or null if missing/expired. */
function cache_get(string $key): mixed {
    global $_cache_memory;

    if (isset($_cache_memory[$key])) {
        $entry = $_cache_memory[$key];
        if ($entry['expires'] === 0 || time() <= $entry['expires']) {
            return $entry['value'];
        }
        unset($_cache_memory[$key]);
        return null;
    }

    $fromFile = cache_file_read($key);
    if ($fromFile === null) {
        return null;
    }

    // Populate the memory tier so a second read this request skips the file.
    $_cache_memory[$key] = $fromFile;
    return $fromFile['value'];
}

function cache_has(string $key): bool {
    return cache_get($key) !== null;
}

/** Remove one cached entry from both tiers. */
function cache_forget(string $key): void {
    global $_cache_memory;
    unset($_cache_memory[$key]);
    $path = cache_file_path($key);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Clear every cached entry (call after a mutation that affects cached data). */
function cache_clear(): void {
    global $_cache_memory;
    $_cache_memory = [];
    foreach (glob(CACHE_DIR . '/*.cache') ?: [] as $file) {
        @unlink($file);
    }
}

function cache_file_path(string $key): string {
    // Cache keys can contain characters that aren't safe filenames (":" etc);
    // hash them rather than sanitizing, so any key value is safe to use.
    return CACHE_DIR . '/' . hash('sha256', $key) . '.cache';
}

function cache_file_write(string $key, mixed $value, int $expiresAt): void {
    if (!is_dir(CACHE_DIR) && !@mkdir(CACHE_DIR, 0775, true) && !is_dir(CACHE_DIR)) {
        return; // Caching is a performance optimization, not a correctness requirement.
    }
    if (!is_writable(CACHE_DIR)) {
        return;
    }

    $payload = serialize(['expires' => $expiresAt, 'value' => $value]);
    $path = cache_file_path($key);

    // Write to a uniquely-named temp file, then rename into place. rename()
    // is atomic on the same filesystem, so a concurrent reader never sees a
    // partially-written cache file.
    $tmpPath = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmpPath, $payload) === false) {
        return;
    }
    if (!rename($tmpPath, $path)) {
        @unlink($tmpPath);
    }
}

/** @return array{value:mixed,expires:int}|null */
function cache_file_read(string $key): ?array {
    $path = cache_file_path($key);
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    // allowed_classes: false — cache payloads are arrays/scalars from our own
    // code, never objects; refusing object unserialization here costs
    // nothing and closes off PHP object-injection via a tampered cache file.
    $data = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($data) || !array_key_exists('expires', $data) || !array_key_exists('value', $data)) {
        return null;
    }

    if ($data['expires'] !== 0 && time() > $data['expires']) {
        @unlink($path);
        return null;
    }

    return ['value' => $data['value'], 'expires' => $data['expires']];
}

/**
 * Cache a value in the session — for state that's genuinely per-visitor,
 * not shared site-wide data (use cache_set() for that instead).
 */
function session_cache_set(string $key, mixed $value, int $ttlSeconds = 0): void {
    if (!isset($_SESSION['_cache'])) {
        $_SESSION['_cache'] = [];
        $_SESSION['_cache_ttl'] = [];
    }

    $_SESSION['_cache'][$key] = $value;
    $_SESSION['_cache_ttl'][$key] = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
}

function session_cache_get(string $key): mixed {
    if (!isset($_SESSION['_cache'][$key])) {
        return null;
    }

    $expiry = $_SESSION['_cache_ttl'][$key] ?? 0;
    if ($expiry > 0 && time() > $expiry) {
        unset($_SESSION['_cache'][$key], $_SESSION['_cache_ttl'][$key]);
        return null;
    }

    return $_SESSION['_cache'][$key];
}

function session_cache_has(string $key): bool {
    return session_cache_get($key) !== null;
}

function session_cache_clear(): void {
    $_SESSION['_cache'] = [];
    $_SESSION['_cache_ttl'] = [];
}

/** Invalidate all caches (call after any mutation to data the cache reflects). */
function cache_invalidate_all(): void {
    cache_clear();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_cache_clear();
    }
}

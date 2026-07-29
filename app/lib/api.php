<?php
/**
 * REST API: Generate keys, validate requests, log usage.
 * Endpoints: /api/v1/photos, /api/v1/orders, etc.
 */

declare(strict_types=1);

/**
 * Create API key for admin.
 */
function create_api_key(PDO $pdo, int $adminId, string $name, array $permissions): ?string {
    try {
        $key = bin2hex(random_bytes(32));
        $hash = hash('sha256', $key);

        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO api_keys (admin_id, name, key_hash, permissions)
            VALUES (?, ?, ?, ?)
        SQL);

        if ($stmt->execute([$adminId, $name, $hash, json_encode($permissions)])) {
            return $key;
        }
        return null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Validate API request.
 * Returns key record if valid, null otherwise.
 */
function validate_api_key(PDO $pdo, string $providedKey): ?array {
    try {
        $hash = hash('sha256', $providedKey);
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT id, admin_id, name, permissions, enabled, created_at, last_used_at FROM api_keys WHERE key_hash = ? AND enabled = 1
        SQL);
        $stmt->execute([$hash]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($key) {
            $stmt = $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?');
            $stmt->execute([$key['id']]);
        }

        return $key;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Check if key has required permission.
 */
function has_api_permission(array $key, string $permission): bool {
    $perms = json_decode($key['permissions'] ?? '[]', true);
    return in_array($permission, $perms) || in_array('*', $perms);
}

/**
 * Log API request.
 */
function log_api_request(PDO $pdo, int $keyId, string $endpoint, string $method, int $statusCode, int $responseTimeMs): void {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO api_logs (api_key_id, endpoint, method, status_code, response_time_ms)
            VALUES (?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$keyId, $endpoint, $method, $statusCode, $responseTimeMs]);
    } catch (Throwable $e) {
    }
}

/**
 * Get photos as JSON (API endpoint).
 */
function api_get_photos(PDO $pdo, int $page = 1, int $perPage = 50): array {
    try {
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT id, public_token, original_filename, price_pence, width, height,
                   view_count, created_at, camera_make, camera_model
            FROM photos
            WHERE status = 'live'
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        SQL);
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get photo details as JSON.
 */
function api_get_photo(PDO $pdo, int $photoId): ?array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT id, public_token, original_filename, price_pence, width, height,
                   view_count, created_at, camera_make, camera_model, description
            FROM photos WHERE id = ? AND status = 'live'
        SQL);
        $stmt->execute([$photoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Revoke API key.
 */
function revoke_api_key(PDO $pdo, int $keyId): bool {
    try {
        $stmt = $pdo->prepare('UPDATE api_keys SET enabled = 0 WHERE id = ?');
        return $stmt->execute([$keyId]);
    } catch (Throwable $e) {
        return false;
    }
}

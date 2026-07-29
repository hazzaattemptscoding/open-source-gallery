<?php
declare(strict_types=1);

/**
 * Fixed-window rate limiting using the rate_limits table.
 * Each bucket/rl_key pair tracks requests in a time window starting at window_start.
 * Once the window expires (60+ seconds passed), a new window begins.
 *
 * @param $pdo PDO instance
 * @param $bucket Type of limit (e.g., 'login', 'checkout', 'download', 'totp')
 * @param $rl_key Unique key within that bucket (e.g., 'ip', email, download_link_id)
 * @param $window_seconds Duration of the time window in seconds (e.g., 900 for 15 minutes, 3600 for 1 hour)
 * @param $max_hits Maximum hits allowed per window
 * @return bool True if under limit, false if limit exceeded
 */
function check_rate_limit(PDO $pdo, string $bucket, string $rl_key, int $window_seconds, int $max_hits): bool {
    $now = new DateTime();
    $windowStart = clone $now;
    $windowStart->modify("-{$window_seconds} seconds");

    $stmt = $pdo->prepare('
        SELECT hits FROM rate_limits
        WHERE bucket = ? AND rl_key = ? AND window_start > ?
        LIMIT 1
    ');
    $stmt->execute([$bucket, $rl_key, $windowStart->format('Y-m-d H:i:s')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Delete any old rows with the same key to avoid conflicts
        $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE bucket = ? AND rl_key = ?');
        $stmt->execute([$bucket, $rl_key]);

        // Now insert fresh row
        $stmt = $pdo->prepare('
            INSERT INTO rate_limits (bucket, rl_key, window_start, hits)
            VALUES (?, ?, ?, 1)
        ');
        $stmt->execute([$bucket, $rl_key, $now->format('Y-m-d H:i:s')]);
        return true;
    }

    $hits = (int)$row['hits'];
    if ($hits >= $max_hits) {
        return false;
    }

    $stmt = $pdo->prepare('
        UPDATE rate_limits SET hits = hits + 1
        WHERE bucket = ? AND rl_key = ?
    ');
    $stmt->execute([$bucket, $rl_key]);

    return true;
}

/**
 * Get best-effort client IP. For security, trusts REMOTE_ADDR only —
 * X-Forwarded-For is spoofable unless a trusted reverse proxy is configured.
 * This app runs with Apache facing the internet directly, not behind a proxy.
 * Use only REMOTE_ADDR for rate limiting and audit logging.
 */
function get_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

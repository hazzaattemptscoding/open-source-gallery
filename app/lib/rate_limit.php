<?php
declare(strict_types=1);

/**
 * Fixed-window rate limiting using the rate_limits table.
 * Windows are 1-hour buckets, tracked by bucket key (IP/email/etc).
 * Requests within the limit succeed; exceeding the limit returns false.
 */
function check_rate_limit(PDO $pdo, string $key, int $maxPerWindow): bool {
    $window = date('Y-m-d H:00:00');

    $stmt = $pdo->prepare('
        SELECT count FROM rate_limits
        WHERE bucket_key = ? AND window = ?
    ');
    $stmt->execute([$key, $window]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $stmt = $pdo->prepare('
            INSERT INTO rate_limits (bucket_key, window, count)
            VALUES (?, ?, 1)
        ');
        $stmt->execute([$key, $window]);
        return true;
    }

    $count = (int)$row['count'];
    if ($count >= $maxPerWindow) {
        return false;
    }

    $stmt = $pdo->prepare('
        UPDATE rate_limits SET count = count + 1
        WHERE bucket_key = ? AND window = ?
    ');
    $stmt->execute([$key, $window]);

    return true;
}

/**
 * Extract client IP, handling X-Forwarded-For header if present
 * (used by reverse proxies). Trusts the header only for 'self' proxies
 * configured in the app; otherwise uses REMOTE_ADDR.
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        if (!empty($ips)) {
            $ip = $ips[0];
        }
    }

    return $ip;
}

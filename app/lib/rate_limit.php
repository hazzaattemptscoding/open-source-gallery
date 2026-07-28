<?php
/**
 * Fixed-window rate limiting against the `rate_limits` table (one row per
 * bucket+key, per docs/architecture.md section 6). "Fixed window" means the
 * window resets to a fresh count the moment it expires, rather than sliding
 * — simpler to reason about, and precise enough for abuse control (login
 * brute force, checkout spam) where the goal is "not way more than N in
 * roughly this long", not exact throttling.
 *
 * The increment-or-reset decision happens inside a single INSERT ... ON
 * DUPLICATE KEY UPDATE so two near-simultaneous requests for the same
 * bucket+key can't both read "0 hits" and both proceed — the UPDATE clause
 * runs under MySQL's row lock on the existing key, not as a separate
 * check-then-write from PHP.
 */

declare(strict_types=1);

/**
 * Record a hit for $bucket+$key and report whether it's still within the
 * limit. Always records the hit (even when it pushes over the limit) so a
 * caller that ignores the false return still has enforcement baked in on
 * the next check.
 */
function rate_limit_check(PDO $pdo, string $bucket, string $key, int $windowSeconds, int $maxHits): bool
{
    $stmt = $pdo->prepare('
        INSERT INTO rate_limits (bucket, rl_key, window_start, hits)
        VALUES (:bucket, :rl_key, NOW(), 1)
        ON DUPLICATE KEY UPDATE
            hits = IF(window_start < NOW() - INTERVAL :window_a SECOND, 1, hits + 1),
            window_start = IF(window_start < NOW() - INTERVAL :window_b SECOND, NOW(), window_start)
    ');
    $stmt->execute([
        'bucket'   => $bucket,
        'rl_key'   => $key,
        'window_a' => $windowSeconds,
        'window_b' => $windowSeconds,
    ]);

    $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = :bucket AND rl_key = :rl_key');
    $stmt->execute(['bucket' => $bucket, 'rl_key' => $key]);
    $hits = (int) $stmt->fetchColumn();

    return $hits <= $maxHits;
}

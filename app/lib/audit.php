<?php
/**
 * Writes to `audit_log`. Every admin action (and the public/system actions
 * docs/architecture.md calls out — login attempts, refunds, downloads) goes
 * through this one function so the log format never drifts between callers.
 */

declare(strict_types=1);

/**
 * @param string $actor 'admin'|'system'|'public'
 * @param array<string,mixed>|null $meta arbitrary structured detail, stored as JSON
 */
function audit_log(PDO $pdo, string $actor, string $action, ?string $entityType = null, ?int $entityId = null, ?array $meta = null, ?string $ip = null): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO audit_log (actor, action, entity_type, entity_id, meta, ip, created_at)
            VALUES (:actor, :action, :entity_type, :entity_id, :meta, :ip, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            'actor'       => $actor,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'meta'        => $meta !== null ? json_encode($meta) : null,
            'ip'          => $ip !== null ? inet_pton($ip) : null,
        ]);
    } catch (Throwable $e) {
        error_log("audit_log failed: {$e->getMessage()}");
    }
}

/**
 * Get client IP for audit/rate-limit keys. Trusts REMOTE_ADDR only —
 * X-Forwarded-For is spoofable unless a trusted proxy is configured.
 * This app runs with Apache facing the internet directly.
 */
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

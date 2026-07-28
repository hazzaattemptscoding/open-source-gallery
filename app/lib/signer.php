<?php
declare(strict_types=1);

/**
 * HMAC-SHA256 signing for anything that must round-trip through a client
 * (cart cookie, per-file download URLs) without a server-side session
 * table. The signature proves the payload wasn't tampered with; it says
 * nothing about freshness, so callers that need expiry embed it in the
 * payload themselves (see docs/architecture.md sections 4 and 6).
 */
function sign_payload(string $payload, string $hmacKey): string {
    return hash_hmac('sha256', $payload, $hmacKey);
}

/** Constant-time signature check — never compare HMACs with ==. */
function verify_payload(string $payload, string $signature, string $hmacKey): bool {
    return hash_equals(sign_payload($payload, $hmacKey), $signature);
}

<?php
/**
 * CSRF protection for admin mutations. Per docs/architecture.md section 6:
 * every mutating admin request carries a per-session token, checked with
 * hash_equals (constant-time, so comparing the token can't leak how many
 * leading bytes matched via timing).
 *
 * Cart endpoints deliberately skip this (see architecture section 4: a
 * forged cart-add is harmless since prices are always re-read from the DB
 * at checkout) — this file is for admin/checkout mutations only.
 */

declare(strict_types=1);

/** Return this session's CSRF token, generating one on first call. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Check a submitted token against the session's token. On success, invalidates
 * the token to prevent replay attacks (one-time use only).
 */
function csrf_verify(?string $submitted): bool
{
    if (empty($_SESSION['csrf_token']) || $submitted === null) {
        return false;
    }

    if (!hash_equals($_SESSION['csrf_token'], $submitted)) {
        return false;
    }

    // Token verified successfully. Invalidate it immediately to prevent replay.
    unset($_SESSION['csrf_token']);

    return true;
}

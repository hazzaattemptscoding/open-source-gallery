<?php
/**
 * RFC 6238 (TOTP) / RFC 4226 (HOTP) hand-rolled, per docs/architecture.md
 * section 1: "TOTP is ~60 lines of RFC 6238 and will be hand-rolled with
 * test vectors rather than pulling a dependency." No third-party library,
 * no Composer package — just the spec.
 *
 * totp_hotp() is kept separate from totp_code() so the core HOTP maths can
 * be verified directly against the raw test vectors in RFC 4226 appendix D
 * (which use an ASCII key, not a base32 secret) without going through
 * base32 decoding at all.
 */

declare(strict_types=1);

/**
 * RFC 4648 base32 decode (uppercase alphabet, '=' padding optional).
 * Authenticator apps (Google Authenticator, Authy, etc.) expect secrets
 * base32-encoded, so this is the boundary between "what a human types into
 * an app" and "the raw bytes HMAC actually uses".
 */
function totp_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(rtrim($b32, '='));

    $bits = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue; // skip whitespace/separators a human might paste in
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes .= chr((int) bindec($byte));
        }
    }

    return $bytes;
}

/** RFC 4648 base32 encode. Used only when generating a new secret to show the admin. */
function totp_base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }

    $b32 = '';
    foreach (str_split($bits, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $b32 .= $alphabet[bindec($chunk)];
    }

    return $b32;
}

/**
 * RFC 4226 HOTP: HMAC-SHA1 over the 8-byte big-endian counter, then dynamic
 * truncation (the "DT" function in the RFC) to a decimal code of $digits.
 * Operates on raw key bytes — no base32 involved — so it can be checked
 * directly against RFC 4226 appendix D's test vectors.
 */
function totp_hotp(string $keyBytes, int $counter, int $digits = 6): string
{
    $counterBytes = pack('J', $counter); // 8-byte big-endian (PHP's 'J' is always big-endian)
    $hash = hash_hmac('sha1', $counterBytes, $keyBytes, true);

    $offset = ord($hash[19]) & 0x0F;
    $truncated =
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF);

    return str_pad((string) ($truncated % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/** Generate a fresh random secret for enrolling a new TOTP device, base32-encoded for display. */
function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

/**
 * otpauth:// URI for an authenticator app that supports scanning/pasting a
 * URI. No QR code is generated (that needs an image library or a JS
 * dependency this project doesn't carry) — the enrollment page shows this
 * URI and the raw secret as text; every authenticator app supports manual
 * entry of either.
 */
function totp_enrollment_uri(string $secretBase32, string $accountLabel, string $issuer): string
{
    return sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        rawurlencode($issuer),
        rawurlencode($accountLabel),
        $secretBase32,
        rawurlencode($issuer)
    );
}

/**
 * Verify a submitted code against the current 30s step and, per
 * docs/architecture.md's replay guard, the step immediately before and
 * after it (±1 step ≈ ±30s of clock drift). $lastAcceptedStep is the
 * admin's stored totp_last_step: a step at or before it is rejected even
 * if the code is numerically correct, so a stolen/observed code can't be
 * replayed within its validity window.
 *
 * @return int|null the step number that matched (persist this as the new
 *                   totp_last_step), or null if no step in the window matched.
 */
function totp_verify(string $secretBase32, string $code, ?int $lastAcceptedStep, int $step = 30, int $digits = 6, int $window = 1): ?int
{
    $keyBytes = totp_base32_decode($secretBase32);
    $currentStep = intdiv(time(), $step);

    for ($i = -$window; $i <= $window; $i++) {
        $candidateStep = $currentStep + $i;

        if ($lastAcceptedStep !== null && $candidateStep <= $lastAcceptedStep) {
            continue; // already used (or older than a step already used) — replay
        }

        if (hash_equals(totp_hotp($keyBytes, $candidateStep, $digits), $code)) {
            return $candidateStep;
        }
    }

    return null;
}

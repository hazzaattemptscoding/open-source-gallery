<?php
/**
 * Starts the PHP session with the cookie flags docs/architecture.md section
 * 6 requires: HttpOnly (JS can't read it, so XSS can't steal the session),
 * SameSite=Strict (the cookie isn't sent on cross-site navigation, so a
 * malicious site can't ride the admin's session), and Secure whenever the
 * request actually arrived over HTTPS.
 *
 * Secure is conditional rather than hardcoded true: IONOS terminates TLS at
 * Apache and sets $_SERVER['HTTPS'], but local dev (php -S, or a Docker
 * container without TLS) serves plain HTTP, and a hardcoded Secure flag
 * would make the browser silently refuse to send the cookie at all there —
 * login would look broken with no error to explain why.
 */

declare(strict_types=1);

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0, // session cookie; expires when the browser closes
        'path'     => '/',
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Strict',
    ]);

    session_start();
}

/**
 * Call on successful login. Regenerating the session ID after auth stops
 * session fixation: an attacker who planted a session ID before login
 * (e.g. via a link with a pre-set cookie) gains nothing, because the ID
 * changes the moment the real admin authenticates.
 */
function session_regenerate_after_login(): void
{
    session_regenerate_id(true);
}

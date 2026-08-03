<?php
/**
 * Fetch a document from a URL an admin typed in.
 *
 * This exists so an entry list can be imported from a published URL instead of
 * being downloaded and re-uploaded by hand. That convenience is the entire
 * feature; the rest of this file is about not turning it into a hole.
 *
 * The risk, stated plainly
 * -----------------------
 * A server that fetches a URL supplied by a user is a server that can be
 * pointed at things the user cannot reach themselves. On shared hosting the
 * interesting targets are the loopback interface, the private ranges the host's
 * own control panel sits on, and cloud metadata endpoints such as 169.254.169.254
 * which hand out credentials to anyone who asks. That class of bug is SSRF, and
 * it is the reason this is a separate, heavily-guarded file rather than three
 * lines of curl inline in a controller.
 *
 * The guards, and why each one is needed
 * --------------------------------------
 *  - Scheme allowlist. Without it, file:// reads local files and gopher:// can
 *    be used to talk to other protocols entirely.
 *  - Address checking, applied to every hop. Checking the hostname is not
 *    enough: DNS can resolve a perfectly innocent name to 127.0.0.1, so what is
 *    actually validated is the resolved address.
 *  - Redirects followed manually, one at a time, re-validating each. curl's own
 *    redirect following would check the first URL and then happily follow a
 *    302 to localhost. This is the specific bypass that makes naive SSRF
 *    protection useless.
 *  - Size cap and timeout, so a hostile or broken endpoint cannot exhaust
 *    memory or hold a request open.
 *
 * There is deliberately no allowlist of permitted hosts. An entry list can live
 * anywhere, and a list that has to be edited in config every time a club changes
 * timing provider would simply not be used.
 */

declare(strict_types=1);

/** Largest document worth pulling for an entry list. */
const REMOTE_FETCH_MAX_BYTES = 2 * 1024 * 1024;

/** Seconds before giving up, total. */
const REMOTE_FETCH_TIMEOUT = 15;

/** How many redirects to follow before concluding it is a loop. */
const REMOTE_FETCH_MAX_REDIRECTS = 3;

/**
 * Is this IP address one the server must refuse to connect to?
 *
 * Rejects loopback, private, link-local (which covers cloud metadata at
 * 169.254.169.254), and reserved ranges. Uses PHP's own filter for the ranges
 * it knows, then adds the checks it does not cover.
 */
function remote_fetch_address_is_blocked(string $ip): bool
{
    // Not an IP at all: refuse rather than guess.
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return true;
    }

    // FILTER_FLAG_NO_PRIV_RANGE and NO_RES_RANGE return false when the address
    // IS private or reserved, which is exactly what must be blocked.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }

    // Belt and braces for the ranges that matter most, in case a PHP build
    // disagrees about what counts as reserved.
    if (str_starts_with($ip, '127.') || $ip === '0.0.0.0' || $ip === '::1') {
        return true;
    }
    if (str_starts_with($ip, '169.254.')) {   // link-local, incl. cloud metadata
        return true;
    }
    if (str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.')) {
        return true;
    }
    // 172.16.0.0 – 172.31.255.255
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $ip) === 1) {
        return true;
    }

    return false;
}

/**
 * Validate a URL and resolve it to an address that is safe to connect to.
 *
 * @return array{ok:bool, error?:string, host?:string, ip?:string}
 */
function remote_fetch_validate_url(string $url): array
{
    $parts = parse_url($url);

    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return ['ok' => false, 'error' => 'That does not look like a full URL.'];
    }

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'error' => 'Only http and https URLs can be imported.'];
    }

    $host = $parts['host'];

    // A literal IP is checked directly; a name is resolved first, because the
    // name tells us nothing about where it points.
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $ip = $host;
    } else {
        $records = @gethostbynamel($host);
        if ($records === false || $records === []) {
            return ['ok' => false, 'error' => 'That address could not be found.'];
        }

        // Every resolved address must be acceptable. A name that resolves to
        // both a public and a private address is a classic way to slip past a
        // check that only looks at the first record.
        foreach ($records as $candidate) {
            if (remote_fetch_address_is_blocked($candidate)) {
                return ['ok' => false, 'error' => 'That address is not allowed.'];
            }
        }
        $ip = $records[0];
    }

    if (remote_fetch_address_is_blocked($ip)) {
        return ['ok' => false, 'error' => 'That address is not allowed.'];
    }

    return ['ok' => true, 'host' => $host, 'ip' => $ip];
}

/**
 * Fetch a URL, following redirects manually and re-validating each hop.
 *
 * Manual redirect handling is the point. Letting curl follow redirects would
 * validate only the URL that was typed, and a 302 to http://127.0.0.1/ would
 * then be followed without a further check, which defeats every guard above.
 *
 * @return array{ok:bool, error?:string, body?:string, content_type?:string, final_url?:string}
 */
function remote_fetch(string $url): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'This server cannot fetch remote URLs (curl is not available).'];
    }

    $seen = [];

    for ($hop = 0; $hop <= REMOTE_FETCH_MAX_REDIRECTS; $hop++) {
        $check = remote_fetch_validate_url($url);
        if (!$check['ok']) {
            return ['ok' => false, 'error' => $check['error']];
        }

        if (isset($seen[$url])) {
            return ['ok' => false, 'error' => 'That URL redirects in a loop.'];
        }
        $seen[$url] = true;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,   // handled here, see above
            CURLOPT_TIMEOUT => REMOTE_FETCH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_MAXFILESIZE => REMOTE_FETCH_MAX_BYTES,
            CURLOPT_USERAGENT => 'open-source-gallery entry-list importer',
            CURLOPT_HEADER => false,
            // Stop reading as soon as the cap is passed. MAXFILESIZE only acts
            // on a declared Content-Length, so a response without one needs
            // this to be bounded at all.
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
                static $total = 0;
                $total += strlen($chunk);
                if ($total > REMOTE_FETCH_MAX_BYTES) {
                    return 0; // aborts the transfer
                }
                echo $chunk;
                return strlen($chunk);
            },
        ]);

        ob_start();
        $ok = curl_exec($ch);
        $body = (string) ob_get_clean();

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $redirectUrl = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $error = curl_error($ch);
        curl_close($ch);

        if ($ok === false && $body === '') {
            return ['ok' => false, 'error' => 'Could not fetch that URL: ' . ($error ?: 'no response')];
        }

        if ($status >= 300 && $status < 400 && $redirectUrl !== '') {
            $url = $redirectUrl;
            continue;
        }

        if ($status !== 200) {
            return ['ok' => false, 'error' => "That URL returned HTTP {$status}."];
        }

        if (strlen($body) > REMOTE_FETCH_MAX_BYTES) {
            return ['ok' => false, 'error' => 'That file is too large to import.'];
        }

        return [
            'ok' => true,
            'body' => $body,
            'content_type' => $contentType,
            'final_url' => $url,
        ];
    }

    return ['ok' => false, 'error' => 'That URL redirected too many times.'];
}

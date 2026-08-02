<?php
/**
 * Unsubscribe from marketing email.
 *
 * Two steps on purpose, GET then POST:
 *
 * The GET shows a page with one button. It does not unsubscribe. Mail clients
 * and corporate security scanners routinely prefetch links in email, and a GET
 * that mutates would silently unsubscribe people who never clicked anything,
 * which is both a bad experience and a way to lose consent records you cannot
 * reconstruct.
 *
 * The POST performs it. One button, no login, no password, no "tell us why"
 * survey: an unsubscribe that is hard to complete is the same as one that does
 * not work, and regulators treat it that way.
 *
 * No CSRF token is required on the POST. The unsubscribe token in the URL is
 * itself the only capability involved, and a forged request can do nothing
 * except stop marketing email to someone who is holding that link. Requiring a
 * session token would break the flow for anyone who opened the link in a
 * different browser from the one they bought in, which is most people.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/consent.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

/**
 * GET /unsubscribe/{token}
 *
 * Renders the confirmation page. An unknown token still renders a normal-looking
 * page rather than a 404, so the URL cannot be used to test whether a given
 * token, and therefore a given contact, exists.
 */
function public_unsubscribe_page_controller(PDO $pdo, array $config, string $token): void
{
    $contact = find_contact_by_unsubscribe_token($pdo, $token);

    render(__DIR__ . '/../../views/public/unsubscribe.php', [
        'pageTitle'    => 'Unsubscribe',
        'siteName'     => $config['site']['name'] ?? 'Gallery',
        'token'        => $token,
        'contact'      => $contact,
        'alreadyDone'  => $contact !== null && !empty($contact['unsubscribed_at']),
        'done'         => false,
    ]);
}

/**
 * POST /unsubscribe
 *
 * Performs the unsubscribe. Always reports success to the visitor, whether or
 * not the token matched: someone who clicked unsubscribe wants to be told it
 * worked, and distinguishing "unknown token" from "done" here would leak
 * whether an address is on the list.
 *
 * Rate limited per IP because it is an unauthenticated write. The limit is
 * generous: the only thing a flood achieves is unsubscribing tokens the caller
 * already holds, so this is about protecting the database rather than the
 * contacts.
 */
function public_unsubscribe_submit_controller(PDO $pdo, array $config): void
{
    $token = trim((string)($_POST['token'] ?? ''));
    $ip = get_client_ip();

    $maxAttempts = adjust_rate_limit_for_dev($config, 60);
    if (!check_rate_limit($pdo, 'unsubscribe', $ip, 3600, $maxAttempts)) {
        http_response_code(429);
        render(__DIR__ . '/../../views/public/unsubscribe.php', [
            'pageTitle'   => 'Unsubscribe',
            'siteName'    => $config['site']['name'] ?? 'Gallery',
            'token'       => $token,
            'contact'     => null,
            'alreadyDone' => false,
            'done'        => false,
            'error'       => 'Too many requests just now. Please try again in a few minutes.',
        ]);
        return;
    }

    unsubscribe_by_token($pdo, $token, $ip);

    render(__DIR__ . '/../../views/public/unsubscribe.php', [
        'pageTitle'   => 'Unsubscribed',
        'siteName'    => $config['site']['name'] ?? 'Gallery',
        'token'       => $token,
        'contact'     => null,
        'alreadyDone' => false,
        'done'        => true,
    ]);
}

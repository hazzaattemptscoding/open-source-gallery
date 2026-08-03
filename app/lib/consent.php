<?php
/**
 * Marketing consent: who this install is allowed to send campaign email to,
 * on what lawful basis, and how they stop it.
 *
 * The distinction this file exists to enforce
 * -------------------------------------------
 * Transactional email (receipt, download link, refund notice) completes a
 * transaction the customer asked for. It needs no consent and must keep sending
 * whatever is recorded here. Nothing in this file gates it, and nothing in this
 * file should ever be called from the receipt path.
 *
 * Campaign email (gallery live, early bird ending, abandoned cart, gallery
 * expiring) is marketing. Under UK PECR it needs either an explicit opt-in or
 * the "soft opt-in" that applies to existing customers, and every message needs
 * a working unsubscribe. can_send_marketing() is the single gate for that, and
 * every campaign send must pass through it.
 *
 * Getting this wrong is not a cosmetic bug. It is the kind of thing that
 * generates complaints to the ICO, so the code errs towards not sending: an
 * unknown address, a missing row, or any database trouble all resolve to "no".
 */

declare(strict_types=1);

require_once __DIR__ . '/audit.php';

/** The person actively ticked a box. Needed for anyone who has not bought. */
const CONSENT_BASIS_EXPLICIT = 'explicit';

/**
 * PECR reg. 22(3) soft opt-in: they bought something, the marketing concerns
 * similar products, and every message offers an opt-out. This is what lets a
 * buyer be told their gallery is expiring without having ticked anything.
 */
const CONSENT_BASIS_SOFT_OPTIN = 'soft_optin';

/**
 * Create an unguessable unsubscribe token.
 *
 * 16 random bytes as 32 hex characters, matching the column. It travels in the
 * footer of every campaign email and has to work with no login, so it must not
 * be derivable from the email address: otherwise anyone could unsubscribe
 * anyone, or worse, enumerate who is on the list.
 */
function generate_unsubscribe_token(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Find a contact by email, or null.
 *
 * @return array<string,mixed>|null
 */
function find_contact(PDO $pdo, string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM contacts WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('find_contact failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Ensure a contact row exists for this address and return it.
 *
 * Creating the row does not grant consent. A contact exists as soon as we hold
 * an address at all, with marketing_consent defaulting to 0; consent is a
 * separate, deliberate act recorded by record_consent().
 *
 * @return array<string,mixed>|null Null only if the row could not be created.
 */
function ensure_contact(PDO $pdo, string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $existing = find_contact($pdo, $email);
    if ($existing !== null) {
        return $existing;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO contacts (email, marketing_consent, unsubscribe_token) VALUES (?, 0, ?)'
        );
        $stmt->execute([$email, generate_unsubscribe_token()]);
    } catch (Throwable $e) {
        // A duplicate here means a concurrent request created it first, which
        // is a normal race and not an error: re-read and carry on.
        error_log('ensure_contact insert: ' . $e->getMessage());
    }

    return find_contact($pdo, $email);
}

/**
 * Record that someone may be sent marketing email, and why.
 *
 * Deliberately refuses to silently resurrect an unsubscribed contact under the
 * soft opt-in basis. Someone who has actively unsubscribed and then buys again
 * has not changed their mind, and quietly re-adding them because they made a
 * purchase is exactly the behaviour that generates complaints. An explicit
 * opt-in does override it, because that is them changing their mind on purpose.
 *
 * @param string $basis CONSENT_BASIS_EXPLICIT or CONSENT_BASIS_SOFT_OPTIN
 * @return bool True when consent is now recorded.
 */
function record_consent(PDO $pdo, string $email, string $basis, ?string $ip = null): bool
{
    if (!in_array($basis, [CONSENT_BASIS_EXPLICIT, CONSENT_BASIS_SOFT_OPTIN], true)) {
        return false;
    }

    $contact = ensure_contact($pdo, $email);
    if ($contact === null) {
        return false;
    }

    if ($basis === CONSENT_BASIS_SOFT_OPTIN && !empty($contact['unsubscribed_at'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE contacts
                SET marketing_consent = 1,
                    consent_basis = ?,
                    consent_at = CURRENT_TIMESTAMP,
                    consent_ip = ?,
                    unsubscribed_at = NULL,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = ?'
        );
        $stmt->execute([$basis, $ip, $contact['id']]);
    } catch (Throwable $e) {
        error_log('record_consent failed: ' . $e->getMessage());
        return false;
    }

    audit_log($pdo, 'public', 'marketing_consent_given', 'contact', (int)$contact['id'], [
        'basis' => $basis,
    ], $ip);

    return true;
}

/**
 * The single gate every campaign send must pass through.
 *
 * Fails closed. An address with no contact row, no consent, an unsubscribe on
 * record, or a database error all return false, because the cost of wrongly
 * not sending a marketing email is an email nobody missed, and the cost of
 * wrongly sending one is a complaint to the regulator.
 */
function can_send_marketing(PDO $pdo, string $email): bool
{
    $contact = find_contact($pdo, $email);
    if ($contact === null) {
        return false;
    }

    if (!empty($contact['unsubscribed_at'])) {
        return false;
    }

    return (int)($contact['marketing_consent'] ?? 0) === 1;
}

/**
 * Look up a contact by unsubscribe token.
 *
 * Shape-checked before querying, so a malformed token never becomes a database
 * round trip.
 *
 * @return array<string,mixed>|null
 */
function find_contact_by_unsubscribe_token(PDO $pdo, string $token): ?array
{
    if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM contacts WHERE unsubscribe_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('find_contact_by_unsubscribe_token failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Unsubscribe by token.
 *
 * Idempotent: unsubscribing twice is a success, because the caller's intent is
 * already satisfied and showing an error to someone who clicked twice would be
 * absurd.
 *
 * The token is left in place rather than rotated, so the same link keeps
 * working if the person clicks an older email later. There is nothing to
 * protect by rotating it: the only thing the token can do is stop email.
 */
function unsubscribe_by_token(PDO $pdo, string $token, ?string $ip = null): bool
{
    $contact = find_contact_by_unsubscribe_token($pdo, $token);
    if ($contact === null) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE contacts
                SET marketing_consent = 0,
                    unsubscribed_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = ?'
        );
        $stmt->execute([$contact['id']]);
    } catch (Throwable $e) {
        error_log('unsubscribe_by_token failed: ' . $e->getMessage());
        return false;
    }

    audit_log($pdo, 'public', 'marketing_unsubscribed', 'contact', (int)$contact['id'], [], $ip);

    return true;
}

/**
 * The unsubscribe URL to put in a campaign email footer.
 *
 * Absolute, because it is being read inside a mail client with no page to be
 * relative to.
 */
function unsubscribe_url(array $config, string $token): string
{
    return rtrim(site_base_url($config) ?? '', '/') . '/unsubscribe/' . $token;
}

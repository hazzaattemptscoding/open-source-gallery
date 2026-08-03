<?php
/**
 * Nudge campaigns: the marketing emails that run themselves off cron.
 *
 * What is here, and what is deliberately not
 * ------------------------------------------
 * The plan called for four campaigns. Two are built:
 *
 *   gallery_live    a published gallery is announced to consented contacts
 *   abandoned_cart  a checkout was started and never paid for
 *
 * Two are not, because they describe features this application does not have,
 * and an email is not a substitute for the thing it talks about:
 *
 *   early_bird_ending  requires a time-limited launch discount. There is no
 *                      such concept: pricing is per-event columns plus fixed
 *                      volume tiers, with no window and no deadline. Sending
 *                      "your discount ends in 24 hours" when no discount exists
 *                      and nothing changes at the deadline is a false statement
 *                      to a customer, not a nudge.
 *
 *   gallery_expiring   requires galleries to expire. They do not. Derivative
 *                      tiering was deliberately removed (see cron.php and
 *                      migration 011): every size is now kept for the life of
 *                      the photo precisely so late discovery still converts.
 *                      Warning people about an expiry that will never happen is
 *                      a fabricated deadline.
 *
 * Both need a product decision and a feature first. When those exist, they slot
 * in here as two more scan functions; the infrastructure below is already
 * general enough.
 *
 * Rules every campaign obeys
 * --------------------------
 *  - Consent is checked per recipient through can_send_marketing(), which fails
 *    closed. No campaign may bypass it.
 *  - Every message carries a working unsubscribe link.
 *  - A send is claimed by INSERTing into campaign_sends before the email is
 *    queued. Cron runs every five minutes and scans re-see the same subjects,
 *    so the unique key is what stops a gallery being announced every five
 *    minutes forever.
 *  - Every send is written to the audit log.
 */

declare(strict_types=1);

require_once __DIR__ . '/consent.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/audit.php';

/**
 * How long after a gallery is published it is still worth announcing.
 *
 * A gallery published a fortnight ago is not news, and announcing it late looks
 * like a bug to the recipient. This also bounds the damage if the scanner is
 * off for a while: it catches up on the last few days rather than the archive.
 */
const CAMPAIGN_GALLERY_LIVE_MAX_AGE_HOURS = 72;

/** How long a checkout has to sit unpaid before it counts as abandoned. */
const CAMPAIGN_ABANDONED_CART_AFTER_HOURS = 24;

/**
 * And how long after that it is too late to bother. Past this the customer has
 * moved on, and the reminder reads as nagging about something they abandoned
 * deliberately.
 */
const CAMPAIGN_ABANDONED_CART_MAX_AGE_HOURS = 96;

/** Most emails one campaign will send per cron tick, so a drain stays bounded. */
const CAMPAIGN_BATCH_LIMIT = 50;

/**
 * Is this campaign switched on?
 *
 * Defaults to off. An install that upgrades into this code should not start
 * emailing its customers because a migration ran; switching each campaign on is
 * a deliberate act by the admin.
 */
function campaign_enabled(PDO $pdo, string $campaign): bool
{
    return (bool) get_setting($pdo, 'campaigns', $campaign . '_enabled', false);
}

/**
 * Claim the right to send one campaign email.
 *
 * Returns false if this exact message has already been sent, including when a
 * concurrent cron run claimed it a microsecond ago: the unique key on
 * campaign_sends turns that race into a duplicate-key error, which is caught
 * here and reported as "already sent". Doing this with a SELECT then an INSERT
 * would leave a window where two runs both decide to send.
 */
function claim_campaign_send(PDO $pdo, string $campaign, int $contactId, string $subjectKey): bool
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO campaign_sends (campaign, contact_id, subject_key) VALUES (?, ?, ?)'
        );
        $stmt->execute([$campaign, $contactId, $subjectKey]);
        return true;
    } catch (Throwable $e) {
        // Unique violation is the expected, normal path once a message has gone
        // out. Anything else is worth a log line but still means "do not send".
        if (!str_contains(strtolower($e->getMessage()), 'unique')
            && !str_contains(strtolower($e->getMessage()), 'duplicate')) {
            error_log('claim_campaign_send failed: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * Wrap campaign body copy in the shared shell, with the unsubscribe footer.
 *
 * The footer is added here rather than left to each campaign, so a new campaign
 * physically cannot ship without one.
 */
function render_campaign_email(array $config, string $heading, string $bodyHtml, string $unsubscribeUrl): array
{
    $siteName = htmlspecialchars($config['site']['name'] ?? 'Gallery', ENT_QUOTES, 'UTF-8');
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $safeUnsub = htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!doctype html>
<html><body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1a1a1a;background:#ffffff;">
  <div style="max-width:34rem;margin:0 auto;">
    <p style="font-size:13px;letter-spacing:0.04em;text-transform:uppercase;color:#6b6b6b;margin:0 0 16px;">{$siteName}</p>
    <h1 style="font-size:22px;margin:0 0 16px;">{$safeHeading}</h1>
    {$bodyHtml}
    <hr style="border:none;border-top:1px solid #e5e5e5;margin:32px 0 16px;">
    <p style="font-size:12px;color:#6b6b6b;margin:0;">
      You are receiving this because you asked to hear about new galleries.
      <a href="{$safeUnsub}" style="color:#6b6b6b;">Unsubscribe</a>.
    </p>
    <p style="font-size:12px;color:#6b6b6b;margin:8px 0 0;">
      Receipts and download links for anything you buy are sent separately and are not affected.
    </p>
  </div>
</body></html>
HTML;

    $text = $heading . "\n\n"
        . trim(strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", $bodyHtml)))
        . "\n\n---\nUnsubscribe: " . $unsubscribeUrl . "\n";

    return ['html' => $html, 'text' => $text];
}

/**
 * Send one campaign email to one contact, if everything permits it.
 *
 * The order here matters. Consent is checked first, then the send is claimed,
 * and only then is the mail actually sent: claiming before sending means a
 * transport failure does not cause the message to be retried on every cron tick
 * forever. The trade is that a genuinely failed send is not retried, which for
 * a marketing email is the right way round.
 *
 * Returns true when this call consumed a send, meaning it claimed the message
 * and handed it to the transport. Deliberately NOT the transport's own success
 * flag: callers use this to count against CAMPAIGN_BATCH_LIMIT, and if a broken
 * SMTP configuration made every delivery fail, a delivery-based count would
 * stay at zero and the batch limit would never trigger, so one cron tick would
 * grind through every contact times every event. Whether delivery actually
 * succeeded is recorded in the audit log, which is where that belongs.
 */
function send_campaign_email(
    PDO $pdo,
    array $config,
    string $campaign,
    array $contact,
    string $subjectKey,
    string $subject,
    string $heading,
    string $bodyHtml
): bool {
    $email = (string) $contact['email'];

    if (!can_send_marketing($pdo, $email)) {
        return false;
    }

    if (!claim_campaign_send($pdo, $campaign, (int) $contact['id'], $subjectKey)) {
        return false;
    }

    $rendered = render_campaign_email(
        $config,
        $heading,
        $bodyHtml,
        unsubscribe_url($config, (string) $contact['unsubscribe_token'])
    );

    $sent = send_email_via_configured_transport(
        $config,
        $email,
        $subject,
        $rendered['html'],
        $rendered['text']
    );

    audit_log($pdo, 'cron', 'campaign_email_sent', 'contact', (int) $contact['id'], [
        'campaign' => $campaign,
        'subject_key' => $subjectKey,
        'delivered' => $sent,
    ], null);

    // True because the send was claimed and attempted. See the docblock: this
    // is the batch-limit counter, not a delivery receipt.
    return true;
}

/** Everyone currently allowed to receive marketing email. */
function fetch_marketable_contacts(PDO $pdo, int $limit = 1000): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT id, email, unsubscribe_token FROM contacts
              WHERE marketing_consent = 1 AND unsubscribed_at IS NULL
              ORDER BY id ASC
              LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('fetch_marketable_contacts failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Campaign: a gallery has gone live.
 *
 * Only considers events published within the last few days and only those with
 * a known published_at, so upgrading an install with an archive of old events
 * does not announce all of them at once.
 *
 * @return int Emails sent.
 */
function scan_gallery_live_campaign(PDO $pdo, array $config): int
{
    if (!campaign_enabled($pdo, 'gallery_live')) {
        return 0;
    }

    $cutoff = date('Y-m-d H:i:s', time() - CAMPAIGN_GALLERY_LIVE_MAX_AGE_HOURS * 3600);

    $stmt = $pdo->prepare(
        "SELECT id, slug, title FROM events
          WHERE is_published = 1
            AND published_at IS NOT NULL
            AND published_at >= ?
          ORDER BY published_at DESC"
    );
    $stmt->execute([$cutoff]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($events)) {
        return 0;
    }

    $contacts = fetch_marketable_contacts($pdo);
    if (empty($contacts)) {
        return 0;
    }

    $baseUrl = rtrim(site_base_url($config) ?? '', '/');
    $sent = 0;

    foreach ($events as $event) {
        $subjectKey = 'event:' . (int) $event['id'];
        $url = $baseUrl . '/e/' . rawurlencode((string) $event['slug']);
        $title = htmlspecialchars((string) $event['title'], ENT_QUOTES, 'UTF-8');

        $body = '<p style="font-size:16px;line-height:1.6;margin:0 0 20px;">'
              . 'Photos from <strong>' . $title . '</strong> are now online.</p>'
              . '<p style="font-size:16px;line-height:1.6;margin:0 0 24px;">'
              . 'Find yours by kart number, no account needed.</p>'
              . '<p style="margin:0 0 24px;"><a href="' . htmlspecialchars($url . '/find', ENT_QUOTES, 'UTF-8') . '" '
              . 'style="display:inline-block;padding:12px 24px;background:#1a1a1a;color:#ffffff;text-decoration:none;">'
              . 'Find your photos</a></p>';

        foreach ($contacts as $contact) {
            if ($sent >= CAMPAIGN_BATCH_LIMIT) {
                return $sent;
            }
            if (send_campaign_email(
                $pdo, $config, 'gallery_live', $contact, $subjectKey,
                'Your photos from ' . $event['title'] . ' are ready',
                'The gallery is live', $body
            )) {
                $sent++;
            }
        }
    }

    return $sent;
}

/**
 * Campaign: a checkout was started and never paid.
 *
 * The cart itself is a signed cookie and invisible to the server, so an
 * abandoned *cart* cannot be detected. What can be detected is an abandoned
 * *checkout*: an order row created and left pending. That is a stronger signal
 * anyway, since the customer got as far as entering their email address, and it
 * is the only version of this campaign that can be honest about who to contact.
 *
 * Only reaches people who have separately consented to marketing. An unpaid
 * order is not itself consent to be marketed at.
 *
 * @return int Emails sent.
 */
function scan_abandoned_cart_campaign(PDO $pdo, array $config): int
{
    if (!campaign_enabled($pdo, 'abandoned_cart')) {
        return 0;
    }

    $olderThan = date('Y-m-d H:i:s', time() - CAMPAIGN_ABANDONED_CART_AFTER_HOURS * 3600);
    $newerThan = date('Y-m-d H:i:s', time() - CAMPAIGN_ABANDONED_CART_MAX_AGE_HOURS * 3600);

    $stmt = $pdo->prepare(
        "SELECT o.id, o.email, o.total_pence
           FROM orders o
          WHERE o.status = 'pending'
            AND o.created_at <= ?
            AND o.created_at >= ?
            -- Never chase someone who has since bought something. They did not
            -- abandon anything; they just started twice.
            AND NOT EXISTS (
                SELECT 1 FROM orders paid
                 WHERE paid.email = o.email AND paid.status = 'paid'
            )
          ORDER BY o.created_at DESC
          LIMIT ?"
    );
    $stmt->bindValue(1, $olderThan);
    $stmt->bindValue(2, $newerThan);
    $stmt->bindValue(3, CAMPAIGN_BATCH_LIMIT, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($orders)) {
        return 0;
    }

    $baseUrl = rtrim(site_base_url($config) ?? '', '/');
    $sent = 0;

    foreach ($orders as $order) {
        $contact = find_contact($pdo, (string) $order['email']);
        if ($contact === null) {
            continue;
        }

        $body = '<p style="font-size:16px;line-height:1.6;margin:0 0 20px;">'
              . 'You started choosing photos but did not finish. They are still there.</p>'
              . '<p style="margin:0 0 24px;"><a href="' . htmlspecialchars($baseUrl . '/cart', ENT_QUOTES, 'UTF-8') . '" '
              . 'style="display:inline-block;padding:12px 24px;background:#1a1a1a;color:#ffffff;text-decoration:none;">'
              . 'Back to your photos</a></p>';

        if (send_campaign_email(
            $pdo, $config, 'abandoned_cart', $contact, 'order:' . (int) $order['id'],
            'Your photos are still waiting',
            'You left some photos behind', $body
        )) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * Run every campaign scan. Called once per cron drain.
 *
 * Each scan is isolated: a campaign that throws must not stop the others, and
 * must never stop the rest of the cron drain, which is doing load-bearing work
 * like generating derivatives.
 *
 * @return int Total emails sent this tick.
 */
function run_campaign_scans(PDO $pdo, array $config): int
{
    $total = 0;

    foreach (['scan_gallery_live_campaign', 'scan_abandoned_cart_campaign'] as $scan) {
        try {
            $total += $scan($pdo, $config);
        } catch (Throwable $e) {
            error_log('campaign scan ' . $scan . ' failed: ' . $e->getMessage());
        }
    }

    return $total;
}

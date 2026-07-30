<?php
declare(strict_types=1);

require_once __DIR__ . '/view.php';
require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/orders.php';

/**
 * Email delivery: renders app/templates/emails/*.html and sends via SMTP
 * (PHPMailer, suggested-not-required in composer.json) when configured, or
 * plain mail() otherwise. This is the send path process_email_job()
 * (app/lib/cron.php) calls for every queued receipt/refund job.
 *
 * PHPMailer is optionally vendored exactly like phpseclib is for remote
 * admin mode (see app/lib/sftp.php's class_exists() check): a host that
 * never fills in Admin -> Settings -> Email -> SMTP host sees zero
 * behavior change and never needs to install anything.
 */

/** True only when SMTP is both configured and actually installed. */
function smtp_is_available(array $smtpConfig): bool
{
    if (empty($smtpConfig['host'])) {
        return false; // not configured: mail() is the intended default, not a failure
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('SMTP host configured but phpmailer/phpmailer is not installed (see composer.json suggest / INSTALL.md). Falling back to mail().');
        return false;
    }

    return true;
}

/** Single send entrypoint every email call site should use instead of mail() directly. */
function send_email_via_configured_transport(array $config, string $recipient, string $subject, string $bodyHtml, string $bodyText = ''): bool
{
    $smtp = $config['smtp'] ?? [];

    return smtp_is_available($smtp)
        ? send_email_smtp($smtp, $recipient, $subject, $bodyHtml, $bodyText)
        : send_email_mail_fallback($recipient, $subject, $bodyHtml, $bodyText);
}

function send_email_smtp(array $smtp, string $recipient, string $subject, string $bodyHtml, string $bodyText): bool
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string)$smtp['host'];
        $mail->Port = (int)($smtp['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = (string)($smtp['user'] ?? '');
        $mail->Password = (string)($smtp['pass'] ?? '');
        $mail->SMTPSecure = ((int)($smtp['port'] ?? 587)) === 465
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom((string)($smtp['from_email'] ?: 'noreply@localhost'), (string)($smtp['from_name'] ?? ''));
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $bodyHtml;
        $mail->AltBody = $bodyText ?: strip_tags($bodyHtml);
        return $mail->send();
    } catch (\Throwable $e) {
        error_log('PHPMailer SMTP send failed: ' . $e->getMessage());
        return false;
    }
}

/** Plain mail(), unchanged from the previous send_email_direct() in email.php. */
function send_email_mail_fallback(string $recipient, string $subject, string $bodyHtml, string $bodyText = ''): bool
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: noreply@' . parse_url($_SERVER['HTTP_HOST'] ?? 'localhost', PHP_URL_HOST) . "\r\n";

    $body = $bodyText ?: strip_tags($bodyHtml);
    return (bool)mail($recipient, $subject, $body, $headers);
}

/**
 * Renders the receipt email for a paid order, or null if the order doesn't
 * exist. Split from send_receipt_email() so the composed content (subject,
 * html, recipient) can be asserted on directly in tests without touching
 * mail()/SMTP.
 *
 * @return array{recipient: string, subject: string, html: string, text: string}|null
 */
function render_receipt_email(PDO $pdo, array $config, int $orderId): ?array
{
    $stmt = $pdo->prepare('SELECT public_token, email, currency, total_pence, paid_at, created_at, download_url FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return null;
    }

    $items = get_order_items($pdo, $orderId);
    $currencyCode = (string)$order['currency'];
    $siteName = $config['site']['name'] ?? 'Gallery';
    $supportEmail = $config['site']['support_email'] ?? '';

    $downloadLink = (string)($order['download_url'] ?? '');
    if ($downloadLink === '') {
        // paid_at should always be set by the time a receipt is queued
        // (mark_order_paid() queues the job in the same call that sets it),
        // but fall back to creating the link rather than emailing a dead
        // "Download Files" button if this is ever called out of order.
        $downloadLink = get_or_create_download_link($pdo, $orderId, $config);
    }

    $vars = [
        'siteName' => $siteName,
        'orderNumber' => (string)$order['public_token'],
        'orderDate' => date('j F Y', strtotime((string)($order['paid_at'] ?? $order['created_at']))),
        'items' => $items,
        'currencyCode' => $currencyCode,
        'total' => format_pence((int)$order['total_pence'], $currencyCode),
        'downloadLink' => $downloadLink,
        'supportEmail' => $supportEmail,
    ];

    return [
        'recipient' => (string)$order['email'],
        'subject' => "Your order from {$siteName}",
        'html' => render_to_string(__DIR__ . '/../templates/emails/receipt.html', $vars),
        'text' => '',
    ];
}

/**
 * Renders the refund confirmation email for an order, or null if it
 * doesn't exist. $refundType is 'full' or 'partial' (matches
 * orders.status's 'refunded'/'partial_refund' values).
 *
 * @return array{recipient: string, subject: string, html: string, text: string}|null
 */
function render_refund_email(PDO $pdo, array $config, int $orderId, string $refundType): ?array
{
    $stmt = $pdo->prepare('SELECT public_token, email FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return null;
    }

    $siteName = $config['site']['name'] ?? 'Gallery';
    $supportEmail = $config['site']['support_email'] ?? '';

    $vars = [
        'siteName' => $siteName,
        'refundWord' => $refundType === 'partial' ? 'partial refund' : 'refund',
        'orderNumber' => (string)$order['public_token'],
        'supportEmail' => $supportEmail,
    ];

    return [
        'recipient' => (string)$order['email'],
        'subject' => "Refund processed for your order at {$siteName}",
        'html' => render_to_string(__DIR__ . '/../templates/emails/refund.html', $vars),
        'text' => '',
    ];
}

/** Called by process_email_job() (app/lib/cron.php) for type='receipt' jobs. */
function send_receipt_email(PDO $pdo, array $config, int $orderId): bool
{
    $rendered = render_receipt_email($pdo, $config, $orderId);
    if ($rendered === null) {
        return false;
    }

    return send_email_via_configured_transport($config, $rendered['recipient'], $rendered['subject'], $rendered['html'], $rendered['text']);
}

/** Called by process_email_job() (app/lib/cron.php) for type='refund' jobs. */
function send_refund_email(PDO $pdo, array $config, int $orderId, string $refundType): bool
{
    $rendered = render_refund_email($pdo, $config, $orderId, $refundType);
    if ($rendered === null) {
        return false;
    }

    return send_email_via_configured_transport($config, $rendered['recipient'], $rendered['subject'], $rendered['html'], $rendered['text']);
}

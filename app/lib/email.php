<?php
declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/currency.php';

/**
 * Send transactional emails: order receipts and refund confirmations.
 * Uses either mail() (sendmail_path) or SwiftMailer for SMTP if configured.
 */

/**
 * Send receipt email with download link after payment.
 */
function send_receipt_email(PDO $pdo, array $config, int $orderId): bool {
    $order = get_order_by_id($pdo, $orderId);
    if (!$order) {
        return false;
    }

    $items = get_order_items($pdo, $orderId);
    if (empty($items)) {
        return false;
    }

    $downloadLink = create_download_link($pdo, $orderId, $config);
    $currencyCode = $config['currency'] ?? 'GBP';
    $siteName = $config['site']['name'] ?? 'Gallery';

    $to = $order['email'];
    $subject = "{$siteName} — Your order receipt";

    // Build plain-text email (HTML can be added later via template)
    $itemsList = '';
    foreach ($items as $item) {
        $price = format_pence((int)$item['line_total_pence'], $currencyCode);
        $itemsList .= "  • {$item['description']} — {$price}\n";
    }

    $total = format_pence((int)$order['total_pence'], $currencyCode);
    $orderNumber = $order['public_token'];

    $body = <<<EOT
Thank you for your purchase!

Order Number: {$orderNumber}
Total: {$total}

Items:
{$itemsList}

Download your files here (valid for 30 days):
{$downloadLink}

If you have any questions, contact {$config['site']['support_email']}.

---
{$siteName}
EOT;

    return send_email($config, $to, $subject, $body);
}

/**
 * Send refund notification email.
 */
function send_refund_email(PDO $pdo, array $config, int $orderId, string $refundType = 'full'): bool {
    $order = get_order_by_id($pdo, $orderId);
    if (!$order) {
        return false;
    }

    $to = $order['email'];
    $siteName = $config['site']['name'] ?? 'Gallery';
    $refundWord = $refundType === 'full' ? 'refund' : 'partial refund';

    $subject = "{$siteName} — {$refundWord} processed";

    $body = <<<EOT
A {$refundWord} has been processed for your order {$order['public_token']}.

The refund will appear in your account within 5-7 business days.

If you have any questions, contact {$config['site']['support_email']}.

---
{$siteName}
EOT;

    return send_email($config, $to, $subject, $body);
}

/**
 * Send email via mail() or configured SMTP service.
 */
function send_email(array $config, string $to, string $subject, string $body): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = $config['smtp']['from_email'] ?? $config['site']['support_email'] ?? 'noreply@example.com';
    $fromName = $config['smtp']['from_name'] ?? $config['site']['name'] ?? 'Gallery';

    $headers = "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // If SMTP is configured, use it; otherwise fall back to mail()
    if (!empty($config['smtp']['host'])) {
        return send_email_smtp($config, $to, $subject, $body, $headers);
    }

    return (bool)mail($to, $subject, $body, $headers);
}

/**
 * Send email via SMTP (if SwiftMailer is available; otherwise log and return false).
 */
function send_email_smtp(array $config, string $to, string $subject, string $body, string $headers): bool {
    // For now, SMTP support is stubbed. In production with SwiftMailer:
    // $transport = (new Swift_SmtpTransport($config['smtp']['host'], $config['smtp']['port']))
    //     ->setUsername($config['smtp']['user'])
    //     ->setPassword($config['smtp']['pass']);
    // $mailer = new Swift_Mailer($transport);
    // ... etc
    //
    // Without a dependency, we fall back to mail() for shared hosting.
    return (bool)mail($to, $subject, $body, $headers);
}

function get_order_by_id(PDO $pdo, int $orderId): ?array {
    $stmt = $pdo->prepare('SELECT id, public_token, email, total_pence FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

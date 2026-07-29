<?php
declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/currency.php';

/**
 * Send transactional emails: order receipts and refund confirmations.
 * Renders HTML templates and sends via mail() or native SMTP.
 */

/**
 * Render an email template with variables.
 */
function render_email_template(string $template, array $variables): string {
    require_once __DIR__ . '/view.php';

    $templateFile = __DIR__ . "/../templates/emails/{$template}.html";
    if (!file_exists($templateFile)) {
        return '';
    }

    ob_start();
    try {
        extract($variables, EXTR_SKIP);
        require $templateFile;
        return ob_get_clean();
    } finally {
        ob_end_clean();
    }
}

/**
 * Send receipt email with HTML template and download link after payment.
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
    $supportEmail = $config['site']['support_email'] ?? 'support@example.com';

    $to = $order['email'];
    $subject = "{$siteName} — Your order receipt";
    $orderNumber = $order['public_token'];
    $total = format_pence((int)$order['total_pence'], $currencyCode);
    $orderDate = date('F j, Y', strtotime($order['created_at']));

    $html = render_email_template('receipt', [
        'siteName' => $siteName,
        'supportEmail' => $supportEmail,
        'orderNumber' => $orderNumber,
        'orderDate' => $orderDate,
        'items' => $items,
        'currencyCode' => $currencyCode,
        'total' => $total,
        'downloadLink' => $downloadLink,
    ]);

    if (empty($html)) {
        return false;
    }

    return send_email($config, $to, $subject, $html, isHtml: true);
}

/**
 * Send refund notification email with HTML template.
 */
function send_refund_email(PDO $pdo, array $config, int $orderId, string $refundType = 'full'): bool {
    $order = get_order_by_id($pdo, $orderId);
    if (!$order) {
        return false;
    }

    $to = $order['email'];
    $siteName = $config['site']['name'] ?? 'Gallery';
    $supportEmail = $config['site']['support_email'] ?? 'support@example.com';
    $refundWord = $refundType === 'full' ? 'refund' : 'partial refund';
    $orderNumber = $order['public_token'];

    $subject = "{$siteName} — {$refundWord} processed";

    $html = render_email_template('refund', [
        'siteName' => $siteName,
        'supportEmail' => $supportEmail,
        'orderNumber' => $orderNumber,
        'refundWord' => $refundWord,
    ]);

    if (empty($html)) {
        return false;
    }

    return send_email($config, $to, $subject, $html, isHtml: true);
}

/**
 * Send email via mail() or native SMTP.
 */
function send_email(array $config, string $to, string $subject, string $body, bool $isHtml = false): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = $config['smtp']['from_email'] ?? $config['site']['support_email'] ?? 'noreply@example.com';
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'noreply@example.com';
    }

    $fromName = $config['smtp']['from_name'] ?? $config['site']['name'] ?? 'Gallery';

    $headers = "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $contentType = $isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
    $headers .= "Content-Type: {$contentType}\r\n";

    if (!empty($config['smtp']['host'])) {
        return send_email_smtp($config, $to, $subject, $body, $headers);
    }

    return (bool)mail($to, $subject, $body, $headers);
}

/**
 * Send email via native SMTP (no external dependencies).
 */
function send_email_smtp(array $config, string $to, string $subject, string $body, string $headers): bool {
    $host = $config['smtp']['host'] ?? '';
    $port = (int)($config['smtp']['port'] ?? 587);
    $user = $config['smtp']['user'] ?? '';
    $pass = $config['smtp']['pass'] ?? '';

    if (empty($host) || empty($user) || empty($pass)) {
        return false;
    }

    try {
        $socket = fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, 10);

        $response = fgets($socket, 1024);
        if (strpos($response, '220') === false) {
            fclose($socket);
            return false;
        }

        $from = extract_email_address($headers);
        if (empty($from)) {
            fclose($socket);
            return false;
        }

        fputs($socket, "EHLO localhost\r\n");
        fgets($socket, 1024);

        fputs($socket, "STARTTLS\r\n");
        fgets($socket, 1024);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        fputs($socket, "EHLO localhost\r\n");
        fgets($socket, 1024);

        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '334') === false) {
            fclose($socket);
            return false;
        }

        fputs($socket, base64_encode($user) . "\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '334') === false) {
            fclose($socket);
            return false;
        }

        fputs($socket, base64_encode($pass) . "\r\n");
        $response = fgets($socket, 1024);
        if (strpos($response, '235') === false) {
            fclose($socket);
            return false;
        }

        fputs($socket, "MAIL FROM:<{$from}>\r\n");
        fgets($socket, 1024);

        fputs($socket, "RCPT TO:<{$to}>\r\n");
        fgets($socket, 1024);

        fputs($socket, "DATA\r\n");
        fgets($socket, 1024);

        fputs($socket, "To: {$to}\r\n");
        fputs($socket, "Subject: {$subject}\r\n");
        fputs($socket, $headers);
        fputs($socket, "\r\n");
        fputs($socket, $body . "\r\n");
        fputs($socket, ".\r\n");
        $response = fgets($socket, 1024);

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return strpos($response, '250') !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Extract email address from headers (From: line).
 */
function extract_email_address(string $headers): string {
    if (preg_match('/From:.*?<(.+?)>/i', $headers, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function get_order_by_id(PDO $pdo, int $orderId): ?array {
    $stmt = $pdo->prepare('SELECT id, public_token, email, total_pence, created_at FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

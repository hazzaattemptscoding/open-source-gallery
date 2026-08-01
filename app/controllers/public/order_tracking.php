<?php
/**
 * Public order tracking: customer views order status, downloads files, sees receipt.
 * Authenticated via order token + email verification (no accounts required).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/cache_headers.php';
require_once __DIR__ . '/../../lib/orders.php';

function public_order_tracking_controller(PDO $pdo, array $config, string $orderToken): void {
    $siteName = $config['site']['name'] ?? 'Gallery';
    $currencyCode = $config['currency'] ?? 'GBP';

    set_cache_headers('no-cache');

    // Verify email first
    $email = trim($_GET['email'] ?? '');
    $verified = false;

    if (!empty($email)) {
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE public_token = ? AND email = ? LIMIT 1');
        $stmt->execute([$orderToken, $email]);
        if ($stmt->fetch()) {
            $verified = true;
        }
    }

    if (!$verified) {
        render(__DIR__ . '/../../views/public/order_verify.php', compact(
            'siteName', 'orderToken'
        ));
        return;
    }

    // Load order
    $stmt = $pdo->prepare('
        SELECT id, email, total_pence, paid_at, status, stripe_checkout_id
        FROM orders
        WHERE public_token = ? AND email = ?
        LIMIT 1
    ');
    $stmt->execute([$orderToken, $email]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        require __DIR__ . '/../../views/public/404.php';
        return;
    }

    // Load items
    $stmt = $pdo->prepare('
        SELECT p.public_token, p.width, p.height, e.title as event_title, oi.quantity, oi.unit_price_pence
        FROM order_items oi
        JOIN photos p ON oi.photo_id = p.id
        JOIN events e ON p.event_id = e.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ');
    $stmt->execute([(int)$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate download link for the order (if paid)
    $downloadLink = '';
    if ($order['status'] === 'paid') {
        $downloadLink = get_or_create_download_link($pdo, (int)$order['id'], $config);
    }

    // Download expiry
    $downloadExpiry = $order['paid_at'] ? date('j M Y', strtotime('+7 days', strtotime($order['paid_at']))) : 'N/A';

    render(__DIR__ . '/../../views/public/order_tracking.php', compact(
        'siteName', 'currencyCode', 'order', 'items', 'orderToken', 'email',
        'downloadLink', 'downloadExpiry'
    ));
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/cart.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/orders.php';
require_once __DIR__ . '/../../lib/stripe.php';
require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../lib/audit.php';

/** Rate-limit key for the checkout bucket — see public_checkout_controller(). */
function checkout_rate_limit_key(string $email, string $ip): string {
    return $email . ':' . $ip;
}

/**
 * POST /checkout {email} — validates cart, creates an order, initiates
 * Stripe Checkout session, and redirects to Stripe Checkout.
 */
function public_checkout_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true);
    $email = trim((string)($input['email'] ?? ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email']);
        return;
    }

    $ip = get_client_ip();

    // Rate limit per email+IP (5 attempts per hour). Keying on the bare
    // email alone would let an attacker who merely knows a victim's email
    // address exhaust their checkout budget from any IP, or let one victim
    // sharing their own email across devices/networks lock themselves out.
    $maxCheckoutAttempts = adjust_rate_limit_for_dev($config, 5);
    if (!check_rate_limit($pdo, 'checkout', checkout_rate_limit_key($email, $ip), 3600, $maxCheckoutAttempts)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many checkout attempts. Try again later.']);
        return;
    }

    $items = cart_get($config);
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty']);
        return;
    }

    // Price everything fresh from DB (including volume discount)
    require_once __DIR__ . '/../../lib/cart.php';
    $priced = cart_price($pdo, $items, $config);
    if (empty($priced['lines']) || $priced['total_pence'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid cart items']);
        return;
    }

    // Create the order
    $order = create_order($pdo, $config, $email, $priced['lines'], $priced['total_pence']);

    audit_log($pdo, 'public', 'checkout_initiated', 'order', $order['id'], [
        'email' => $email,
        'item_count' => count($priced['lines']),
        'total_pence' => $priced['total_pence'],
    ], $ip);

    // Create Stripe session
    try {
        $baseUrl = $config['site']['base_url'] ?? 'https://example.com';
        $successUrl = "{$baseUrl}/checkout/success/{$order['public_token']}";
        $cancelUrl = "{$baseUrl}/cart";

        $sessionId = stripe_create_checkout_session($config, $order['public_token'], $priced['lines'], $successUrl, $cancelUrl);

        update_order_stripe_ids($pdo, $order['id'], $sessionId);

        // Get publishable key for client redirect
        $publishableKey = $config['stripe']['publishable_key'] ?? '';
        if (!$publishableKey) {
            throw new RuntimeException('Stripe publishable key not configured');
        }

        echo json_encode(['ok' => true, 'session_id' => $sessionId, 'publishable_key' => $publishableKey]);
    } catch (Throwable $e) {
        error_log('Checkout failed for order ' . $order['id'] . ': ' . $e->getMessage());
        audit_log($pdo, 'public', 'checkout_failed', 'order', $order['id'], [
            'error' => $e->getMessage(),
        ], $ip);
        http_response_code(500);
        echo json_encode(['error' => 'Checkout failed']);
    }
}

/**
 * GET /checkout/success/{order-token} — landing page after successful Stripe payment.
 * Shows order confirmation and download link (or queues email if still building).
 */
function public_checkout_success_controller(PDO $pdo, array $config, string $orderToken): void {
    $siteName = $config['site']['name'] ?? 'Gallery';

    $order = get_order_by_token($pdo, $orderToken);
    if (!$order) {
        http_response_code(404);
        require __DIR__ . '/../../views/public/404.php';
        return;
    }

    if ($order['status'] !== 'paid') {
        http_response_code(400);
        render(__DIR__ . '/../../views/public/checkout_pending.php', [
            'siteName' => $siteName,
        ]);
        return;
    }

    setcookie('pm_cart', '', ['expires' => time() - 86400, 'path' => '/', 'httponly' => true]);

    $items = get_order_items($pdo, (int)$order['id']);

    $downloadLink = get_or_create_download_link($pdo, (int)$order['id'], $config);

    render(__DIR__ . '/../../views/public/checkout_success.php', [
        'siteName' => $siteName,
        'order' => $order,
        'items' => $items,
        'downloadLink' => $downloadLink,
    ]);
}

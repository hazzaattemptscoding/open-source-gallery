<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/cart.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/orders.php';
require_once __DIR__ . '/../../lib/stripe.php';

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

    $items = cart_get($config);
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty']);
        return;
    }

    // Price everything fresh from DB
    require_once __DIR__ . '/../../lib/cart.php';
    $priced = cart_price($pdo, $items);
    if (empty($priced['lines']) || $priced['total_pence'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid cart items']);
        return;
    }

    // Create the order
    $order = create_order($pdo, $config, $email, $priced['lines'], $priced['total_pence']);

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
        echo 'Order payment not yet confirmed. Please check your email for download links.';
        return;
    }

    $items = get_order_items($pdo, (int)$order['id']);

    $downloadLink = get_or_create_download_link($pdo, (int)$order['id'], $config);

    render(__DIR__ . '/../../views/public/checkout_success.php', [
        'siteName' => $siteName,
        'order' => $order,
        'items' => $items,
        'downloadLink' => $downloadLink,
    ]);
}

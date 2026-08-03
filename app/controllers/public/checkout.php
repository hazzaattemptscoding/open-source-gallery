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

    /*
     * Record the contact and, if they ticked the box, their marketing consent.
     *
     * Consent is only taken from an explicit, affirmative tick. A pre-ticked
     * box or an "unless you object" default is not consent under UK GDPR, so
     * the checkbox in the cart view ships unchecked and this reads it strictly:
     * anything other than a truthy value means no.
     *
     * Soft opt-in is deliberately NOT applied here. At this point the customer
     * has started a checkout, not completed one, so they are not yet an
     * existing customer in the PECR sense. Whether to apply soft opt-in on
     * successful payment is a decision for whoever builds the campaign sends,
     * and it belongs in the webhook that confirms payment, not here.
     *
     * Wrapped so that a consent-table problem can never break a checkout. A
     * failed sale is worse than a missed mailing-list signup.
     */
    try {
        require_once __DIR__ . '/../../lib/consent.php';
        ensure_contact($pdo, $email);

        if (!empty($input['marketing_consent'])) {
            record_consent($pdo, $email, CONSENT_BASIS_EXPLICIT, $ip);
        }
    } catch (Throwable $e) {
        error_log('checkout consent capture failed: ' . $e->getMessage());
    }

    /*
     * Apply advance credit, if a code was supplied.
     *
     * Order of operations matters and is deliberate:
     *
     *  1. The order already exists, so the ledger has a real order_id to point
     *     at and the spend is attributable even if everything after this fails.
     *  2. spend_credit() decides the amount in SQL, atomically. Nothing here
     *     reads a balance and then subtracts it, because two simultaneous
     *     checkouts would both pass that check.
     *  3. Whatever it actually took is what gets deducted from the charge. Not
     *     what was requested, and not what the balance looked like a moment
     *     ago: only the value the database confirms it removed.
     *
     * orders.total_pence keeps its meaning as the gross order value, so
     * existing reports and receipts stay correct. Stripe is asked for
     * total_pence minus credit_applied_pence.
     */
    $creditApplied = 0;
    $creditCode = strtolower(trim((string) ($input['credit_code'] ?? '')));

    if ($creditCode !== '') {
        require_once __DIR__ . '/../../lib/credit.php';
        $credit = find_spendable_credit($pdo, $creditCode);

        if ($credit === null) {
            http_response_code(400);
            echo json_encode(['error' => 'That credit code is not valid or has been used up']);
            return;
        }

        // A credit tied to one event cannot be spent on another. Every line in
        // this cart must belong to the event the credit names.
        if (!credit_cart_matches_event($pdo, $priced['lines'], $credit)) {
            http_response_code(400);
            echo json_encode(['error' => 'That credit can only be used on photos from the event it was bought for']);
            return;
        }

        $creditApplied = spend_credit($pdo, (int) $credit['id'], (int) $order['id'], (int) $priced['total_pence']);

        if ($creditApplied > 0) {
            $pdo->prepare('UPDATE orders SET credit_applied_pence = ?, credit_id = ? WHERE id = ?')
                ->execute([$creditApplied, (int) $credit['id'], (int) $order['id']]);

            audit_log($pdo, 'public', 'checkout_credit_applied', 'order', (int) $order['id'], [
                'credit_id' => (int) $credit['id'],
                'amount_pence' => $creditApplied,
            ], $ip);
        }
    }

    $amountToCharge = max(0, (int) $priced['total_pence'] - $creditApplied);

    /*
     * Fully covered by credit: there is nothing to charge, and Stripe cannot
     * create a zero-value Checkout session, so this must not go near Stripe.
     * The money was collected when the credit was bought, so the order is
     * already paid and is marked as such here rather than waiting for a webhook
     * that will never arrive.
     */
    if ($amountToCharge === 0) {
        mark_order_paid($pdo, (int) $order['id']);

        audit_log($pdo, 'public', 'checkout_paid_by_credit', 'order', (int) $order['id'], [
            'credit_applied_pence' => $creditApplied,
        ], $ip);

        // The cart cookie is cleared by the success page, which every other
        // completed checkout also relies on, so it is not duplicated here.
        echo json_encode([
            'ok' => true,
            'paid_by_credit' => true,
            'redirect' => '/checkout/success/' . $order['public_token'],
        ]);
        return;
    }

    // Create Stripe session
    try {
        $baseUrl = $config['site']['base_url'] ?? 'https://example.com';
        $successUrl = "{$baseUrl}/checkout/success/{$order['public_token']}";
        $cancelUrl = "{$baseUrl}/cart";

        /*
         * With credit applied, Stripe is sent one summarised line for the net
         * amount rather than the itemised cart. Stripe has no concept of a
         * negative line item, and the full itemisation already lives in
         * order_items and on our own receipt, so collapsing it is both simpler
         * and clearer at the payment step than a synthetic discount line.
         */
        $stripeLines = $creditApplied > 0
            ? [[
                'description' => count($priced['lines']) . ' item(s), less '
                    . format_pence($creditApplied, $config['currency']['code'] ?? 'GBP') . ' credit',
                'unit_price_pence' => $amountToCharge,
              ]]
            : $priced['lines'];

        $sessionId = stripe_create_checkout_session($config, $order['public_token'], $stripeLines, $successUrl, $cancelUrl);

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

        /*
         * Give the credit back.
         *
         * It was spent above, before Stripe was asked for a session, because
         * the spend has to settle atomically before anything decides what to
         * charge. If that session could not be created, the customer is left
         * with a lower balance and no photos: their money has vanished into an
         * order they were never able to pay for.
         *
         * release_credit() refuses to act on an order that did reach 'paid', so
         * this cannot claw back credit that was legitimately spent.
         */
        if ($creditApplied > 0 && isset($credit)) {
            release_credit($pdo, (int) $credit['id'], (int) $order['id']);
        }

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

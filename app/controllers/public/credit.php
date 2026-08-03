<?php
/**
 * Buying advance credit, and checking a code's balance.
 *
 * Redemption does not live here: credit is spent during checkout, so that code
 * is in app/controllers/public/checkout.php next to the order it discounts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/credit.php';
require_once __DIR__ . '/../../lib/stripe.php';
require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../lib/currency.php';
require_once __DIR__ . '/../../lib/consent.php';

/** GET /credit — the page explaining and selling advance credit. */
function public_credit_page_controller(PDO $pdo, array $config): void
{
    $currency = config_currency_code($config);

    // Offered amounts come from config so a photographer can price their own
    // ladder. Falls back to a sensible spread rather than hardcoding a price
    // anywhere in the code.
    $amounts = $config['credit']['amounts_pence'] ?? [1000, 2000, 5000];

    $options = [];
    foreach ($amounts as $pence) {
        $pence = (int) $pence;
        if ($pence < CREDIT_MIN_PENCE || $pence > CREDIT_MAX_PENCE) {
            continue;
        }
        $options[] = ['pence' => $pence, 'formatted' => format_pence($pence, $currency)];
    }

    render(__DIR__ . '/../../views/public/credit.php', [
        'pageTitle' => 'Buy photo credit',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'options' => $options,
        'currencyCode' => $currency,
    ]);
}

/**
 * POST /credit/buy {email, amount_pence}
 *
 * Creates a pending credit and a Stripe session for it. The credit is not
 * spendable until the webhook confirms payment; see activate_credit().
 */
function public_credit_buy_controller(PDO $pdo, array $config): void
{
    header('Content-Type: application/json');

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Malformed request']);
        return;
    }

    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $amount = (int) ($input['amount_pence'] ?? 0);
    $ip = get_client_ip();

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Enter a valid email address']);
        return;
    }

    /*
     * The amount is checked against the configured ladder, not just against the
     * min/max. Otherwise anyone could post an arbitrary amount and buy credit
     * at a value the photographer never offered, which is a pricing bypass
     * rather than a validation nicety.
     */
    $allowed = array_map('intval', $config['credit']['amounts_pence'] ?? [1000, 2000, 5000]);
    if (!in_array($amount, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Choose one of the available amounts']);
        return;
    }

    $maxAttempts = adjust_rate_limit_for_dev($config, 5);
    if (!check_rate_limit($pdo, 'credit_buy', $email . ':' . $ip, 3600, $maxAttempts)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many attempts. Try again later.']);
        return;
    }

    $currency = config_currency_code($config);
    $credit = create_pending_credit($pdo, $email, $amount, $currency, null);

    if ($credit === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not start that purchase']);
        return;
    }

    audit_log($pdo, 'public', 'credit_purchase_started', 'credit', $credit['id'], [
        'amount_pence' => $amount,
    ], $ip);

    try {
        $baseUrl = rtrim(site_base_url($config) ?? '', '/');

        $sessionId = stripe_create_checkout_session(
            $config,
            'credit-' . $credit['code'],
            [[
                'description' => format_pence($amount, $currency) . ' photo credit',
                'unit_price_pence' => $amount,
            ]],
            $baseUrl . '/credit/success/' . $credit['code'],
            $baseUrl . '/credit'
        );

        attach_credit_checkout($pdo, $credit['id'], $sessionId);

        $publishableKey = $config['stripe']['publishable_key'] ?? '';
        if ($publishableKey === '') {
            throw new RuntimeException('Stripe publishable key not configured');
        }

        echo json_encode(['ok' => true, 'session_id' => $sessionId, 'publishable_key' => $publishableKey]);
    } catch (Throwable $e) {
        error_log('credit purchase failed for credit ' . $credit['id'] . ': ' . $e->getMessage());
        audit_log($pdo, 'public', 'credit_purchase_failed', 'credit', $credit['id'], [
            'error' => $e->getMessage(),
        ], $ip);
        http_response_code(500);
        echo json_encode(['error' => 'Could not start that purchase']);
    }
}

/**
 * GET /credit/success/{code}
 *
 * Where Stripe returns the buyer. Shows the code and its balance.
 *
 * The balance may still read as pending here: Stripe redirects the browser and
 * delivers the webhook independently, and the webhook is what activates the
 * credit. The page says so rather than pretending, because a customer who has
 * just paid and is told their credit is worth nothing will email the
 * photographer within the minute.
 */
function public_credit_success_controller(PDO $pdo, array $config, string $code): void
{
    $stmt = $pdo->prepare('SELECT * FROM credits WHERE code = ? LIMIT 1');
    $stmt->execute([strtolower($code)]);
    $credit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($credit === false) {
        http_response_code(404);
        render(__DIR__ . '/../../views/errors/404.php', []);
        return;
    }

    render(__DIR__ . '/../../views/public/credit_success.php', [
        'pageTitle' => 'Your photo credit',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'credit' => $credit,
        'currencyCode' => $credit['currency'] ?? (config_currency_code($config)),
    ]);
}

/**
 * POST /credit/check {code}
 *
 * Balance lookup for the cart, so someone can see what a code is worth before
 * committing to a checkout.
 *
 * Rate limited hard and per IP. A credit code is a bearer instrument, so an
 * unlimited lookup endpoint is an offer to brute-force one. 16 hex characters
 * is far too large a space to search, but the limit costs nothing and removes
 * the question.
 */
function public_credit_check_controller(PDO $pdo, array $config): void
{
    header('Content-Type: application/json');

    $input = json_decode((string) file_get_contents('php://input'), true);
    $code = strtolower(trim((string) ($input['code'] ?? '')));

    $maxChecks = adjust_rate_limit_for_dev($config, 30);
    if (!check_rate_limit($pdo, 'credit_check', get_client_ip(), 3600, $maxChecks)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many attempts. Try again shortly.']);
        return;
    }

    // Checked in the site's currency, the same one checkout will spend it in.
    // A balance shown here that checkout then refuses would be worse than the
    // uniform "not valid" message, because the customer would have been told
    // the money was there.
    $credit = find_spendable_credit($pdo, $code, config_currency_code($config));

    if ($credit === null) {
        // Deliberately one message for every failure mode. Telling an anonymous
        // caller the difference between "no such code" and "already spent"
        // helps someone probing for codes more than it helps a customer.
        http_response_code(404);
        echo json_encode(['error' => 'That code is not valid or has been used up']);
        return;
    }

    $currency = (string) $credit['currency'];

    echo json_encode([
        'ok' => true,
        'balance_pence' => (int) $credit['balance_pence'],
        'balance_formatted' => format_pence((int) $credit['balance_pence'], $currency),
        'event_id' => $credit['event_id'] !== null ? (int) $credit['event_id'] : null,
    ]);
}

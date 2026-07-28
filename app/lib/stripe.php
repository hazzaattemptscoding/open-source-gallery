<?php
declare(strict_types=1);

/**
 * Minimal Stripe API wrapper for checkout session creation and webhook
 * validation. Uses only curl (no Stripe SDK).
 */

function stripe_create_checkout_session(array $config, string $orderToken, array $lineItems, string $successUrl, string $cancelUrl): string {
    $secretKey = $config['stripe']['secret_key'] ?? '';
    if ($secretKey === '') {
        throw new RuntimeException('Stripe secret key not configured');
    }

    $items = [];
    foreach ($lineItems as $line) {
        $items[] = [
            'price_data' => [
                'currency' => strtolower($config['currency'] ?? 'gbp'),
                'unit_amount' => $line['unit_price_pence'],
                'product_data' => [
                    'name' => $line['description'],
                ],
            ],
            'quantity' => 1,
        ];
    }

    $data = [
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'line_items' => $items,
        'client_reference_id' => $orderToken,
    ];

    $response = stripe_request('POST', 'https://api.stripe.com/v1/checkout/sessions', $secretKey, $data);

    if (!isset($response['id'])) {
        throw new RuntimeException('Failed to create Stripe checkout session');
    }

    return $response['id'];
}

function stripe_verify_webhook_signature(string $payload, string $signature, string $webhookSecret): bool {
    if (!$webhookSecret) {
        return false;
    }

    [$timestamp, $sigHash] = explode(',', str_replace('t=', '', str_replace('v1=', '', $signature)), 2) + ['', ''];

    $signedContent = "$timestamp.$payload";
    $expectedHash = hash_hmac('sha256', $signedContent, $webhookSecret);

    return hash_equals($expectedHash, $sigHash);
}

function stripe_request(string $method, string $url, string $secretKey, array $data): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$secretKey:");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300 || !$response) {
        throw new RuntimeException("Stripe API error: HTTP $httpCode");
    }

    return json_decode($response, true) ?? [];
}

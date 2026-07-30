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

    $parts = [];
    $timestamp = null;
    $v1Hashes = [];

    foreach (explode(',', $signature) as $part) {
        $part = trim($part);
        if (strpos($part, 't=') === 0) {
            $timestamp = substr($part, 2);
        } elseif (strpos($part, 'v1=') === 0) {
            $v1Hashes[] = substr($part, 3);
        }
    }

    if (!$timestamp || empty($v1Hashes)) {
        return false;
    }

    $currentTime = time();
    $timestampTime = (int)$timestamp;
    $timeTolerance = 300;

    if ($currentTime - $timestampTime > $timeTolerance) {
        return false;
    }

    $signedContent = "$timestamp.$payload";
    $expectedHash = hash_hmac('sha256', $signedContent, $webhookSecret);

    foreach ($v1Hashes as $sigHash) {
        if (hash_equals($expectedHash, $sigHash)) {
            return true;
        }
    }

    return false;
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
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException("Stripe API connection error: $curlError");
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $errorMessage = "HTTP $httpCode";
        if ($response) {
            $decoded = json_decode($response, true);
            if (isset($decoded['error']['message'])) {
                $errorMessage .= " - " . $decoded['error']['message'];
            }
        }
        throw new RuntimeException("Stripe API error: $errorMessage");
    }

    if (!$response) {
        throw new RuntimeException("Stripe API error: Empty response");
    }

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new RuntimeException("Stripe API error: Invalid JSON response: $response");
    }

    return $decoded;
}

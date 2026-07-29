<?php
declare(strict_types=1);

require_once __DIR__ . '/signer.php';

/**
 * Order lifecycle: created as 'pending' when the customer initiates checkout.
 * Stripe's checkout.session.completed webhook updates it to 'paid' and queues
 * email + zip-building jobs. If the customer abandons the flow, the order stays
 * 'pending' forever (no cleanup needed; they expire via the links they'd need
 * to access files). On refund, Stripe webhook sets status to 'refunded' or
 * 'partial_refund' and queues a refund confirmation email.
 */

function create_order(PDO $pdo, array $config, string $email, array $lines, int $totalPence): array {
    $token = bin2hex(random_bytes(11));
    $currencyCode = $config['currency'] ?? 'GBP';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO orders (public_token, email, currency, total_pence, status)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$token, $email, $currencyCode, $totalPence, 'pending']);
        $orderId = (int)$pdo->lastInsertId();

        foreach ($lines as $line) {
            $stmt = $pdo->prepare('
                INSERT INTO order_items (order_id, item_type, photo_id, session_id, event_id, description, unit_price_pence, line_total_pence)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $orderId,
                $line['type'],
                $line['type'] === 'photo' ? $line['id'] : null,
                $line['type'] === 'session_bundle' ? $line['id'] : null,
                $line['type'] === 'event_bundle' ? $line['id'] : null,
                $line['description'],
                $line['unit_price_pence'],
                $line['unit_price_pence'],
            ]);
        }

        $pdo->commit();

        return [
            'id' => $orderId,
            'public_token' => $token,
            'email' => $email,
            'currency' => $currencyCode,
            'total_pence' => $totalPence,
            'status' => 'pending',
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_order_by_token(PDO $pdo, string $token): ?array {
    $stmt = $pdo->prepare('SELECT id, public_token, email, status, currency, total_pence, stripe_checkout_id, paid_at FROM orders WHERE public_token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_order_items(PDO $pdo, int $orderId): array {
    $stmt = $pdo->prepare('SELECT id, item_type, photo_id, session_id, event_id, description, unit_price_pence, line_total_pence FROM order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_order_stripe_ids(PDO $pdo, int $orderId, string $checkoutId, ?string $paymentIntentId = null): void {
    $stmt = $pdo->prepare('UPDATE orders SET stripe_checkout_id = ?, stripe_payment_intent = ? WHERE id = ?');
    $stmt->execute([$checkoutId, $paymentIntentId, $orderId]);
}

function mark_order_paid(PDO $pdo, int $orderId): void {
    $stmt = $pdo->prepare('UPDATE orders SET status = ?, paid_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute(['paid', $orderId]);

    // Queue email and zip-building jobs
    $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        queue_job($pdo, 'email', ['order_id' => $orderId, 'type' => 'receipt']);
        queue_job($pdo, 'zip_build', ['order_id' => $orderId]);
    }
}

function get_order_by_checkout_id(PDO $pdo, string $checkoutId): ?array {
    $stmt = $pdo->prepare('SELECT id, public_token, status FROM orders WHERE stripe_checkout_id = ?');
    $stmt->execute([$checkoutId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function queue_job(PDO $pdo, string $type, array $payload): void {
    $stmt = $pdo->prepare('
        INSERT INTO jobs (type, payload, status, run_after)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ');
    $stmt->execute([$type, json_encode($payload), 'pending']);
}

function create_download_link(PDO $pdo, int $orderId, array $config, int $expiryDays = 30): string {
    // Get item count and download cap from settings
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $itemCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $stmt->execute(['download_cap_multiplier']);
    $multiplier = (int)($stmt->fetchColumn() ?: 5);

    $maxDownloads = max(1, $itemCount * $multiplier);
    $expiryDate = date('Y-m-d H:i:s', time() + ($expiryDays * 86400));

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken, true);
    $encodedHash = strtr(base64_encode($tokenHash), '+/', '-_');

    $stmt = $pdo->prepare('
        INSERT INTO download_links (order_id, token_hash, expires_at, max_downloads)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$orderId, $encodedHash, $expiryDate, $maxDownloads]);

    return "{$config['site']['base_url']}/download/{$rawToken}";
}

function get_or_create_download_link(PDO $pdo, int $orderId, array $config): string {
    return create_download_link($pdo, $orderId, $config);
}

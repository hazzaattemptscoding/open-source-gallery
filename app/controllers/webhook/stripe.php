<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/stripe.php';
require_once __DIR__ . '/../../lib/orders.php';
require_once __DIR__ . '/../../lib/audit.php';

/**
 * POST /webhook/stripe — Stripe webhook endpoint for payment status updates.
 * Validates signature (with idempotency on event ID), marks order as paid,
 * and queues email/archive-building jobs.
 *
 * Stripe retries with exponential backoff; we must return 2xx within 300s
 * or it's logged as failed delivery and retried later.
 */
function webhook_stripe_controller(PDO $pdo, array $config): void {
    $payload = (string)file_get_contents('php://input');
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    header('Content-Type: application/json');

    $webhookSecret = $config['stripe']['webhook_secret'] ?? '';
    if (!$webhookSecret || !stripe_verify_webhook_signature($payload, $signature, $webhookSecret)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        return;
    }

    $event = json_decode($payload, true);
    if (!isset($event['id'], $event['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid event']);
        return;
    }

    $eventId = (string)$event['id'];

    // Idempotency: if we've already seen this event, don't reprocess it
    $stmt = $pdo->prepare('SELECT 1 FROM webhook_events WHERE id = ?');
    $stmt->execute([$eventId]);
    if ($stmt->fetchColumn()) {
        http_response_code(200);
        echo json_encode(['ok' => true]);
        return;
    }

    try {
        if ($event['type'] === 'checkout.session.completed') {
            handle_checkout_completed($pdo, $event['data']['object'] ?? []);
        } elseif ($event['type'] === 'charge.refunded') {
            handle_charge_refunded($pdo, $event['data']['object'] ?? []);
        }

        // Mark event as processed
        $stmt = $pdo->prepare('INSERT INTO webhook_events (id, type) VALUES (?, ?)');
        $stmt->execute([$eventId, $event['type']]);

        http_response_code(200);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        audit_log($pdo, 'system', 'webhook_error', null, null, ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['error' => 'Processing failed']);
    }
}

function handle_checkout_completed(PDO $pdo, array $session): void {
    $checkoutId = (string)($session['id'] ?? '');
    $paymentStatus = (string)($session['payment_status'] ?? '');

    if (!$checkoutId || $paymentStatus !== 'paid') {
        return;
    }

    $order = get_order_by_checkout_id($pdo, $checkoutId);
    if (!$order) {
        return;
    }

    if ($order['status'] === 'paid') {
        return;
    }

    $orderId = (int)$order['id'];
    mark_order_paid($pdo, $orderId);
    audit_log($pdo, 'system', 'webhook_payment_confirmed', 'order', $orderId, [
        'checkout_id' => $checkoutId,
    ]);

    // Queue order confirmation email
    queue_job($pdo, 'email', [
        'order_id' => $orderId,
        'type' => 'order_confirmation',
    ]);
}

function handle_charge_refunded(PDO $pdo, array $charge): void {
    $paymentIntentId = (string)($charge['payment_intent'] ?? '');
    if (!$paymentIntentId) {
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM orders WHERE stripe_payment_intent = ?');
    $stmt->execute([$paymentIntentId]);
    $orderId = $stmt->fetchColumn();

    if (!$orderId) {
        return;
    }

    $isFullyRefunded = (bool)($charge['refunded'] ?? false);
    $refundType = $isFullyRefunded ? 'full' : 'partial';
    $status = $isFullyRefunded ? 'refunded' : 'partial_refund';

    $stmt = $pdo->prepare('UPDATE orders SET status = ?, refunded_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$status, $orderId]);

    audit_log($pdo, 'system', 'webhook_refund_processed', 'order', (int)$orderId, [
        'refund_type' => $refundType,
        'payment_intent' => $paymentIntentId,
    ]);

    // Queue refund confirmation email
    queue_job($pdo, 'email', ['order_id' => (int)$orderId, 'type' => 'refund', 'refund_type' => $refundType]);
}

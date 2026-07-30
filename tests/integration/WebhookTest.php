<?php
/**
 * Covers app/controllers/webhook/stripe.php's success-path audit logging.
 * audit_log() was only ever called from the webhook's error catch — a
 * successful checkout.session.completed or charge.refunded, arguably the
 * most sensitive state transition the app has (money changed hands), left
 * no trace in the audit trail at all. Only failures were visible.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/controllers/webhook/stripe.php';

final class WebhookTest extends TestCase {
    private function createPendingOrderWithCheckoutId(string $checkoutId): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (public_token, email, currency, total_pence, status, stripe_checkout_id)
             VALUES (?, 'buyer@example.com', 'GBP', 2000, 'pending', ?)"
        );
        $stmt->execute([bin2hex(random_bytes(11)), $checkoutId]);
        return (int)$this->pdo->lastInsertId();
    }

    private function createPaidOrderWithPaymentIntent(string $paymentIntentId): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (public_token, email, currency, total_pence, status, stripe_payment_intent, paid_at)
             VALUES (?, 'buyer@example.com', 'GBP', 2000, 'paid', ?, NOW())"
        );
        $stmt->execute([bin2hex(random_bytes(11)), $paymentIntentId]);
        return (int)$this->pdo->lastInsertId();
    }

    private function auditCountFor(string $action, int $entityId): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM audit_log WHERE action = ? AND entity_type = 'order' AND entity_id = ?"
        );
        $stmt->execute([$action, $entityId]);
        return (int)$stmt->fetchColumn();
    }

    public function testCheckoutCompletedLogsAuditEntryOnSuccess(): void {
        $checkoutId = 'cs_test_' . bin2hex(random_bytes(8));
        $orderId = $this->createPendingOrderWithCheckoutId($checkoutId);

        handle_checkout_completed($this->pdo, ['id' => $checkoutId, 'payment_status' => 'paid']);

        $this->assertGreaterThan(0, $this->auditCountFor('webhook_payment_confirmed', $orderId));
    }

    public function testChargeRefundedLogsAuditEntryOnSuccess(): void {
        $paymentIntentId = 'pi_test_' . bin2hex(random_bytes(8));
        $orderId = $this->createPaidOrderWithPaymentIntent($paymentIntentId);

        handle_charge_refunded($this->pdo, ['payment_intent' => $paymentIntentId, 'refunded' => true]);

        $this->assertGreaterThan(0, $this->auditCountFor('webhook_refund_processed', $orderId));
    }

    public function testCheckoutCompletedIsIdempotentAndDoesNotDoubleLog(): void {
        $checkoutId = 'cs_test_' . bin2hex(random_bytes(8));
        $orderId = $this->createPendingOrderWithCheckoutId($checkoutId);

        handle_checkout_completed($this->pdo, ['id' => $checkoutId, 'payment_status' => 'paid']);
        // Simulates Stripe redelivering the same event after the order is
        // already paid (webhook_events' own idempotency guard lives in the
        // controller, not in this handler, but the handler must still be
        // safe to call twice without logging twice).
        handle_checkout_completed($this->pdo, ['id' => $checkoutId, 'payment_status' => 'paid']);

        $this->assertSame(1, $this->auditCountFor('webhook_payment_confirmed', $orderId));
    }
}

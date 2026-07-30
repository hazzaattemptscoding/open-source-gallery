<?php
/**
 * Covers app/lib/mailer.php. Before this file existed, process_email_job()
 * (app/lib/cron.php) called send_receipt_email() and send_refund_email() —
 * neither was defined anywhere in the codebase. Every queued receipt/refund
 * email threw "Error: Call to undefined function", retried three times, and
 * landed in the failed-jobs table. No customer has ever received an
 * automated receipt or refund email through this path.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/mailer.php';
require_once __DIR__ . '/../../app/lib/orders.php';

final class EmailDeliveryTest extends TestCase {
    private function createPaidOrderWithItems(): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (public_token, email, currency, total_pence, status, download_url)
             VALUES (?, 'buyer@example.com', 'GBP', 4500, 'paid', 'https://gallery.test/download/abc123')"
        );
        $stmt->execute([bin2hex(random_bytes(11))]);
        $orderId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, item_type, description, unit_price_pence, line_total_pence)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, 'photo', 'Photo #42', 4500, 4500]);

        return $orderId;
    }

    private function baseConfig(): array {
        return [
            'site' => ['name' => 'Test Gallery', 'support_email' => 'help@test-gallery.example'],
            'smtp' => ['host' => '', 'port' => 587, 'user' => '', 'pass' => '', 'from_email' => '', 'from_name' => ''],
        ];
    }

    public function testSmtpIsAvailableFalseWhenHostEmpty(): void {
        $this->assertFalse(smtp_is_available(['host' => '']));
    }

    public function testSmtpIsAvailableFalseWhenHostSetButPhpMailerNotInstalled(): void {
        // This environment's vendor/ has no PHPMailer (it's a suggest-only,
        // optionally-vendored dependency — see composer.json). A host that
        // fills in SMTP settings without installing the package must fall
        // back to mail(), not fatal.
        $this->assertFalse(smtp_is_available(['host' => 'smtp.example.com']));
    }

    public function testRenderReceiptEmailIncludesOrderDataAndDownloadLink(): void {
        $orderId = $this->createPaidOrderWithItems();

        $rendered = render_receipt_email($this->pdo, $this->baseConfig(), $orderId);

        $this->assertNotNull($rendered);
        $this->assertSame('buyer@example.com', $rendered['recipient']);
        $this->assertStringContainsString('Test Gallery', $rendered['subject']);
        $this->assertStringContainsString('Photo #42', $rendered['html']);
        $this->assertStringContainsString('£45.00', $rendered['html']);
        $this->assertStringContainsString('https://gallery.test/download/abc123', $rendered['html']);
        $this->assertStringContainsString('help@test-gallery.example', $rendered['html']);
    }

    public function testRenderReceiptEmailReturnsNullForUnknownOrder(): void {
        $this->assertNull(render_receipt_email($this->pdo, $this->baseConfig(), 999999));
    }

    public function testRenderRefundEmailIncludesOrderNumberAndRefundWord(): void {
        $orderId = $this->createPaidOrderWithItems();
        $stmt = $this->pdo->prepare('SELECT public_token FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $publicToken = $stmt->fetchColumn();

        $rendered = render_refund_email($this->pdo, $this->baseConfig(), $orderId, 'full');

        $this->assertNotNull($rendered);
        $this->assertStringContainsString($publicToken, $rendered['html']);
        $this->assertStringContainsString('refund', $rendered['html']);
    }

    public function testRenderRefundEmailUsesPartialRefundWording(): void {
        $orderId = $this->createPaidOrderWithItems();

        $rendered = render_refund_email($this->pdo, $this->baseConfig(), $orderId, 'partial');

        $this->assertStringContainsString('partial refund', $rendered['html']);
    }
}

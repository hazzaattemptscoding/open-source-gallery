<?php
/**
 * Covers checkout's rate-limit key (app/controllers/public/checkout.php).
 * It rate-limited on a bare email with no IP component at all — worse than
 * docs/archive/AUDIT.md's original L3 finding (which flagged an "{email}:{ip}" key as
 * inconsistent with other buckets, not missing entirely). An attacker
 * sharing a victim's email in the checkout form from many IPs would share
 * one 5-attempts-per-hour budget with the real customer; conversely one
 * bad actor could exhaust a bucket keyed purely on an email they don't
 * control, locking out the real customer from checking out at all.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/rate_limit.php';
require_once __DIR__ . '/../../app/controllers/public/checkout.php';

final class CheckoutTest extends TestCase {
    public function testCheckoutRateLimitKeyDiffersByIp(): void {
        $keyA = checkout_rate_limit_key('shared@example.com', '203.0.113.1');
        $keyB = checkout_rate_limit_key('shared@example.com', '198.51.100.7');

        $this->assertNotSame($keyA, $keyB, 'the same email from different IPs must not share a rate-limit key');
    }

    public function testCheckoutRateLimitDoesNotLockOutOtherIpsSharingAnEmail(): void {
        $email = 'shared@example.com';

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(check_rate_limit($this->pdo, 'checkout', checkout_rate_limit_key($email, '203.0.113.1'), 3600, 5));
        }
        $this->assertFalse(
            check_rate_limit($this->pdo, 'checkout', checkout_rate_limit_key($email, '203.0.113.1'), 3600, 5),
            'IP A should be rate-limited after 5 attempts'
        );

        // Same email, different IP: must have its own budget. This is the
        // exact scenario that fails when the key is the bare email with
        // no IP component at all.
        $this->assertTrue(
            check_rate_limit($this->pdo, 'checkout', checkout_rate_limit_key($email, '198.51.100.7'), 3600, 5),
            'a different IP with the same email must have its own budget'
        );
    }
}

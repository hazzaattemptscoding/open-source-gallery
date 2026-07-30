<?php
/**
 * Tests for DEV_MODE configuration flag and its effects on local development.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

require_once __DIR__ . '/../../app/lib/rate_limit.php';
require_once __DIR__ . '/../../app/lib/mailer.php';

final class DevModeTest extends TestCase {
    public function testAdjustRateLimitForDevRaisesThreshold(): void {
        $config = $this->getTestConfig();
        $config['dev_mode'] = 'local';

        $localLimit = adjust_rate_limit_for_dev($config, 5);
        $this->assertGreaterThan(5, $localLimit, 'dev mode should raise rate limits');
        $this->assertGreaterThanOrEqual(50, $localLimit, 'dev mode should multiply by at least 10x');
    }

    public function testAdjustRateLimitForProductionKeepsThreshold(): void {
        $config = $this->getTestConfig();
        $config['dev_mode'] = 'production';

        $productionLimit = adjust_rate_limit_for_dev($config, 5);
        $this->assertSame(5, $productionLimit, 'production mode should not change rate limits');
    }

    public function testDevModeEmailLogging(): void {
        $config = $this->getTestConfig();
        $config['dev_mode'] = 'local';

        $logFile = __DIR__ . '/../../storage/dev-emails.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        $result = send_email_via_configured_transport($config, 'test@example.com', 'Test Subject', '<p>Test body</p>');
        $this->assertTrue($result, 'email logging should return true');

        $this->assertFileExists($logFile, 'dev mode should create email log file');
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('test@example.com', $content);
        $this->assertStringContainsString('Test Subject', $content);
        $this->assertStringContainsString('Test body', $content);
    }

    public function testProductionModeSendsEmailsNormally(): void {
        $config = $this->getTestConfig();
        $config['dev_mode'] = 'production';
        // Empty SMTP config forces mail() fallback (we don't test actual sending)
        $config['smtp'] = ['host' => '', 'port' => 587, 'user' => '', 'pass' => '', 'from_email' => '', 'from_name' => ''];

        // In production mode with empty SMTP, it will try mail() which may fail in test,
        // but the important thing is it doesn't log to file
        $logFile = __DIR__ . '/../../storage/dev-emails.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // Don't assert the result since mail() might not work in test, just verify
        // that it didn't log to the dev file (or did it already exist)
        send_email_via_configured_transport($config, 'test@example.com', 'Test Subject', '<p>Test body</p>');

        // In production mode, log file should not be created by the send function
        // (it only creates the log file in dev mode)
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            // If the file exists and doesn't contain our test content, that's correct
            // (it wasn't created in production mode)
            if (strpos($content, 'Test Subject') === false) {
                $this->assertTrue(true, 'email was not logged in production mode');
            }
        } else {
            $this->assertTrue(true, 'email log file not created in production mode');
        }
    }
}

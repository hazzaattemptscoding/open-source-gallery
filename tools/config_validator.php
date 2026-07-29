<?php
/**
 * Configuration validator for the gallery.
 *
 * Tests connectivity and validity of key configuration settings:
 * - SMTP email credentials
 * - Stripe API keys
 * - Remote NAS poller (if enabled)
 *
 * Usage: php tools/config_validator.php
 * This script connects to the database and tests each configuration option.
 */

// Load database connection from config
require_once __DIR__ . '/../config/config.php';

if (!function_exists('get_pdo')) {
    die("Database connection not available.\n");
}

$pdo = get_pdo();

echo "\n";
echo "====================================\n";
echo "  Gallery Configuration Validator\n";
echo "====================================\n\n";

$passed = 0;
$failed = 0;
$skipped = 0;

// Helper function for test output
function test_result($name, $status, $message = '') {
    global $passed, $failed, $skipped;

    $symbols = [
        'pass' => '✓',
        'fail' => '✗',
        'skip' => '⊘',
    ];

    $symbol = $symbols[$status] ?? '?';
    $color = [
        'pass' => "\033[32m",  // green
        'fail' => "\033[31m",  // red
        'skip' => "\033[33m",  // yellow
        'reset' => "\033[0m",
    ];

    echo sprintf(
        "%s %s %s%s%s",
        $color[$status] . $symbol . $color['reset'],
        str_pad($name, 30),
        $message ? '  (' . $message . ')' : '',
        $message ? '' : '',
        "\n"
    );

    if ($status === 'pass') {
        $passed++;
    } elseif ($status === 'fail') {
        $failed++;
    } else {
        $skipped++;
    }
}

// Test 1: Database connection
echo "Database:\n";
try {
    $result = $pdo->query("SELECT 1");
    test_result('Database connection', 'pass');
} catch (Exception $e) {
    test_result('Database connection', 'fail', $e->getMessage());
}

echo "\n";

// Test 2: SMTP configuration
echo "Email (SMTP):\n";

try {
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE key IN ('smtp.from_email', 'smtp.host', 'smtp.port', 'smtp.user', 'smtp.pass')");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }

    // Check if email is configured
    if (empty($settings['smtp.from_email'] ?? '')) {
        test_result('SMTP configuration', 'skip', 'not configured (use local mode)');
    } else {
        $host = $settings['smtp.host'] ?? '';
        $port = (int)($settings['smtp.port'] ?? 587);
        $user = $settings['smtp.user'] ?? '';

        if (!$host || !$user) {
            test_result('SMTP configuration', 'fail', 'missing host or username');
        } else {
            // Try to connect to SMTP server
            $conn = @fsockopen($host, $port, $errno, $errstr, 5);
            if ($conn) {
                fclose($conn);
                test_result('SMTP connection', 'pass', "{$host}:{$port}");
            } else {
                test_result('SMTP connection', 'fail', "{$host}:{$port} - {$errstr}");
            }
        }
    }
} catch (Exception $e) {
    test_result('SMTP configuration', 'fail', $e->getMessage());
}

echo "\n";

// Test 3: Stripe configuration
echo "Stripe:\n";

try {
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE key IN ('stripe.publishable_key', 'stripe.secret_key')");
    $stmt->execute();
    $stripe = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stripe[$row['key']] = $row['value'];
    }

    if (empty($stripe['stripe.publishable_key'] ?? '') || empty($stripe['stripe.secret_key'] ?? '')) {
        test_result('Stripe keys', 'skip', 'not configured (payments disabled)');
    } else {
        $pubKey = $stripe['stripe.publishable_key'];
        $secKey = $stripe['stripe.secret_key'];

        // Validate key formats
        if (!str_starts_with($pubKey, 'pk_')) {
            test_result('Publishable key format', 'fail', 'must start with pk_');
        } else {
            test_result('Publishable key format', 'pass', 'starts with pk_');
        }

        if (!str_starts_with($secKey, 'sk_')) {
            test_result('Secret key format', 'fail', 'must start with sk_');
        } else {
            test_result('Secret key format', 'pass', 'starts with sk_');
        }

        // Try a simple API call to validate keys
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/charges');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $secKey . ':');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            test_result('Stripe API connectivity', 'fail', $error);
        } elseif ($httpCode === 401) {
            test_result('Stripe API key validity', 'fail', 'unauthorized (invalid key)');
        } elseif ($httpCode === 200) {
            test_result('Stripe API key validity', 'pass', 'keys are valid');
        } else {
            test_result('Stripe API connectivity', 'fail', "HTTP {$httpCode}");
        }
    }
} catch (Exception $e) {
    test_result('Stripe configuration', 'fail', $e->getMessage());
}

echo "\n";

// Test 4: Remote NAS configuration
echo "Remote NAS Fulfillment:\n";

try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'storage_mode'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $storageMode = $result['value'] ?? 'local';

    if ($storageMode !== 'remote-nas') {
        test_result('Remote NAS', 'skip', 'not enabled (using local storage)');
    } else {
        // Check if poller token is set
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'fulfillment.poller_token'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $token = $result['value'] ?? '';

        if (!$token) {
            test_result('Poller token', 'fail', 'not configured');
        } else {
            test_result('Poller token', 'pass', 'configured');
        }

        // Check for pending jobs (would indicate poller is working)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM jobs WHERE type = 'fulfillment' AND status = 'pending'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            test_result('Pending fulfillment jobs', 'pass', $result['count'] . ' waiting');
        } else {
            test_result('Pending fulfillment jobs', 'skip', 'none queued');
        }
    }
} catch (Exception $e) {
    test_result('Remote NAS configuration', 'fail', $e->getMessage());
}

echo "\n";

// Summary
echo "====================================\n";
printf("Results: %d passed, %d failed, %d skipped\n", $passed, $failed, $skipped);
echo "====================================\n\n";

if ($failed > 0) {
    echo "⚠ Fix the failed tests above before going live.\n\n";
    exit(1);
} else {
    echo "✓ Configuration looks good!\n\n";
    exit(0);
}
?>

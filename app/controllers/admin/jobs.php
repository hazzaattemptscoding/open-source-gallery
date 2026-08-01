<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/derivatives.php';
require_once __DIR__ . '/../../lib/db_compat.php';
require_once __DIR__ . '/../../lib/email_templates.php';
require_once __DIR__ . '/../../lib/mailer.php';

function admin_jobs_run_controller(PDO $pdo, array $config): void {
    require_admin();

    header('Content-Type: application/json');

    $lockToken = 'pm_cron_browser';
    if (!db_acquire_lock($pdo, $lockToken, 0)) {
        echo json_encode(['status' => 'locked']);
        return;
    }

    $startTime = microtime(true);
    $budget = 20.0;
    $jobsProcessed = 0;

    try {
        while ((microtime(true) - $startTime) < $budget) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $stmt = $pdo->prepare('
                UPDATE jobs
                SET status = ?, locked_at = ?
                WHERE status = ? AND run_after <= ?
                ORDER BY id ASC
                LIMIT 1
            ');
            $stmt->execute(['running', $now->format('Y-m-d H:i:s'), 'pending', $now->format('Y-m-d H:i:s')]);

        if ($stmt->rowCount() === 0) {
            break;
        }

        $stmt = $pdo->prepare('SELECT id, type, payload FROM jobs WHERE status = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute(['running']);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            break;
        }

        $jobId = (int)$job['id'];
        $type = (string)$job['type'];
        $payload = json_decode((string)$job['payload'], true) ?? [];

        $success = process_job($pdo, $type, $payload);

            if ($success) {
                $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
                $jobsProcessed++;
            } else {
                $pdo->prepare('UPDATE jobs SET status = ?, locked_at = NULL WHERE id = ?')->execute(['pending', $jobId]);
                break;
            }
        }
    } finally {
        db_release_lock($pdo, $lockToken);
    }

    echo json_encode([
        'status' => 'ok',
        'processed' => $jobsProcessed,
        'elapsed' => round(microtime(true) - $startTime, 2),
    ]);
}

function process_job(PDO $pdo, string $type, array $payload): bool {
    try {
        if ($type === 'derivative') {
            return process_derivative_job($pdo, $payload);
        } elseif ($type === 'email') {
            return process_email_job($pdo, $payload);
        }
        return true;
    } catch (Throwable $e) {
        error_log("Job processing failed: {$e->getMessage()}");
        return false;
    }
}

function process_email_job(PDO $pdo, array $payload): bool {
    $orderId = (int)($payload['order_id'] ?? 0);
    $emailType = (string)($payload['type'] ?? '');

    if (!$orderId || !$emailType) {
        return false;
    }

    // Load order
    $stmt = $pdo->prepare('
        SELECT id, public_token, email, total_pence, paid_at
        FROM orders
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return false;
    }

    if ($emailType === 'order_confirmation') {
        return send_order_confirmation_email($pdo, $order);
    } elseif ($emailType === 'refund') {
        return send_refund_email($pdo, $order);
    } elseif ($emailType === 'download_ready') {
        return send_download_ready_email($pdo, $order);
    }

    return false;
}

function send_order_confirmation_email(PDO $pdo, array $order): bool {
    global $config;

    $orderId = (int)$order['id'];
    $stmt = $pdo->prepare('
        SELECT p.public_token, e.title as event_title, oi.quantity, oi.unit_price_pence
        FROM order_items oi
        JOIN photos p ON oi.photo_id = p.id
        JOIN events e ON p.event_id = e.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ');
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsForTemplate = [];
    foreach ($items as $item) {
        $itemsForTemplate[] = [
            'description' => $item['event_title'] . ' (Photo)',
            'quantity' => (int)$item['quantity'],
            'price' => format_pence((int)$item['price_pence'], $config['currency'] ?? 'GBP'),
        ];
    }

    $orderTrackingUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $orderTrackingUrl .= "/order/" . urlencode($order['public_token']);
    $orderTrackingUrl .= "?email=" . urlencode($order['email']);

    $html = email_template_order_confirmation([
        'site_name' => $config['site']['name'] ?? 'Gallery',
        'customer_email' => $order['email'],
        'order_token' => $order['public_token'],
        'order_date' => date('j M Y', strtotime($order['paid_at'])),
        'items' => $itemsForTemplate,
        'total' => format_pence((int)$order['total_pence'], $config['currency'] ?? 'GBP'),
        'download_link' => $orderTrackingUrl,
        'download_expires' => date('j M Y', strtotime('+7 days', strtotime($order['paid_at']))),
        'gallery_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
    ]);

    return send_email(
        $order['email'],
        'Your Order Confirmation',
        $html,
        $config['mail']['from_address'] ?? 'noreply@example.com'
    );
}

function send_refund_email(PDO $pdo, array $order): bool {
    global $config;

    // Simplified refund email
    $html = <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; color: #111;">
<h1>Refund Processed</h1>
<p>Your refund for order #{$order['public_token']} has been processed.</p>
<p>The funds will return to your original payment method within 5-10 business days.</p>
</body>
</html>
HTML;

    return send_email(
        $order['email'],
        'Refund Confirmation',
        $html,
        $config['mail']['from_address'] ?? 'noreply@example.com'
    );
}

function send_download_ready_email(PDO $pdo, array $order): bool {
    global $config;

    $orderTrackingUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $orderTrackingUrl .= "/order/" . urlencode($order['public_token']);
    $orderTrackingUrl .= "?email=" . urlencode($order['email']);

    $html = email_template_download_ready([
        'site_name' => $config['site']['name'] ?? 'Gallery',
        'order_token' => $order['public_token'],
        'download_link' => $orderTrackingUrl,
        'items_ready' => 1,
    ]);

    return send_email(
        $order['email'],
        'Your Files Are Ready',
        $html,
        $config['mail']['from_address'] ?? 'noreply@example.com'
    );
}

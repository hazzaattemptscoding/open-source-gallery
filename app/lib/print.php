<?php
/**
 * Print fulfillment integration (Printful, Printware, etc.).
 * Manages print orders and delivery to fulfillment partners.
 */

declare(strict_types=1);

/**
 * Get print settings for a provider.
 */
function get_print_settings(PDO $pdo, int $providerId): ?array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT ps.*, pp.name as provider_name, pp.api_endpoint
            FROM print_settings ps
            JOIN print_providers pp ON pp.id = ps.provider_id
            WHERE ps.provider_id = ?
        SQL);
        $stmt->execute([$providerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('print: get_print_settings() failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if print fulfillment is enabled.
 */
function is_print_enabled(PDO $pdo): bool {
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM print_settings WHERE enabled = 1 LIMIT 1');
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('print: is_print_enabled() failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Submit an order for print fulfillment.
 */
function submit_print_order(
    PDO $pdo,
    int $orderId,
    int $providerId,
    array $items
): bool {
    try {
        $settings = get_print_settings($pdo, $providerId);
        if (!$settings || !$settings['enabled']) {
            return false;
        }

        // Create print order record
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO print_orders (order_id, provider_id, status, submitted_at)
            VALUES (?, ?, 'submitted', CURRENT_TIMESTAMP)
        SQL);
        $stmt->execute([$orderId, $providerId]);
        $printOrderId = (int)$pdo->lastInsertId();

        // Add print items
        $itemStmt = $pdo->prepare(<<<'SQL'
            INSERT INTO print_items (print_order_id, photo_id, product_type, quantity, price_pence)
            VALUES (?, ?, ?, ?, ?)
        SQL);

        foreach ($items as $item) {
            $itemStmt->execute([
                $printOrderId,
                $item['photo_id'],
                $item['product_type'] ?? 'photo_print',
                $item['quantity'] ?? 1,
                $item['price_pence'] ?? 0,
            ]);
        }

        // Send to provider API if configured
        if ($settings['provider_name'] === 'Printful') {
            return submit_to_printful($pdo, $printOrderId, $settings);
        }

        return true;
    } catch (Throwable $e) {
        error_log('print: submit_print_order() failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Submit order to Printful API.
 */
function submit_to_printful(PDO $pdo, int $printOrderId, array $settings): bool {
    try {
        // Get order details
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT o.id, o.email, o.total_pence,
                   pi.photo_id, pi.product_type, pi.quantity, pi.price_pence
            FROM print_orders po
            JOIN orders o ON po.order_id = o.id
            JOIN print_items pi ON pi.print_order_id = po.id
            WHERE po.id = ?
        SQL);
        $stmt->execute([$printOrderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return false;
        }

        // Build Printful API request
        $apiKey = $settings['api_key'];
        $endpoint = $settings['api_endpoint'];

        $payload = [
            'external_id' => "order_$printOrderId",
            'shipping' => 'GROUND',
            'recipient' => [
                'email' => $items[0]['email'],
            ],
            'items' => [],
        ];

        foreach ($items as $item) {
            $payload['items'][] = [
                'product_id' => $this->mapProductToPrintful($item['product_type']),
                'quantity' => $item['quantity'],
                'external_id' => $item['photo_id'],
            ];
        }

        // Make HTTP request to Printful
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Basic ' . base64_encode("$apiKey:"),
                    'Content-Type: application/json',
                ],
                'content' => json_encode($payload),
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents("$endpoint/orders", false, $context);
        if (!$response) {
            return false;
        }

        $result = json_decode($response, true);
        if (isset($result['result']['id'])) {
            // Update with Printful order ID
            $stmt = $pdo->prepare(<<<'SQL'
                UPDATE print_orders
                SET external_order_id = ?, status = 'processing'
                WHERE id = ?
            SQL);
            $stmt->execute([$result['result']['id'], $printOrderId]);
            return true;
        }

        return false;
    } catch (Throwable $e) {
        error_log('print: submit_to_printful() failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Map product type to Printful product ID.
 * This is a stub - real integration would use Printful's product catalog.
 */
function mapProductToPrintful(string $productType): int {
    return match ($productType) {
        'photo_print_4x6' => 2,
        'photo_print_5x7' => 3,
        'photo_print_8x10' => 4,
        'mug' => 26,
        'tshirt' => 1,
        'hoodie' => 44,
        default => 2, // Default to 4x6 print
    };
}

/**
 * Get print order status.
 */
function get_print_order_status(PDO $pdo, int $printOrderId): ?array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT po.*, pp.name as provider_name
            FROM print_orders po
            JOIN print_providers pp ON pp.id = po.provider_id
            WHERE po.id = ?
        SQL);
        $stmt->execute([$printOrderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('print: get_print_order_status() failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all print orders for an order.
 */
function get_print_orders_for_order(PDO $pdo, int $orderId): array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT po.*, pp.name as provider_name
            FROM print_orders po
            JOIN print_providers pp ON pp.id = po.provider_id
            WHERE po.order_id = ?
            ORDER BY po.created_at DESC
        SQL);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('print: get_print_orders_for_order() failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Cancel a print order.
 */
function cancel_print_order(PDO $pdo, int $printOrderId): bool {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            UPDATE print_orders
            SET status = 'cancelled'
            WHERE id = ?
        SQL);
        $stmt->execute([$printOrderId]);
        return true;
    } catch (Throwable $e) {
        error_log('print: cancel_print_order() failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if an order has print items.
 */
function order_has_print_items(PDO $pdo, int $orderId): bool {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*) FROM order_items
            WHERE order_id = ? AND item_type = 'print'
        SQL);
        $stmt->execute([$orderId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('print: order_has_print_items() failed: ' . $e->getMessage());
        return false;
    }
}

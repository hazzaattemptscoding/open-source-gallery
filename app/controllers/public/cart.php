<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/cart.php';
require_once __DIR__ . '/../../lib/currency.php';

/**
 * GET /cart — shows the running total, priced fresh from the DB every
 * time (the cookie only ever held IDs). No login, no CSRF: architecture
 * section 4 treats a forged cart-add as harmless since checkout re-validates
 * everything against the DB before a Stripe session is ever created.
 */
function public_cart_page_controller(PDO $pdo, array $config): void {
    $siteName = $config['site']['name'] ?? 'Gallery';
    $currencyCode = $config['currency'] ?? 'GBP';

    $items = cart_get($config);
    $priced = cart_price($pdo, $items);

    render(__DIR__ . '/../../views/public/cart.php', [
        'siteName' => $siteName,
        'currencyCode' => $currencyCode,
        'lines' => $priced['lines'],
        'totalPence' => $priced['total_pence'],
    ]);
}

/** POST /cart/add {type, id} — single-tap add, no confirm step (CLAUDE.md product rules). */
function public_cart_add_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true);
    $type = (string)($input['type'] ?? '');
    $id = (int)($input['id'] ?? 0);

    if (!in_array($type, ['photo', 'session_bundle', 'event_bundle'], true) || $id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid item']);
        return;
    }

    if (!cart_item_exists($pdo, $type, $id)) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not available']);
        return;
    }

    $items = cart_add($config, $type, $id);
    echo json_encode(['ok' => true, 'count' => count($items)]);
}

/** POST /cart/remove {type, id} */
function public_cart_remove_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true);
    $type = (string)($input['type'] ?? '');
    $id = (int)($input['id'] ?? 0);

    if (!in_array($type, ['photo', 'session_bundle', 'event_bundle'], true) || $id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid item']);
        return;
    }

    $items = cart_remove($config, $type, $id);
    echo json_encode(['ok' => true, 'count' => count($items)]);
}

function cart_item_exists(PDO $pdo, string $type, int $id): bool {
    if ($type === 'photo') {
        $stmt = $pdo->prepare('SELECT id FROM photos WHERE id = ? AND status = ? AND media_type = ?');
        $stmt->execute([$id, 'live', 'photo']);
    } elseif ($type === 'session_bundle') {
        $stmt = $pdo->prepare('
            SELECT s.id FROM sessions s
            JOIN events e ON e.id = s.event_id
            WHERE s.id = ? AND e.price_session_pence IS NOT NULL
        ');
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ? AND price_event_pence IS NOT NULL');
        $stmt->execute([$id]);
    }
    return (bool)$stmt->fetchColumn();
}

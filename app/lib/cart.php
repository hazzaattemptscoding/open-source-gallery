<?php
declare(strict_types=1);

require_once __DIR__ . '/signer.php';

const CART_COOKIE_NAME = 'pm_cart';
const CART_COOKIE_DAYS = 14;

/**
 * Cart lives entirely in a signed cookie holding IDs only — never prices
 * (docs/architecture.md section 4: "the cookie can't set a price"). A
 * forged cart-add is harmless since every price is re-read from the DB
 * at cart-display and checkout time, which is also why cart mutation
 * endpoints skip CSRF: there's nothing here worth forging.
 *
 * Each item is ['type' => 'photo'|'session_bundle'|'event_bundle', 'id' => int].
 *
 * @return array<int, array{type: string, id: int}>
 */
function cart_get(array $config): array {
    $raw = $_COOKIE[CART_COOKIE_NAME] ?? '';
    if ($raw === '') {
        return [];
    }

    $parts = explode('.', $raw, 2);
    if (count($parts) !== 2) {
        return [];
    }

    [$encodedPayload, $signature] = $parts;
    $payload = base64_decode(strtr($encodedPayload, '-_', '+/'), true);
    if ($payload === false) {
        return [];
    }

    $hmacKey = $config['security']['hmac_key'] ?? '';
    if ($hmacKey === '' || !verify_payload($payload, $signature, $hmacKey)) {
        return [];
    }

    $items = json_decode($payload, true);
    if (!is_array($items)) {
        return [];
    }

    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['type'], $item['id'])) {
            continue;
        }
        $type = (string)$item['type'];
        if (!in_array($type, ['photo', 'session_bundle', 'event_bundle'], true)) {
            continue;
        }
        $clean[] = ['type' => $type, 'id' => (int)$item['id']];
    }

    return $clean;
}

/** @param array<int, array{type: string, id: int}> $items */
function cart_save(array $config, array $items): void {
    $hmacKey = $config['security']['hmac_key'] ?? '';
    $payload = json_encode(array_values($items));
    $signature = sign_payload($payload, $hmacKey);
    $encodedPayload = strtr(base64_encode($payload), '+/', '-_');
    $value = $encodedPayload . '.' . $signature;

    $isHttps = (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    setcookie(CART_COOKIE_NAME, $value, [
        'expires' => time() + (CART_COOKIE_DAYS * 86400),
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        // Lax, not Strict: a customer following a shared link into the site
        // (e.g. from WhatsApp) with an item already in cart shouldn't lose it.
        'samesite' => 'Lax',
    ]);
}

function cart_add(array $config, string $type, int $id): array {
    $items = cart_get($config);
    foreach ($items as $item) {
        if ($item['type'] === $type && $item['id'] === $id) {
            return $items;
        }
    }
    $items[] = ['type' => $type, 'id' => $id];
    cart_save($config, $items);
    return $items;
}

function cart_remove(array $config, string $type, int $id): array {
    $items = cart_get($config);
    $items = array_values(array_filter(
        $items,
        fn($item) => !($item['type'] === $type && $item['id'] === $id)
    ));
    cart_save($config, $items);
    return $items;
}

/**
 * Re-reads every cart line from the DB (live photos only; bundles only if
 * the event still offers that price tier) and prices it fresh — the cart
 * cookie is never trusted for anything but which IDs are in it.
 *
 * @return array{lines: list<array<string,mixed>>, total_pence: int}
 */
function cart_price(PDO $pdo, array $items): array {
    $lines = [];
    $total = 0;

    foreach ($items as $item) {
        if ($item['type'] === 'photo') {
            $stmt = $pdo->prepare('
                SELECT p.id, p.public_token, p.event_id, e.title AS event_title, e.price_single_pence
                FROM photos p
                JOIN events e ON e.id = p.event_id
                WHERE p.id = ? AND p.status = ? AND p.media_type = ?
            ');
            $stmt->execute([$item['id'], 'live', 'photo']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                continue;
            }
            $price = (int)$row['price_single_pence'];
            $lines[] = [
                'type' => 'photo',
                'id' => (int)$row['id'],
                'description' => "Photo {$row['public_token']} — {$row['event_title']}",
                'unit_price_pence' => $price,
            ];
            $total += $price;
        } elseif ($item['type'] === 'session_bundle') {
            $stmt = $pdo->prepare('
                SELECT s.id, s.name, e.title AS event_title, e.price_session_pence
                FROM sessions s
                JOIN events e ON e.id = s.event_id
                WHERE s.id = ?
            ');
            $stmt->execute([$item['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['price_session_pence'] === null) {
                continue;
            }
            $price = (int)$row['price_session_pence'];
            $lines[] = [
                'type' => 'session_bundle',
                'id' => (int)$row['id'],
                'description' => "Full session — {$row['name']} ({$row['event_title']})",
                'unit_price_pence' => $price,
            ];
            $total += $price;
        } elseif ($item['type'] === 'event_bundle') {
            $stmt = $pdo->prepare('SELECT id, title, price_event_pence FROM events WHERE id = ?');
            $stmt->execute([$item['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['price_event_pence'] === null) {
                continue;
            }
            $price = (int)$row['price_event_pence'];
            $lines[] = [
                'type' => 'event_bundle',
                'id' => (int)$row['id'],
                'description' => "Full event — {$row['title']}",
                'unit_price_pence' => $price,
            ];
            $total += $price;
        }
    }

    return ['lines' => $lines, 'total_pence' => $total];
}

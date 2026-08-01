<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/wishlist.php';
require_once __DIR__ . '/../../lib/currency.php';

/** GET /favorites — lists the visitor's favourited photos, with an add-all-to-cart action. */
function public_favorites_page_controller(PDO $pdo, array $config): void {
    $siteName = $config['site']['name'] ?? 'Gallery';
    $currencyCode = $config['currency'] ?? 'GBP';

    // No cookie yet means nothing has ever been favourited on this browser;
    // render the empty state rather than minting a token nobody will use.
    $token = $_COOKIE[FAVORITES_COOKIE_NAME] ?? '';
    $items = $token !== '' ? get_wishlist_items($pdo, $token) : [];

    render(__DIR__ . '/../../views/public/favorites.php', [
        'siteName' => $siteName,
        'currencyCode' => $currencyCode,
        'items' => $items,
    ]);
}

/** POST /favorites/add {photo_id} — single-tap, no confirm step (CLAUDE.md product rules), mirrors /cart/add. */
function public_favorites_add_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true);
    $photoId = (int)($input['photo_id'] ?? 0);

    if ($photoId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid photo']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM photos WHERE id = ? AND status = ? AND media_type = ?');
    $stmt->execute([$photoId, 'live', 'photo']);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Photo not available']);
        return;
    }

    $token = get_or_create_favorites_token();
    if (!add_to_wishlist($pdo, $token, $photoId)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not add favourite']);
        return;
    }

    $count = count(get_wishlist_items($pdo, $token));
    echo json_encode(['ok' => true, 'count' => $count]);
}

/** POST /favorites/remove {photo_id} — mirrors /cart/remove. */
function public_favorites_remove_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true);
    $photoId = (int)($input['photo_id'] ?? 0);

    if ($photoId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid photo']);
        return;
    }

    // No cookie means nothing was ever favourited, so removal trivially
    // succeeds rather than minting a fresh empty token for a no-op.
    $token = $_COOKIE[FAVORITES_COOKIE_NAME] ?? '';
    $count = 0;
    if ($token !== '') {
        remove_from_wishlist($pdo, $token, $photoId);
        $count = count(get_wishlist_items($pdo, $token));
    }

    echo json_encode(['ok' => true, 'count' => $count]);
}

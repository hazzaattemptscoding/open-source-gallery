<?php
/**
 * Customer wishlists ("favourites"), persisted by an opaque per-visitor
 * token, no login required — same no-accounts model as the cart
 * (app/lib/cart.php).
 *
 * Deliberately not modelled on the cart's cookie: the cart cookie carries
 * its own contents directly (HMAC-signed, since a forged cart item is only
 * harmless because price is always re-read from the DB, per that file's own
 * comment). A favourites token is different in kind -- it is a lookup key
 * into the wishlists table, not a payload -- so what it needs is
 * unguessability, not tamper-evidence. There is nothing to tamper with in
 * an opaque random token.
 */

declare(strict_types=1);

const FAVORITES_COOKIE_NAME = 'pm_favorites';
const FAVORITES_COOKIE_DAYS = 365;

/**
 * Read the visitor's favourites token, minting and cookie-ing a new one if
 * this is their first visit. Called at the top of every favourites request
 * so a fresh visitor always gets a working, if empty, favourites list
 * rather than an error.
 */
function get_or_create_favorites_token(): string {
    $existing = $_COOKIE[FAVORITES_COOKIE_NAME] ?? '';
    if (is_string($existing) && preg_match('/^[a-f0-9]{64}$/', $existing)) {
        return $existing;
    }

    $token = bin2hex(random_bytes(32));
    $isHttps = (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    setcookie(FAVORITES_COOKIE_NAME, $token, [
        'expires' => time() + (FAVORITES_COOKIE_DAYS * 86400),
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        // Lax, matching the cart cookie: a shared gallery link opened from
        // another site is still a top-level GET navigation, which Lax sends
        // the cookie on. It's also why a cross-site POST forging a favourite
        // add/remove can't work -- Lax excludes the cookie from cross-site
        // POSTs, which is what lets these two endpoints skip a CSRF token
        // the same way cart_add()/cart_remove() already do.
        'samesite' => 'Lax',
    ]);
    // Available to the rest of this request without a second round trip.
    $_COOKIE[FAVORITES_COOKIE_NAME] = $token;

    return $token;
}

/**
 * Get or create wishlist for customer token.
 */
function get_customer_wishlist(PDO $pdo, string $customerToken): ?array {
    try {
        $stmt = $pdo->prepare('SELECT id, customer_token, name, is_default, created_at FROM wishlists WHERE customer_token = ? AND is_default = 1');
        $stmt->execute([$customerToken]);
        $wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wishlist) {
            $stmt = $pdo->prepare(<<<'SQL'
                INSERT INTO wishlists (customer_token, name, is_default)
                VALUES (?, ?, 1)
            SQL);
            $stmt->execute([$customerToken, 'Default']);
            $wishlist = get_customer_wishlist($pdo, $customerToken);
        }

        return $wishlist;
    } catch (Throwable $e) {
        error_log('wishlist: get_customer_wishlist() failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Add photo to wishlist. Idempotent: adding an already-favourited photo
 * succeeds without a second row, rather than relying on the UNIQUE
 * (wishlist_id, photo_id) constraint to fail and having the caller unable to
 * tell that apart from a real failure -- both would have hit the same catch
 * block and returned the same false.
 */
function add_to_wishlist(PDO $pdo, string $customerToken, int $photoId, string $notes = ''): bool {
    try {
        $wishlist = get_customer_wishlist($pdo, $customerToken);
        if (!$wishlist) {
            return false;
        }

        if (is_in_wishlist($pdo, $customerToken, $photoId)) {
            return true;
        }

        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO wishlist_items (wishlist_id, photo_id, notes)
            VALUES (?, ?, ?)
        SQL);
        return $stmt->execute([$wishlist['id'], $photoId, $notes]);
    } catch (Throwable $e) {
        error_log('wishlist: add_to_wishlist() failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Remove from wishlist.
 */
function remove_from_wishlist(PDO $pdo, string $customerToken, int $photoId): bool {
    try {
        $wishlist = get_customer_wishlist($pdo, $customerToken);
        if (!$wishlist) {
            return false;
        }

        $stmt = $pdo->prepare('DELETE FROM wishlist_items WHERE wishlist_id = ? AND photo_id = ?');
        return $stmt->execute([$wishlist['id'], $photoId]);
    } catch (Throwable $e) {
        error_log('wishlist: remove_from_wishlist() failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get wishlist items with photo details.
 */
function get_wishlist_items(PDO $pdo, string $customerToken, int $limit = 100): array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT w.id, p.id as photo_id, p.public_token, p.original_filename,
                   COALESCE(p.price_pence, e.price_single_pence) AS price_pence,
                   p.width, p.height, wi.notes, wi.added_at
            FROM wishlists w
            JOIN wishlist_items wi ON w.id = wi.wishlist_id
            JOIN photos p ON wi.photo_id = p.id
            JOIN events e ON p.event_id = e.id
            WHERE w.customer_token = ? AND p.status = 'live'
            ORDER BY wi.added_at DESC
            LIMIT ?
        SQL);
        $stmt->execute([$customerToken, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('wishlist: get_wishlist_items() failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check if photo is in wishlist.
 */
function is_in_wishlist(PDO $pdo, string $customerToken, int $photoId): bool {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*) FROM wishlist_items wi
            JOIN wishlists w ON wi.wishlist_id = w.id
            WHERE w.customer_token = ? AND wi.photo_id = ?
        SQL);
        $stmt->execute([$customerToken, $photoId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('wishlist: is_in_wishlist() failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Which of the given photo IDs are already in this customer's wishlist, as
 * an id => true map for an O(1) isset() check per grid tile. Batched
 * against the current page's photo IDs rather than one is_in_wishlist()
 * call per tile, so rendering a page of GALLERY_PAGE_SIZE (60) photos costs
 * one query, not 60.
 *
 * @param list<int> $photoIds
 * @return array<int, true>
 */
function get_wishlisted_photo_ids(PDO $pdo, string $customerToken, array $photoIds): array {
    if ($photoIds === []) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
        $stmt = $pdo->prepare(<<<SQL
            SELECT wi.photo_id
            FROM wishlist_items wi
            JOIN wishlists w ON wi.wishlist_id = w.id
            WHERE w.customer_token = ? AND wi.photo_id IN ({$placeholders})
        SQL);
        $stmt->execute([$customerToken, ...$photoIds]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $result[(int)$id] = true;
        }
        return $result;
    } catch (Throwable $e) {
        error_log('wishlist: get_wishlisted_photo_ids() failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Export wishlist as cart (for quick re-order).
 */
function wishlist_to_cart(PDO $pdo, string $customerToken): array {
    $items = get_wishlist_items($pdo, $customerToken);
    return array_map(fn($item) => [
        'photo_id' => $item['photo_id'],
        'public_token' => $item['public_token'],
        'price_pence' => $item['price_pence'],
    ], $items);
}

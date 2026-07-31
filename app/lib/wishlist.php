<?php
/**
 * Customer wishlists and favorites.
 * Persisted by signed customer token, no login required.
 */

declare(strict_types=1);

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
 * Add photo to wishlist.
 */
function add_to_wishlist(PDO $pdo, string $customerToken, int $photoId, string $notes = ''): bool {
    try {
        $wishlist = get_customer_wishlist($pdo, $customerToken);
        if (!$wishlist) {
            return false;
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

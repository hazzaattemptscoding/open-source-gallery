<?php
/**
 * Works out the buying options to offer on a gallery page, and what each one
 * actually costs per photo.
 *
 * Why this exists
 * ---------------
 * The cart, the pricing code, order_items and checkout have all supported
 * session and event bundles since the initial schema. Nothing ever rendered a
 * way to buy one, so in practice the only purchasable product was a single
 * photo. This builds the list of options a view can show.
 *
 * The ordering is deliberate and is the point of the exercise. At grassroots
 * volumes a flat "all of it, one price" outperforms per-photo pricing, so the
 * widest bundle is listed first and the single photo last. A bundle presented
 * underneath a single-photo price reads as an upsell; presented above it, it
 * reads as the normal way to buy.
 *
 * Every option carries an effective per-photo price, because that is the sum
 * the customer would otherwise do in their head and get wrong. No price is
 * hardcoded anywhere here: everything comes from the event's own columns and
 * the discount tiers in config.
 */

declare(strict_types=1);

require_once __DIR__ . '/currency.php';

/**
 * How many live, sellable photos an event contains.
 *
 * Videos are excluded because bundles are photo products, and the count feeds
 * the per-photo maths shown to the customer.
 */
function count_event_photos(PDO $pdo, int $eventId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM photos
          WHERE event_id = ? AND status = 'live' AND media_type = 'photo'"
    );
    $stmt->execute([$eventId]);
    return (int) $stmt->fetchColumn();
}

/** How many live, sellable photos one session contains. */
function count_session_photos(PDO $pdo, int $sessionId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM photos
          WHERE session_id = ? AND status = 'live' AND media_type = 'photo'"
    );
    $stmt->execute([$sessionId]);
    return (int) $stmt->fetchColumn();
}

/**
 * The discount percentage that applies to a given number of photos.
 *
 * Mirrors the tier selection in cart_price() so the figure quoted on the page
 * matches what the cart will actually charge. Kept as a separate small function
 * rather than duplicating the loop, because a quoted price that disagrees with
 * the checkout total is worse than quoting nothing at all.
 */
function volume_discount_percent(array $config, int $photoCount): float
{
    $discounts = $config['discounts'] ?? [];
    if (empty($discounts)) {
        return 0.0;
    }

    $thresholds = array_keys($discounts);
    rsort($thresholds);

    foreach ($thresholds as $threshold) {
        if ($photoCount >= $threshold) {
            return (float) $discounts[$threshold];
        }
    }

    return 0.0;
}

/**
 * What a set of N single photos actually costs once volume discount applies.
 *
 * @return array{total_pence:int, discount_percent:float, effective_each_pence:int}
 */
function price_photo_set(array $config, int $unitPricePence, int $photoCount): array
{
    $gross = $unitPricePence * max(0, $photoCount);
    $percent = volume_discount_percent($config, $photoCount);
    $total = $gross - (int) round($gross * $percent);

    return [
        'total_pence' => $total,
        'discount_percent' => $percent,
        'effective_each_pence' => $photoCount > 0 ? (int) round($total / $photoCount) : $unitPricePence,
    ];
}

/**
 * Build the ordered list of purchase options for an event page.
 *
 * Widest bundle first, single photo last. A bundle is only offered when the
 * event actually prices it (a NULL price column means "not offered", which is
 * the same rule cart_item_exists() enforces on the way in) and when it covers
 * at least one photo, so an empty session never advertises a bundle.
 *
 * @param array      $event         Row from events, including the three price columns.
 * @param array|null $activeSession The session being viewed, or null on the whole-event view.
 * @return list<array<string,mixed>>
 */
function build_purchase_options(
    PDO $pdo,
    array $config,
    array $event,
    ?array $activeSession,
    string $currencyCode
): array {
    $options = [];
    $eventId = (int) $event['id'];
    $singlePence = (int) $event['price_single_pence'];

    // --- Whole event -------------------------------------------------------
    if ($event['price_event_pence'] !== null) {
        $covers = count_event_photos($pdo, $eventId);
        if ($covers > 0) {
            $price = (int) $event['price_event_pence'];
            $options[] = [
                'type' => 'event_bundle',
                'id' => $eventId,
                'label' => 'Every photo from this event',
                'covers' => $covers,
                'price_pence' => $price,
                'price_formatted' => format_pence($price, $currencyCode),
                'effective_each_formatted' => format_pence((int) round($price / $covers), $currencyCode),
                'compare_pence' => $singlePence * $covers,
            ];
        }
    }

    // --- One session -------------------------------------------------------
    if ($activeSession !== null && $event['price_session_pence'] !== null) {
        $covers = count_session_photos($pdo, (int) $activeSession['id']);
        if ($covers > 0) {
            $price = (int) $event['price_session_pence'];
            $options[] = [
                'type' => 'session_bundle',
                'id' => (int) $activeSession['id'],
                'label' => 'Every photo from ' . $activeSession['name'],
                'covers' => $covers,
                'price_pence' => $price,
                'price_formatted' => format_pence($price, $currencyCode),
                'effective_each_formatted' => format_pence((int) round($price / $covers), $currencyCode),
                'compare_pence' => $singlePence * $covers,
            ];
        }
    }

    // --- A single photo ----------------------------------------------------
    // Always last, and always present: it is the fallback, not the headline.
    // No id, because it is not a thing you add to the cart from here; it
    // describes what tapping a photo in the grid will cost.
    $options[] = [
        'type' => 'single',
        'id' => null,
        'label' => 'A single photo',
        'covers' => 1,
        'price_pence' => $singlePence,
        'price_formatted' => format_pence($singlePence, $currencyCode),
        'effective_each_formatted' => format_pence($singlePence, $currencyCode),
        'compare_pence' => null,
    ];

    // Saving against buying the same photos one at a time. Only shown when it
    // is genuinely a saving: if an admin prices a bundle above the sum of its
    // parts, quoting a negative "saving" would be worse than quoting none.
    foreach ($options as &$option) {
        $option['saving_formatted'] = null;
        if ($option['compare_pence'] !== null && $option['compare_pence'] > $option['price_pence']) {
            $option['saving_formatted'] = format_pence(
                $option['compare_pence'] - $option['price_pence'],
                $currencyCode
            );
        }
    }

    return $options;
}

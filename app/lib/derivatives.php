<?php
declare(strict_types=1);

require_once __DIR__ . '/images.php';

/**
 * Generates the 400/800/1600px derivatives for one photo and flips it to
 * 'live'. Shared by cron/run.php and the browser-assisted drain
 * (app/controllers/admin/jobs.php) so the two paths can't drift apart.
 */
function process_derivative_job(PDO $pdo, array $payload): bool {
    $photoId = (int)($payload['photo_id'] ?? 0);
    if ($photoId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, event_id, public_token, file_extension FROM photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        error_log("derivatives: photo {$photoId} not found, cannot build derivatives");
        return false;
    }

    $eventId = (int)$photo['event_id'];
    $token = (string)$photo['public_token'];
    $ext = (string)($photo['file_extension'] ?? 'jpg');
    $hiresPath = __DIR__ . "/../../storage/hires/{$eventId}/{$token}.{$ext}";

    if (!file_exists($hiresPath)) {
        // Worth logging loudly: the original is the one file that cannot be
        // regenerated, so a missing one is a storage problem, not a job problem.
        error_log("derivatives: original missing for photo {$photoId} at {$hiresPath}");
        return false;
    }

    $settings = get_watermark_settings($pdo);
    // Include photo ID in watermark text so previews can be identified by buyers
    $settings['text'] = "ID: {$photoId}";

    $sizes = [400, 800, 1200, 1600];
    $derivPath = __DIR__ . '/../../public/media/d';

    if (!is_dir($derivPath)) {
        @mkdir($derivPath, 0755, true);
    }

    $derivBytes = 0;
    try {
        foreach ($sizes as $size) {
            $outPath = "{$derivPath}/{$token}-{$size}.jpg";
            $watermark = $settings['enabled'] && in_array(watermark_size_tier($size), $settings['apply_to_sizes'], true);
            generate_derivative($hiresPath, $outPath, $size, $watermark, $settings);
            $bytes = (int)@filesize($outPath);
            if ($bytes > 0) {
                $derivBytes += $bytes;
            }
        }
    } catch (Throwable $e) {
        // The photo is flipped to failed either way; without this line the
        // reason (usually GD exhausting memory on a large original) was lost.
        error_log("derivatives: photo {$photoId} failed: " . get_class($e) . ': ' . $e->getMessage());
        $pdo->prepare('UPDATE photos SET status = ? WHERE id = ?')->execute(['failed', $photoId]);
        return false;
    }

    $pdo->prepare('UPDATE photos SET status = ?, deriv_size_bytes = ? WHERE id = ?')
        ->execute(['live', $derivBytes, $photoId]);

    return true;
}

/**
 * Reads the watermark_* rows from settings (percent-based opacity/scale,
 * per migrations/001_initial_schema.sql seed data) and converts them into
 * the fractions/ints app/lib/images.php expects.
 */
/**
 * The pixel width of each generated derivative, tagged with the tier name
 * app/controllers/admin/watermarks.php's apply_to_sizes column uses
 * ('sm,md,lg'). Kept here, next to the one place $sizes is defined, so the
 * two can never drift apart the way the size list and the watermark
 * settings themselves already had.
 */
function watermark_size_tier(int $pixelWidth): string {
    return match ($pixelWidth) {
        400 => 'sm',
        800 => 'md',
        1600 => 'lg',
        default => 'md',
    };
}

/**
 * Reads app/controllers/admin/watermarks.php's own table.
 *
 * Previously read a `watermark_%` prefix out of the legacy `settings`
 * key/value table (migrations/001), which nothing in the Watermarks admin
 * page has ever written to — that page writes watermark_settings (schema
 * added for the C4 preset UI: position, opacity, text, enabled,
 * apply_to_sizes). The two were never connected, so editing watermark
 * settings through the page built for exactly that had no effect on a
 * single generated image; every derivative rendered from whatever was left
 * in the `settings` table's defaults regardless of what the admin set.
 *
 * `scale` is gone: it was read out of the old table but nothing in
 * app/lib/images.php's watermark renderer ever consumed it, so it was
 * configuring nothing even before this fix.
 *
 * `min_width` is gone too, replaced by `apply_to_sizes`: the admin UI has
 * never offered a numeric width, only the sm/md/lg checkboxes, so a
 * min_width read from a table the UI doesn't write was always just today's
 * default (800) regardless of the admin's actual choice.
 */
function get_watermark_settings(PDO $pdo): array {
    $row = $pdo->query('SELECT position, opacity, text, enabled, apply_to_sizes FROM watermark_settings WHERE id = 1')
                ->fetch(PDO::FETCH_ASSOC);

    $tiers = $row ? array_filter(array_map('trim', explode(',', (string)$row['apply_to_sizes']))) : ['sm', 'md', 'lg'];

    return [
        'enabled' => $row ? (bool)$row['enabled'] : true,
        'opacity' => $row ? (float)$row['opacity'] : 0.35,
        // The admin form (app/views/admin/watermarks.php) submits underscored
        // values ('bottom_right'); watermark_xy() (app/lib/images.php) matches
        // on hyphens. Left unconverted, 3 of the form's 4 options fell through
        // to the hyphenated default and rendered bottom-right regardless of
        // what the admin picked.
        'position' => str_replace('_', '-', $row['position'] ?? 'bottom-right'),
        'apply_to_sizes' => $tiers ?: ['sm', 'md', 'lg'],
        'text' => ($row['text'] ?? '') !== '' ? $row['text'] : 'PREVIEW',
    ];
}

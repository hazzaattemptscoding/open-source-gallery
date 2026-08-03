<?php
/**
 * The free share image: a 1080x1350 branded card built from a gallery photo.
 *
 * This is a different product from the clean file, and the difference is the
 * point. The share image is deliberately given away: it is social-sized,
 * branded, watermarked, and carries the kart number and class. It travels
 * through WhatsApp groups and Instagram stories and brings people back to the
 * gallery. The clean, unwatermarked original is what gets sold.
 *
 * Giving away a watermarked, socially-sized crop does not cannibalise the sale.
 * Nobody prints a 1080px watermarked JPEG; they post it, and their friends ask
 * where it came from.
 *
 * What is deliberately NOT on the card
 * ------------------------------------
 * The driver's name. This image is designed to be posted publicly, which makes
 * it the single worst place to print a child's name. Identity on the card is
 * kart number and class only, the same rule as every other public surface. See
 * docs/PRIVACY-DESIGN.md.
 *
 * Finishing position is also absent, despite being in the original plan: no
 * results or classification data exists anywhere in this schema. Printing a
 * position would mean inventing one.
 *
 * Caching
 * -------
 * Generated on first request and written to public/media/share/, so Apache
 * serves every later request as a static file without PHP running at all. That
 * matches how derivatives already work and keeps a share link cheap when a post
 * does well.
 */

declare(strict_types=1);

/** Instagram portrait. The most shared size, and the least cropped on a phone. */
const SHARE_IMAGE_WIDTH = 1080;
const SHARE_IMAGE_HEIGHT = 1350;

/** Height of the branding bar along the bottom. */
const SHARE_IMAGE_FOOTER_HEIGHT = 190;

/** Absolute path of the cached share image for a photo token. */
function share_image_path(string $publicToken): string
{
    return __DIR__ . '/../../public/media/share/' . $publicToken . '.jpg';
}

/** Public URL of the share image for a photo token. */
function share_image_url(string $publicToken): string
{
    return '/media/share/' . $publicToken . '.jpg';
}

/**
 * Pick the best available source file for a photo.
 *
 * Prefers the 1600px derivative over the hi-res original: it is already sized
 * close to what is needed, so scaling it costs a fraction of the memory of
 * decoding a 24-megapixel original, which matters on shared hosting where the
 * PHP memory limit is not generous.
 */
function share_image_source_path(string $publicToken): ?string
{
    $candidates = [
        __DIR__ . '/../../public/media/d/' . $publicToken . '-1600.jpg',
        __DIR__ . '/../../public/media/d/' . $publicToken . '-800.jpg',
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Draw text using the best facility available.
 *
 * FreeType gives real typography, but a shared host may have GD without it, and
 * a share image that fails to render is worse than one in a bitmap font. Falls
 * back to GD's built-in font, scaled up as far as it goes.
 *
 * @param resource|GdImage $img
 */
function share_image_text($img, string $text, int $x, int $y, int $size, int $colour, bool $rightAlign = false): void
{
    $font = share_image_font();

    if ($font !== null) {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = abs($box[4] - $box[0]);
        imagettftext($img, $size, 0, $rightAlign ? $x - $width : $x, $y, $colour, $font, $text);
        return;
    }

    // No FreeType: GD's largest built-in font is 5, and it cannot be scaled, so
    // the card degrades to something plain but legible rather than blank.
    $builtin = 5;
    $width = imagefontwidth($builtin) * strlen($text);
    imagestring($img, $builtin, $rightAlign ? $x - $width : $x, $y - imagefontheight($builtin), $text, $colour);
}

/**
 * Locate a TrueType font, or null if none is available.
 *
 * Checks the bundled font first so output is identical everywhere, then a few
 * common system paths. Deliberately does not fail hard: see share_image_text().
 */
function share_image_font(): ?string
{
    static $resolved = false;
    static $path = null;

    if ($resolved) {
        return $path;
    }
    $resolved = true;

    $candidates = [
        __DIR__ . '/../../public/assets/fonts/share.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $path = $candidate;
            return $path;
        }
    }

    return null;
}

/**
 * Build the share card and write it to disk.
 *
 * The photo is scaled to cover the image area and centre-cropped, so the
 * subject stays central rather than being squashed to fit a portrait frame.
 *
 * @param array $meta ['site_name' => string, 'number' => ?string, 'class' => ?string]
 * @return bool True when a file was written.
 */
function generate_share_image(string $publicToken, array $meta): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $sourcePath = share_image_source_path($publicToken);
    if ($sourcePath === null) {
        return false;
    }

    $source = @imagecreatefromjpeg($sourcePath);
    if (!$source) {
        error_log('share image: could not decode ' . $sourcePath);
        return false;
    }

    $canvas = imagecreatetruecolor(SHARE_IMAGE_WIDTH, SHARE_IMAGE_HEIGHT);

    $ink = imagecolorallocate($canvas, 17, 17, 17);
    $paper = imagecolorallocate($canvas, 255, 255, 255);
    $muted = imagecolorallocate($canvas, 130, 130, 130);

    imagefilledrectangle($canvas, 0, 0, SHARE_IMAGE_WIDTH, SHARE_IMAGE_HEIGHT, $ink);

    // --- Photo area: scale to cover, then centre-crop -----------------------
    $areaHeight = SHARE_IMAGE_HEIGHT - SHARE_IMAGE_FOOTER_HEIGHT;
    $srcW = imagesx($source);
    $srcH = imagesy($source);

    $scale = max(SHARE_IMAGE_WIDTH / $srcW, $areaHeight / $srcH);
    $scaledW = (int) ceil($srcW * $scale);
    $scaledH = (int) ceil($srcH * $scale);
    $offsetX = (int) (($scaledW - SHARE_IMAGE_WIDTH) / 2);
    $offsetY = (int) (($scaledH - $areaHeight) / 2);

    imagecopyresampled(
        $canvas, $source,
        -$offsetX, -$offsetY,
        0, 0,
        $scaledW, $scaledH,
        $srcW, $srcH
    );

    // --- Footer bar ---------------------------------------------------------
    imagefilledrectangle($canvas, 0, $areaHeight, SHARE_IMAGE_WIDTH, SHARE_IMAGE_HEIGHT, $ink);

    $textBaseline = $areaHeight + 78;

    // Identity: number and class only, never a name.
    $identity = '';
    if (!empty($meta['number'])) {
        $identity = '#' . $meta['number'];
    }
    if (!empty($meta['class'])) {
        $identity = $identity === '' ? (string) $meta['class'] : $identity . '  ' . $meta['class'];
    }

    if ($identity !== '') {
        share_image_text($canvas, $identity, 60, $textBaseline, 46, $paper);
        share_image_text($canvas, (string) $meta['site_name'], 60, $textBaseline + 58, 26, $muted);
    } else {
        // No entry-list data for this photo, so the card is branding only.
        share_image_text($canvas, (string) $meta['site_name'], 60, $textBaseline + 20, 40, $paper);
    }

    // Right-hand mark, so a cropped repost still carries attribution.
    share_image_text($canvas, 'Full photo at', SHARE_IMAGE_WIDTH - 60, $textBaseline, 22, $muted, true);
    share_image_text($canvas, (string) ($meta['domain'] ?? ''), SHARE_IMAGE_WIDTH - 60, $textBaseline + 42, 28, $paper, true);

    $dir = dirname(share_image_path($publicToken));
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('share image: cannot create ' . $dir);
        return false;
    }

    $ok = imagejpeg($canvas, share_image_path($publicToken), 88);

    return (bool) $ok;
}

/**
 * Everything the card needs about one photo, or null if it is not shareable.
 *
 * Only live photos qualify, so an unpublished or failed upload cannot be turned
 * into a shareable card. The entrant lookup is a LEFT JOIN because most photos
 * will not be attributed yet, and an unattributed photo still deserves a
 * branded card.
 *
 * @return array{public_token:string, number:?string, class:?string}|null
 */
function fetch_share_image_meta(PDO $pdo, string $publicToken): ?array
{
    $stmt = $pdo->prepare(
        "SELECT p.public_token,
                e.number AS number,
                c.name   AS class_name
           FROM photos p
           LEFT JOIN photo_entrants pe
                  ON pe.photo_id = p.id AND pe.confidence >= 0.75
           LEFT JOIN entrants e ON e.id = pe.entrant_id
           LEFT JOIN classes  c ON c.id = e.class_id
          WHERE p.public_token = ?
            AND p.status = 'live'
            AND p.media_type = 'photo'
          LIMIT 1"
    );
    $stmt->execute([$publicToken]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return null;
    }

    return [
        'public_token' => (string) $row['public_token'],
        'number' => $row['number'] !== null ? (string) $row['number'] : null,
        'class' => $row['class_name'] !== null ? (string) $row['class_name'] : null,
    ];
}

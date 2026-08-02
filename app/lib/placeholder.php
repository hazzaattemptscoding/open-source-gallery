<?php
/**
 * Placeholder image generation.
 *
 * Two callers, one shared renderer:
 *
 *  - The dev seeder, which previously inserted photo rows with no image files
 *    behind them. Every gallery tile then requested a derivative that could not
 *    exist, so a freshly seeded install 404'd on all ~111 photos and looked
 *    broken rather than empty.
 *
 *  - The /media/d/ fallback route, for the real production window between a
 *    photo being uploaded and cron generating its derivatives. Falling through
 *    to the HTML 404 page in that window sends a text/html body to an <img>,
 *    which the browser renders as a broken image. A grey tile is honest and
 *    costs nothing.
 *
 * Deliberately GD-only. Imagick is optional on shared hosting, and a
 * placeholder that itself depends on an optional extension defeats the point.
 */

declare(strict_types=1);

/**
 * Deterministic muted colour derived from a seed string.
 *
 * Deterministic matters: the same photo token keeps the same placeholder
 * colour across requests and across regeneration, so a gallery of placeholders
 * looks like a set of distinct photographs rather than flickering noise.
 *
 * Saturation and lightness are held low so a wall of these reads as a neutral
 * contact sheet and never competes with real photography sitting beside it.
 *
 * @return array{0:int,1:int,2:int} RGB
 */
function placeholder_colour(string $seed): array
{
    $hash = crc32($seed);
    $hue = ($hash % 360) / 360;

    // HSL -> RGB at S=0.14, L=0.42. Fixed low chroma, per the note above.
    $s = 0.14;
    $l = 0.42;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($hue * 6, 2) - 1));
    $m = $l - $c / 2;

    $sector = (int) floor($hue * 6);
    [$r, $g, $b] = match ($sector) {
        0 => [$c, $x, 0.0],
        1 => [$x, $c, 0.0],
        2 => [0.0, $c, $x],
        3 => [0.0, $x, $c],
        4 => [$x, 0.0, $c],
        default => [$c, 0.0, $x],
    };

    return [
        (int) round(($r + $m) * 255),
        (int) round(($g + $m) * 255),
        (int) round(($b + $m) * 255),
    ];
}

/**
 * Render a placeholder image.
 *
 * The diagonal band is there purely so the result is obviously synthetic at a
 * glance. A flat rectangle is too easy to mistake for a real photograph that
 * failed to load, which is exactly the confusion this is meant to remove.
 *
 * @param string $label Drawn centred when it fits; omitted on small sizes
 *                      where it would be illegible anyway.
 */
function render_placeholder_image(int $width, int $height, string $seed, string $label = ''): GdImage
{
    $img = imagecreatetruecolor($width, $height);

    [$r, $g, $b] = placeholder_colour($seed);
    imagefilledrectangle($img, 0, 0, $width, $height, imagecolorallocate($img, $r, $g, $b));

    // Slightly lighter diagonal band.
    $band = imagecolorallocate(
        $img,
        min(255, (int) round($r * 1.18)),
        min(255, (int) round($g * 1.18)),
        min(255, (int) round($b * 1.18))
    );
    $points = [
        0, (int) ($height * 0.62),
        (int) ($width * 0.55), 0,
        (int) ($width * 0.78), 0,
        0, (int) ($height * 0.92),
    ];
    imagefilledpolygon($img, $points, $band);

    if ($label !== '' && $width >= 320) {
        $font = 3;
        $textWidth = imagefontwidth($font) * strlen($label);
        if ($textWidth < $width * 0.8) {
            $ink = imagecolorallocatealpha($img, 255, 255, 255, 40);
            imagestring(
                $img,
                $font,
                (int) (($width - $textWidth) / 2),
                (int) (($height - imagefontheight($font)) / 2),
                $label,
                $ink
            );
        }
    }

    return $img;
}

/**
 * Write a placeholder JPEG to disk, creating the directory if needed.
 * Returns the bytes written, or 0 on failure.
 */
function write_placeholder_jpeg(string $path, int $width, int $height, string $seed, string $label = ''): int
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log("placeholder: cannot create directory {$dir}");
        return 0;
    }

    $img = render_placeholder_image($width, $height, $seed, $label);
    $ok = imagejpeg($img, $path, 82);

    if (!$ok) {
        error_log("placeholder: imagejpeg failed for {$path}");
        return 0;
    }

    return (int) @filesize($path);
}

/**
 * Stream a placeholder JPEG as the HTTP response.
 *
 * Sends 404 rather than 200: the derivative genuinely is not there, and
 * claiming otherwise would let a missing-image bug hide behind a grey tile
 * forever. The body is still a real JPEG so the browser renders something
 * sensible instead of a broken-image icon, and the status keeps it out of
 * caches and visible in logs.
 */
function serve_placeholder_jpeg(int $width, int $height, string $seed): void
{
    if (!function_exists('imagecreatetruecolor')) {
        http_response_code(404);
        return;
    }

    $img = render_placeholder_image($width, $height, $seed, 'Processing');

    http_response_code(404);
    header('Content-Type: image/jpeg');
    header('Cache-Control: no-store, max-age=0');
    imagejpeg($img, null, 78);
}

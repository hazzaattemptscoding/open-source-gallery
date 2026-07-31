<?php
declare(strict_types=1);

/**
 * Dev-only: give seeded photo rows real image files.
 *
 * The seeder inserts photo rows but does not create image files, so a freshly
 * seeded database has photos in the gallery whose derivatives 404 — the grid,
 * hero and lightbox all render broken images and there is nothing to look at
 * when checking layout or design work.
 *
 * This script fills that gap in two steps:
 *
 *   1. Write a placeholder hires JPEG for any photo row missing its source, at
 *      the width/height recorded on the row so aspect ratios in the grid stay
 *      truthful. Photos that already have a real source file are left alone.
 *   2. Run the application's own process_derivative_job() over each photo.
 *
 * Step 2 matters: it means the 400/800/1600 derivatives are produced by the
 * real pipeline, with the real watermark settings, rather than by a shortcut
 * that could drift from what production does. It also exercises that path.
 *
 * NEVER run this against real data. It only touches rows whose source file is
 * missing, but it is a development convenience and nothing more.
 *
 * Usage:  php tools/seed_placeholder_images.php [--force]
 *         --force  regenerate derivatives even where they already exist
 */

$root = dirname(__DIR__);
$config = require $root . '/config/config.php';

require_once $root . '/app/lib/derivatives.php';

if (($config['dev_mode'] ?? 'production') === 'production') {
    fwrite(STDERR, "Refusing to run: config dev_mode is 'production'.\n");
    exit(1);
}

$force = in_array('--force', $argv, true);

$pdo = new PDO(
    'sqlite:' . $config['db']['path'],
    null,
    null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$photos = $pdo->query(
    'SELECT id, event_id, public_token, file_extension, width, height, original_filename
     FROM photos ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

/**
 * Draw a placeholder that reads as a photograph rather than a grey box: a
 * diagonal two-tone gradient with a soft vignette, so the gallery grid shows
 * varied tone and the watermark has something to sit on. The hue is derived
 * from the photo ID so adjacent thumbnails differ and the grid looks like a
 * real set rather than one image repeated.
 */
function draw_placeholder(int $width, int $height, int $seed): GdImage {
    $img = imagecreatetruecolor($width, $height);

    // Two anchor colours a fixed distance apart on the wheel, so every image is
    // a plausible photographic tone pair rather than a random clash.
    $hue = ($seed * 47) % 360;
    [$r1, $g1, $b1] = hsv_to_rgb($hue, 0.45, 0.78);
    [$r2, $g2, $b2] = hsv_to_rgb(($hue + 35) % 360, 0.60, 0.32);

    // Step in bands rather than per-pixel: a 4px band is invisible once the
    // image is downscaled to a thumbnail and is far faster on large sources.
    $band = 4;
    $span = $width + $height;
    for ($y = 0; $y < $height; $y += $band) {
        for ($x = 0; $x < $width; $x += $band) {
            $t = ($x + $y) / $span;
            $c = imagecolorallocate(
                $img,
                (int)round($r1 + ($r2 - $r1) * $t),
                (int)round($g1 + ($g2 - $g1) * $t),
                (int)round($b1 + ($b2 - $b1) * $t)
            );
            imagefilledrectangle($img, $x, $y, $x + $band - 1, $y + $band - 1, $c);
        }
    }

    // Vignette: darken toward the corners so the frame reads as a photo.
    $cx = $width / 2;
    $cy = $height / 2;
    $maxDist = sqrt($cx * $cx + $cy * $cy);
    for ($y = 0; $y < $height; $y += $band) {
        for ($x = 0; $x < $width; $x += $band) {
            $dx = $x - $cx;
            $dy = $y - $cy;
            $falloff = (sqrt($dx * $dx + $dy * $dy) / $maxDist) ** 2;
            $alpha = (int)round(min(90, $falloff * 90));
            if ($alpha > 0) {
                $shade = imagecolorallocatealpha($img, 0, 0, 0, 127 - (int)round($alpha * 127 / 100));
                imagefilledrectangle($img, $x, $y, $x + $band - 1, $y + $band - 1, $shade);
            }
        }
    }

    return $img;
}

/** @return array{0:int,1:int,2:int} */
function hsv_to_rgb(float $h, float $s, float $v): array {
    $c = $v * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $v - $c;
    [$r, $g, $b] = match (true) {
        $h < 60  => [$c, $x, 0.0],
        $h < 120 => [$x, $c, 0.0],
        $h < 180 => [0.0, $c, $x],
        $h < 240 => [0.0, $x, $c],
        $h < 300 => [$x, 0.0, $c],
        default  => [$c, 0.0, $x],
    };
    return [
        (int)round(($r + $m) * 255),
        (int)round(($g + $m) * 255),
        (int)round(($b + $m) * 255),
    ];
}

$created = 0;
$skipped = 0;
$derived = 0;
$failed  = 0;

foreach ($photos as $photo) {
    $eventId = (int)$photo['event_id'];
    $token   = (string)$photo['public_token'];
    $ext     = (string)($photo['file_extension'] ?: 'jpg');
    $dir     = $root . "/storage/hires/{$eventId}";
    $hires   = "{$dir}/{$token}.{$ext}";

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (file_exists($hires)) {
        $skipped++;
    } else {
        // Fall back to a sane 3:2 frame when the row carries no dimensions.
        $w = max(320, (int)($photo['width']  ?: 2400));
        $h = max(320, (int)($photo['height'] ?: 1600));
        $img = draw_placeholder($w, $h, (int)$photo['id']);
        imagejpeg($img, $hires, 82);
        imagedestroy($img);
        $created++;
    }

    $existing = $root . "/public/media/d/{$token}-800.jpg";
    if (!$force && file_exists($existing)) {
        continue;
    }

    if (process_derivative_job($pdo, ['photo_id' => (int)$photo['id']])) {
        $derived++;
    } else {
        $failed++;
        fwrite(STDERR, "derivative failed for photo {$photo['id']} ({$token})\n");
    }
}

printf(
    "sources: %d created, %d already present\nderivatives: %d generated, %d failed\n",
    $created,
    $skipped,
    $derived,
    $failed
);

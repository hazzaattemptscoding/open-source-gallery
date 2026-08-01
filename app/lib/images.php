<?php
declare(strict_types=1);

/**
 * Load image from file using GD, supporting both JPEG and PNG.
 */
function load_image_gd(string $inputPath) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string)finfo_file($finfo, $inputPath) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === 'image/png') {
        return imagecreatefrompng($inputPath);
    }
    return imagecreatefromjpeg($inputPath);
}

/**
 * Runtime-detects Imagick vs GD per docs/architecture.md section 1: shared
 * hosting can't be assumed to have either extension, so a build that hard-
 * depends on one is useless. Imagick is preferred when present (better
 * resampling, native progressive JPEG); GD is the universal fallback.
 */
function generate_derivative(string $inputPath, string $outputPath, int $size, bool $addWatermark, array $watermarkSettings): void {
    if (class_exists('Imagick')) {
        generate_derivative_imagick($inputPath, $outputPath, $size, $addWatermark, $watermarkSettings);
    } else {
        generate_derivative_gd($inputPath, $outputPath, $size, $addWatermark, $watermarkSettings);
    }
}

function generate_derivative_imagick(string $inputPath, string $outputPath, int $size, bool $addWatermark, array $watermarkSettings): void {
    $image = new Imagick($inputPath);
    $image->autoOrient();

    $width = $image->getImageWidth();
    $height = $image->getImageHeight();
    $smallestDim = min($width, $height);
    $longestDim = max($width, $height);
    if ($smallestDim < 100) {
        throw new RuntimeException("Image too small: {$smallestDim}px");
    }

    $scaledSize = min($longestDim, max(1, (int)($size * $longestDim / 1600)));
    $thumbWidth = (int)($width * $scaledSize / $longestDim);
    $thumbHeight = (int)($height * $scaledSize / $longestDim);

    $image->resizeImage($thumbWidth, $thumbHeight, Imagick::FILTER_LANCZOS, 1);
    $image->setImageFormat('jpeg');
    $image->setImageCompressionQuality(85);
    $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
    $image->stripImage();

    if ($addWatermark) {
        apply_watermark_imagick($image, $watermarkSettings);
    }

    $image->writeImage($outputPath);
    $image->destroy();
}

function apply_watermark_imagick(Imagick $image, array $settings): void {
    $text = (string)($settings['text'] ?? 'PREVIEW');
    if ($text === '') {
        return;
    }

    $opacity = min(1.0, max(0.0, (float)($settings['opacity'] ?? 0.35)));
    $position = (string)($settings['position'] ?? 'bottom-right');

    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('white'));
    $draw->setFillAlpha($opacity);
    $draw->setFontSize(max(12, (int)($image->getImageWidth() * 0.03)));

    $metrics = $image->queryFontMetrics($draw, $text);
    $textWidth = (int)$metrics['textWidth'];
    $textHeight = (int)$metrics['textHeight'];

    [$x, $y] = watermark_xy($image->getImageWidth(), $image->getImageHeight(), $textWidth, $textHeight, $position);
    $draw->annotation($x, $y + $textHeight, $text);
    $image->drawImage($draw);
}

function generate_derivative_gd(string $inputPath, string $outputPath, int $size, bool $addWatermark, array $watermarkSettings): void {
    $image = load_image_gd($inputPath);
    if (!$image) {
        throw new RuntimeException("Failed to load image: {$inputPath}");
    }

    $width = imagesx($image);
    $height = imagesy($image);

    $smallestDim = min($width, $height);
    $longestDim = max($width, $height);
    if ($smallestDim < 100) {
        imagedestroy($image);
        throw new RuntimeException("Image too small: {$smallestDim}px");
    }

    $scaledSize = min($longestDim, max(1, (int)($size * $longestDim / 1600)));
    $thumbWidth = (int)($width * $scaledSize / $longestDim);
    $thumbHeight = (int)($height * $scaledSize / $longestDim);

    $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
    if (!$thumb) {
        imagedestroy($image);
        throw new RuntimeException("Failed to create thumbnail");
    }

    if (!imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height)) {
        imagedestroy($image);
        imagedestroy($thumb);
        throw new RuntimeException("Failed to resample image");
    }

    imagedestroy($image);

    if ($addWatermark) {
        apply_watermark_gd($thumb, $watermarkSettings);
    }

    imageinterlace($thumb, true); // progressive JPEG, no external binary needed

    if (!imagejpeg($thumb, $outputPath, 85)) {
        imagedestroy($thumb);
        throw new RuntimeException("Failed to save JPEG");
    }

    imagedestroy($thumb);
}

function apply_watermark_gd(GdImage $image, array $settings): void {
    $text = (string)($settings['text'] ?? 'PREVIEW');
    if ($text === '') {
        return;
    }

    $opacity = min(1.0, max(0.0, (float)($settings['opacity'] ?? 0.35)));
    $position = (string)($settings['position'] ?? 'bottom-right');

    $alpha = (int)((1 - $opacity) * 127);
    $white = imagecolorallocatealpha($image, 255, 255, 255, $alpha);

    $fontSize = 5;
    $textWidth = strlen($text) * imagefontwidth($fontSize);
    $textHeight = imagefontheight($fontSize);

    [$x, $y] = watermark_xy(imagesx($image), imagesy($image), $textWidth, $textHeight, $position);
    imagestring($image, $fontSize, $x, $y, $text, $white);
}

/**
 * Shared corner-placement math for both backends: keeps the four position
 * options (docs/architecture.md's watermark_position setting) consistent
 * whichever image library actually drew the pixels.
 */
function watermark_xy(int $imgWidth, int $imgHeight, int $textWidth, int $textHeight, string $position): array {
    $margin = (int)($imgWidth * 0.02);

    switch ($position) {
        case 'bottom-left':
            $x = $margin;
            $y = $imgHeight - $textHeight - $margin;
            break;
        case 'top-right':
            $x = $imgWidth - $textWidth - $margin;
            $y = $margin;
            break;
        case 'top-left':
            $x = $margin;
            $y = $margin;
            break;
        case 'bottom-right':
        default:
            $x = $imgWidth - $textWidth - $margin;
            $y = $imgHeight - $textHeight - $margin;
            break;
    }

    return [max(0, $x), max(0, $y)];
}

<?php
declare(strict_types=1);

function generate_derivative(string $inputPath, string $outputPath, int $size, bool $addWatermark, array $watermarkSettings): void {
    $image = imagecreatefromjpeg($inputPath);
    if (!$image) {
        throw new RuntimeException("Failed to load image: {$inputPath}");
    }

    $width = imagesx($image);
    $height = imagesy($image);

    $smallestDim = min($width, $height);
    if ($smallestDim < 100) {
        imagedestroy($image);
        throw new RuntimeException("Image too small: {$smallestDim}px");
    }

    $scaledSize = max(1, (int)($size * $smallestDim / 1600));
    $scaledSize = min($scaledSize, $smallestDim);

    $thumbWidth = (int)($width * $scaledSize / $smallestDim);
    $thumbHeight = (int)($height * $scaledSize / $smallestDim);

    $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
    if (!$thumb) {
        imagedestroy($image);
        throw new RuntimeException("Failed to create thumbnail");
    }

    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);

    if (!imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height)) {
        imagedestroy($image);
        imagedestroy($thumb);
        throw new RuntimeException("Failed to resample image");
    }

    imagedestroy($image);

    if ($addWatermark) {
        apply_watermark($thumb, $watermarkSettings);
    }

    $tmp = $outputPath . '.tmp';
    if (!imagejpeg($thumb, $tmp, 85)) {
        imagedestroy($thumb);
        @unlink($tmp);
        throw new RuntimeException("Failed to save JPEG");
    }

    imagedestroy($thumb);

    if (!make_progressive_jpeg($tmp, $outputPath)) {
        @unlink($tmp);
        throw new RuntimeException("Failed to create progressive JPEG");
    }

    @unlink($tmp);
}

function apply_watermark(GdImage $image, array $settings): void {
    $text = (string)($settings['text'] ?? 'WATERMARK');
    if (!$text) {
        return;
    }

    $opacity = min(1.0, max(0.0, (float)($settings['opacity'] ?? 0.3)));
    $position = (string)($settings['position'] ?? 'bottom-right');

    $alpha = (int)((1 - $opacity) * 127);
    $white = imagecolorallocatealpha($image, 255, 255, 255, $alpha);

    $fontSize = 5;
    $textWidth = strlen($text) * imagefontwidth($fontSize);
    $textHeight = imagefontheight($fontSize);

    $imgWidth = imagesx($image);
    $imgHeight = imagesy($image);
    $margin = (int)($imgWidth * 0.02);

    switch ($position) {
        case 'bottom-right':
            $x = $imgWidth - $textWidth - $margin;
            $y = $imgHeight - $textHeight - $margin;
            break;
        case 'bottom-left':
            $x = $margin;
            $y = $imgHeight - $textHeight - $margin;
            break;
        case 'top-right':
            $x = $imgWidth - $textWidth - $margin;
            $y = $margin;
            break;
        case 'top-left':
        default:
            $x = $margin;
            $y = $margin;
            break;
    }

    $x = max(0, $x);
    $y = max(0, $y);

    imagestring($image, $fontSize, $x, $y, $text, $white);
}

function make_progressive_jpeg(string $inputPath, string $outputPath): bool {
    if (!extension_loaded('exif')) {
        return @rename($inputPath, $outputPath);
    }

    $tmpOutput = $outputPath . '.progressive';
    $command = escapeshellarg($inputPath) . ' -interlace Plane ' . escapeshellarg($tmpOutput);

    if (!extension_loaded('imagick') && function_exists('shell_exec')) {
        @shell_exec("convert {$command} 2>/dev/null");
        if (file_exists($tmpOutput)) {
            return @rename($tmpOutput, $outputPath);
        }
    }

    return @rename($inputPath, $outputPath);
}

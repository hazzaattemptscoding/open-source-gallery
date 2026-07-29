<?php
/**
 * Photo metadata: EXIF, technical specs, camera info.
 */

declare(strict_types=1);

/**
 * Extract EXIF metadata from photo file.
 */
function extract_exif_metadata(string $filepath): ?array {
    if (!extension_loaded('exif')) {
        return null;
    }

    try {
        $exif = @exif_read_data($filepath);
        if (!$exif) {
            return null;
        }

        return [
            'make' => $exif['Make'] ?? null,
            'model' => $exif['Model'] ?? null,
            'date' => $exif['DateTime'] ?? null,
            'focal_length' => $exif['FocalLength'] ?? null,
            'f_number' => $exif['FNumber'] ?? null,
            'iso' => $exif['ISOSpeedRatings'] ?? null,
            'shutter_speed' => $exif['ExposureTime'] ?? null,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Store metadata for a photo.
 */
function save_photo_metadata(PDO $pdo, int $photoId, array $metadata): bool {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            UPDATE photos
            SET camera_make = ?, camera_model = ?, lens = ?, focal_length = ?,
                aperture = ?, shutter_speed = ?, iso = ?, taken_at = ?,
                exif_data = ?
            WHERE id = ?
        SQL);

        $date = null;
        if (!empty($metadata['date'])) {
            $date = DateTime::createFromFormat('Y:m:d H:i:s', $metadata['date'])?->format('Y-m-d H:i:s');
        }

        return $stmt->execute([
            $metadata['make'] ?? null,
            $metadata['model'] ?? null,
            $metadata['lens'] ?? null,
            $metadata['focal_length'] ?? null,
            $metadata['f_number'] ?? null,
            $metadata['shutter_speed'] ?? null,
            is_numeric($metadata['iso'] ?? null) ? (int)$metadata['iso'] : null,
            $date,
            !empty($metadata) ? json_encode($metadata) : null,
            $photoId,
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get photo metadata.
 */
function get_photo_metadata(PDO $pdo, int $photoId): ?array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT camera_make, camera_model, lens, focal_length, aperture,
                   shutter_speed, iso, taken_at, exif_data
            FROM photos WHERE id = ?
        SQL);
        $stmt->execute([$photoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get photos by camera.
 */
function get_photos_by_camera(PDO $pdo, string $make, string $model): array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT id, original_filename, camera_make, camera_model, taken_at
            FROM photos
            WHERE camera_make = ? AND camera_model = ? AND status = 'live'
            ORDER BY taken_at DESC
        SQL);
        $stmt->execute([$make, $model]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

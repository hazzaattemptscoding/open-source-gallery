<?php
declare(strict_types=1);

/**
 * Driver visibility controls — photographers can hide driver names from public views
 * to protect privacy. Valid visibility tiers: 'hidden', 'initials', 'full'.
 */

/**
 * Redact a driver name based on the photographer's visibility preference.
 *
 * @param string|null $driverName The full driver name from photo_tags
 * @param string $visibility One of: 'hidden', 'initials', 'full'
 * @return string|null Redacted name, or null if hidden or name is empty
 */
function redact_driver_name(?string $driverName, string $visibility): ?string {
    if (empty($driverName) || $visibility === 'hidden') {
        return null;
    }

    if ($visibility === 'full') {
        return $driverName;
    }

    // 'initials': convert "John Smith" → "J.S."
    if ($visibility === 'initials') {
        $parts = array_filter(explode(' ', trim((string)$driverName)));
        if (empty($parts)) {
            return null;
        }
        return implode('.', array_map(fn($p) => strtoupper($p[0]), $parts)) . '.';
    }

    return null;
}

/**
 * Load driver visibility settings for an event.
 * Returns array keyed by driver_name (exact match from event_entries).
 *
 * @return array<string, string> ['John Smith' => 'initials', ...]
 */
function load_driver_visibility(PDO $pdo, int $eventId): array {
    $stmt = $pdo->prepare('
        SELECT DISTINCT driver_name, drivers_visibility
        FROM event_entries
        WHERE event_id = ? AND driver_name <> \'\'
    ');
    $stmt->execute([$eventId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $visibility = [];
    foreach ($rows as $row) {
        $visibility[$row['driver_name']] = $row['drivers_visibility'];
    }
    return $visibility;
}

/**
 * Redact a comma-separated list of driver names (as returned by GROUP_CONCAT).
 *
 * @param string|null $driverTags Comma-separated names from GROUP_CONCAT
 * @param array<string, string> $visibility Visibility mapping from load_driver_visibility()
 * @return string|null Comma-separated redacted names, or null if all hidden
 */
function redact_driver_tags(?string $driverTags, array $visibility): ?string {
    if (empty($driverTags)) {
        return null;
    }

    $names = array_filter(explode(',', $driverTags));
    $redacted = [];

    foreach ($names as $name) {
        $name = trim($name);
        $vis = $visibility[$name] ?? 'hidden';
        $redactedName = redact_driver_name($name, $vis);
        if ($redactedName !== null) {
            $redacted[] = $redactedName;
        }
    }

    return !empty($redacted) ? implode(', ', $redacted) : null;
}

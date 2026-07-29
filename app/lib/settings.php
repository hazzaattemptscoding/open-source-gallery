<?php
/**
 * Settings management: read, write, cache database settings.
 * All user-facing configuration lives here, organized by category.
 */

declare(strict_types=1);

/**
 * Get all settings, optionally filtered by category.
 */
function get_all_settings(PDO $pdo, string $category = ''): array {
    try {
        $sql = 'SELECT * FROM settings';
        $params = [];

        if (!empty($category)) {
            $sql .= ' WHERE category = ?';
            $params[] = $category;
        }

        $sql .= ' ORDER BY category, order_by ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get setting by key.
 */
function get_setting(PDO $pdo, string $category, string $key, mixed $default = null): mixed {
    try {
        $stmt = $pdo->prepare('SELECT value, type FROM settings WHERE category = ? AND key_name = ?');
        $stmt->execute([$category, $key]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$setting) {
            return $default;
        }

        return cast_setting_value($setting['value'], $setting['type']);
    } catch (Throwable $e) {
        return $default;
    }
}

/**
 * Set setting value.
 */
function set_setting(PDO $pdo, string $category, string $key, mixed $value): bool {
    try {
        $stringValue = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;

        $stmt = $pdo->prepare(<<<'SQL'
            UPDATE settings SET value = ? WHERE category = ? AND key_name = ?
        SQL);

        return $stmt->execute([$stringValue, $category, $key]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get all settings grouped by category.
 */
function get_settings_by_category(PDO $pdo, bool $includeAdvanced = false): array {
    try {
        $sql = 'SELECT * FROM settings';
        $params = [];

        if (!$includeAdvanced) {
            $sql .= ' WHERE is_advanced = 0';
        }

        $sql .= ' ORDER BY category, order_by ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($settings as $setting) {
            $cat = $setting['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $setting;
        }

        return $grouped;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Cast setting value to correct type.
 */
function cast_setting_value(string $value, string $type): mixed {
    return match ($type) {
        'integer' => (int)$value,
        'boolean' => $value === '1' || $value === 'true',
        'json' => json_decode($value, true) ?? [],
        default => $value,
    };
}

/**
 * Validate setting value before saving.
 */
function validate_setting(PDO $pdo, string $category, string $key, mixed $value): array {
    $errors = [];

    try {
        $stmt = $pdo->prepare('SELECT type, required FROM settings WHERE category = ? AND key_name = ?');
        $stmt->execute([$category, $key]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$setting) {
            $errors[] = "Unknown setting: $category.$key";
            return $errors;
        }

        if ($setting['required'] && empty($value)) {
            $errors[] = "This setting is required";
        }

        switch ($setting['type']) {
            case 'integer':
                if (!is_numeric($value)) {
                    $errors[] = "Must be a number";
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email address";
                }
                break;
        }
    } catch (Throwable $e) {
        $errors[] = "Validation error";
    }

    return $errors;
}

/**
 * Batch update settings (for form submissions).
 */
function batch_update_settings(PDO $pdo, array $updates, string $category): int {
    $updated = 0;

    foreach ($updates as $key => $value) {
        $errors = validate_setting($pdo, $category, $key, $value);
        if (empty($errors) && set_setting($pdo, $category, $key, $value)) {
            $updated++;
        }
    }

    return $updated;
}

/**
 * Get all setting categories for navigation.
 */
function get_setting_categories(PDO $pdo): array {
    try {
        $stmt = $pdo->query('SELECT DISTINCT category FROM settings ORDER BY category');
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get human-readable category name.
 */
function get_category_label(string $category): string {
    return match ($category) {
        'site' => 'Site Settings',
        'currency' => 'Currency',
        'email' => 'Email',
        'stripe' => 'Payment (Stripe)',
        'photos' => 'Photos',
        'print' => 'Print Fulfillment',
        'search' => 'Search',
        'api' => 'API',
        'security' => 'Security',
        default => ucwords(str_replace('_', ' ', $category)),
    };
}

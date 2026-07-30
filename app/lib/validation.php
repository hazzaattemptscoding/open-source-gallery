<?php
/**
 * Input validation helpers.
 * Centralized validators for common input types.
 */

declare(strict_types=1);

/**
 * Validate pagination page number.
 *
 * @param mixed $page User input (should be int-like)
 * @param int $maxPage Maximum allowed page number (default 1000)
 * @return int Valid page number (minimum 1)
 */
function validate_page(mixed $page, int $maxPage = 1000): int {
    $page = (int)$page;
    return max(1, min($page, $maxPage));
}

/**
 * Validate pagination limit/page size.
 *
 * @param mixed $limit User input
 * @param int $maxLimit Maximum allowed (default 100)
 * @return int Valid limit (minimum 1, maximum $maxLimit)
 */
function validate_limit(mixed $limit, int $maxLimit = 100): int {
    $limit = (int)$limit;
    return max(1, min($limit, $maxLimit));
}

/**
 * Validate integer in range.
 *
 * @param mixed $value User input
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return int|null Valid value if in range, null otherwise
 */
function validate_int_range(mixed $value, int $min, int $max): ?int {
    $value = (int)$value;
    return ($value >= $min && $value <= $max) ? $value : null;
}

/**
 * Validate ISO date format (YYYY-MM-DD).
 *
 * @param mixed $date User input
 * @return string|null Valid date string if valid, null otherwise
 */
function validate_iso_date(mixed $date): ?string {
    $date = (string)$date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }

    // Check if date is valid
    $parts = explode('-', $date);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        return null;
    }

    return $date;
}

/**
 * Validate email format.
 *
 * @param mixed $email User input
 * @return string|null Valid email if valid, null otherwise
 */
function validate_email(mixed $email): ?string {
    $email = (string)$email;
    return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
}

/**
 * Validate email format, allowing localhost addresses in dev mode.
 * In dev mode, allows admin@localhost, admin@127.0.0.1, etc.
 * In production, uses standard email validation only.
 *
 * @param mixed $email User input
 * @param array $config Application config with 'dev_mode' key
 * @return string|null Valid email if valid, null otherwise
 */
function validate_email_for_mode(mixed $email, array $config): ?string {
    $email = (string)$email;

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    if ($config['dev_mode'] === 'local') {
        if (preg_match('/^[a-z0-9._+-]+@(localhost|127\.0\.0\.1)$/i', $email)) {
            return $email;
        }
    }

    return null;
}

/**
 * Validate URL format.
 *
 * @param mixed $url User input
 * @return string|null Valid URL if valid, null otherwise
 */
function validate_url(mixed $url): ?string {
    $url = (string)$url;
    return filter_var($url, FILTER_VALIDATE_URL) ?: null;
}

/**
 * Validate string length.
 *
 * @param mixed $str User input
 * @param int $minLen Minimum length
 * @param int $maxLen Maximum length
 * @return string|null Valid string if within range, null otherwise
 */
function validate_string_length(mixed $str, int $minLen = 0, int $maxLen = 255): ?string {
    $str = (string)$str;
    $len = strlen($str);
    return ($len >= $minLen && $len <= $maxLen) ? $str : null;
}

/**
 * Validate against allowed values (enum-like).
 *
 * @param mixed $value User input
 * @param array $allowed Allowed values
 * @return string|null Valid value if in allowed list, null otherwise
 */
function validate_enum(mixed $value, array $allowed): ?string {
    $value = (string)$value;
    return in_array($value, $allowed, true) ? $value : null;
}

/**
 * Sanitize string for output (basic XSS prevention).
 * For HTML context. Use htmlspecialchars() for most cases.
 *
 * @param mixed $str User input
 * @return string Sanitized string
 */
function sanitize_string(mixed $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize string for HTML attributes.
 *
 * @param mixed $str User input
 * @return string Sanitized string
 */
function sanitize_attribute(mixed $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize string for JSON output.
 *
 * @param mixed $str User input
 * @return string Sanitized string
 */
function sanitize_json(mixed $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_JSON, 'UTF-8');
}
?>

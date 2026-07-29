<?php
/**
 * Auto-generate config/config.php from environment variables if it doesn't exist.
 * Used in Docker to avoid requiring manual config copy/edit. On shared hosting,
 * config.php is created once by the admin and then never touched.
 */

$configPath = __DIR__ . '/../config/config.php';

if (file_exists($configPath)) {
    return; // Config already exists, use it as-is
}

// Generate from environment variables (Docker Compose sets these)
$config = [
    'db' => [
        'host'    => getenv('MYSQL_HOST') ?: '127.0.0.1',
        'port'    => (int)(getenv('MYSQL_PORT') ?: 3306),
        'name'    => getenv('MYSQL_DATABASE') ?: 'photo_gallery',
        'user'    => getenv('MYSQL_USER') ?: 'gallery',
        'pass'    => getenv('MYSQL_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name'          => getenv('GALLERY_NAME') ?: 'Photo Gallery',
        'base_url'      => getenv('GALLERY_BASE_URL') ?: 'https://example.com',
        'support_email' => getenv('GALLERY_SUPPORT_EMAIL') ?: 'support@example.com',
    ],
    'currency' => getenv('GALLERY_CURRENCY') ?: 'GBP',
    'branding' => ['theme' => 'podium-ink'],
    'stripe' => [
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
        'secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
        'webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
    ],
    'smtp' => [
        'host'       => getenv('SMTP_HOST') ?: '',
        'port'       => (int)(getenv('SMTP_PORT') ?: 587),
        'user'       => getenv('SMTP_USER') ?: '',
        'pass'       => getenv('SMTP_PASS') ?: '',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: '',
        'from_name'  => getenv('SMTP_FROM_NAME') ?: '',
    ],
    'discounts' => [
        10 => 0.15,
        20 => 0.20,
        50 => 0.25,
    ],
    'security' => [
        'hmac_key'    => getenv('HMAC_KEY') ?: bin2hex(random_bytes(32)),
        'cron_secret' => getenv('CRON_SECRET') ?: bin2hex(random_bytes(32)),
    ],
    'timezone' => getenv('TIMEZONE') ?: 'UTC',
];

// Ensure directory exists
$configDir = dirname($configPath);
if (!is_dir($configDir)) {
    if (!@mkdir($configDir, 0755, true)) {
        fwrite(STDERR, "ERROR: Could not create {$configDir}. Check directory permissions.\n");
        exit(1);
    }
}

// Check directory is writable
if (!is_writable($configDir)) {
    fwrite(STDERR, "ERROR: Directory {$configDir} is not writable. Fix permissions with: chmod 755 {$configDir}\n");
    exit(1);
}

// Write config file
$configCode = "<?php\nreturn " . var_export($config, true) . ";\n";
if (!@file_put_contents($configPath, $configCode)) {
    fwrite(STDERR, "ERROR: Could not write {$configPath}. Check file permissions.\n");
    exit(1);
}

if (!@chmod($configPath, 0600)) {
    fwrite(STDERR, "WARNING: Could not set permissions on {$configPath}. File may be readable by other users.\n");
}

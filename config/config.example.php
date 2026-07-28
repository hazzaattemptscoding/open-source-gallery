<?php
/**
 * Config template. Copy this file to config.php and fill in real values.
 * config.php is gitignored and must never be committed: it holds live
 * secrets (DB password, Stripe keys, SMTP credentials, signing keys).
 *
 * This is the one place business-specific values live, per project rule:
 * nothing about any particular self-hoster's business belongs in app/ code.
 * A fresh self-hoster only ever has to edit this file.
 *
 * Some sections (stripe, smtp) aren't read by any code yet — they're wired
 * up in later build stages (cart/checkout, delivery emails) — but the file
 * ships with its full shape now so the format doesn't change under anyone
 * partway through setup.
 */

return [
    // MySQL/MariaDB connection. Matches whatever the host's control panel gives you.
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'gallery',
        'user'    => 'gallery',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Shown in page titles, admin emails, and the login screen.
    'site' => [
        'name'          => 'Your Gallery Name',
        'base_url'      => 'https://example.com',
        'support_email' => 'you@example.com',
    ],

    // ISO 4217 code. Stripe amounts and all displayed prices use this.
    'currency' => 'GBP',

    // Theme swap point. "podium-ink" is the shipped default (see docs/architecture.md).
    // A different theme is a different value here plus a matching CSS file in
    // public/assets/css/ — nothing in app/ code branches on theme name.
    'branding' => [
        'theme' => 'podium-ink',
    ],

    // Populated in the Stripe checkout build stage. Leave blank until then.
    'stripe' => [
        'publishable_key' => '',
        'secret_key'      => '',
        'webhook_secret'  => '',
    ],

    // Populated in the delivery build stage (receipts, download links).
    'smtp' => [
        'host'      => '',
        'port'      => 587,
        'user'      => '',
        'pass'      => '',
        'from_email' => '',
        'from_name'  => '',
    ],

    // Volume discounts: auto-applied at checkout based on number of photos.
    // Example: 10+ photos = 15% off, 20+ photos = 20% off.
    // Discount is calculated at checkout time from actual cart contents;
    // cannot be reverse-engineered by adding items then removing them.
    'discounts' => [
        10 => 0.15,  // 10+ photos: 15% off
        20 => 0.20,  // 20+ photos: 20% off
        50 => 0.25,  // 50+ photos: 25% off
    ],

    'security' => [
        // Random 32+ byte string. Signs the cart cookie and per-file download
        // URLs (see docs/architecture.md sections 4 and 6). Generate with:
        // php -r "echo bin2hex(random_bytes(32));"
        'hmac_key' => '',

        // URL path segment for the HTTP cron fallback: GET /cron/{this value}.
        // Only needed if the host's cron is URL-invoked rather than CLI.
        'cron_secret' => '',
    ],

    'timezone' => 'UTC',
];

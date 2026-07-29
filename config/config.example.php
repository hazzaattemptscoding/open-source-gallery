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

    // Email configuration. Leave host empty to use mail() (shared hosting default).
    // For SMTP, fill in credentials. See docs/EMAIL.md for providers (Gmail, SendGrid, etc).
    // If left empty, the server's mail() function is used (most shared hosts support this).
    'smtp' => [
        'host'       => '',              // 'smtp.gmail.com', 'smtp.sendgrid.net', etc. Leave empty for mail()
        'port'       => 587,             // 587 (STARTTLS) or 465 (SSL/TLS)
        'user'       => '',              // SMTP username (for Gmail, your email@gmail.com)
        'pass'       => '',              // SMTP password (for Gmail, use App Password, not account password!)
        'from_email' => 'noreply@example.com',  // Sender email (use your domain)
        'from_name'  => 'Your Gallery',  // Sender name (shown in customer's email client)
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

    // File storage mode. Can be 'local' or 'remote-nas'.
    // 'local' (default): all files including originals stored on this server.
    //   Standard shared hosting setup, no additional hardware needed.
    // 'remote-nas': originals stored on home NAS, previews cached on this server.
    //   Advanced setup for users with home network + always-on poller machine.
    //   See docs/NAS-FULFILLMENT.md for detailed setup instructions.
    'storage_mode' => 'local',

    // Admin deployment mode. Can be 'local' or 'remote'.
    // 'local' (default): admin panel runs on the same server as the public site.
    //   Everything works as it does today, no additional configuration needed.
    // 'remote': admin panel runs on a separate server (e.g., home machine, local dev)
    //   and connects to production database and files over the network.
    //   Requires remote MySQL access (not all shared hosts allow this) and SFTP access.
    //   See INSTALL.md for detailed setup instructions.
    'admin_mode' => 'local',

    // When admin_mode is 'remote', this section configures the admin panel's
    // connection to the production database and file storage.
    // Ignored when admin_mode is 'local' (the admin panel uses the same db config above).
    'admin_remote' => [
        // Production database credentials (for admin to connect to from remote).
        // These can be different from the 'db' section if your host supports
        // restricted remote MySQL connections (e.g., by IP address).
        // Requires the hosting provider to allow remote connections.
        'db_host' => '192.0.2.1',     // Production server's IP or hostname
        'db_port' => 3306,
        'db_name' => 'gallery',
        'db_user' => 'gallery_remote',
        'db_pass' => '',

        // SFTP connection to production server for file uploads/management.
        // Uses phpseclib library (pure PHP, no ssh2 extension needed).
        'sftp_host'      => '192.0.2.1',     // Production server's IP or hostname
        'sftp_port'      => 22,
        'sftp_user'      => 'gallery',
        'sftp_pass'      => '',
        'sftp_key_file'  => '',               // Alternative to password: path to private key file
        'storage_path'   => '/home/gallery/storage',  // Absolute path to storage on production server
    ],
];

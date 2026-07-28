# Installation Guide

Self-hosted photo gallery and sales platform for sports photographers.

## Prerequisites

- **PHP 8.2+** with `exif`, `gd`, and `zip` extensions
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache 2.4+** with `mod_rewrite`
- **Cron** support (5-minute intervals recommended)
- **Stripe API keys** for payment processing (test or live)
- **Domain** with HTTPS support (required for secure payment processing)

## Installation Steps

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/open-source-gallery.git
cd open-source-gallery
```

### 2. Configure Environment

Copy and edit the configuration template:

```bash
cp config/config.template.php config/config.php
```

Edit `config/config.php` with your settings:
- `site.name` — Your gallery name
- `site.base_url` — Public site URL (must be HTTPS)
- `database.*` — MySQL connection details
- `stripe.publishable_key` — Stripe public key
- `stripe.secret_key` — Stripe secret key
- `stripe.webhook_secret` — Stripe webhook signing secret
- `currency` — 3-letter currency code (GBP, USD, etc.)
- `security.hmac_key` — Random 32-byte key for cart signing (generate with: `php -r 'echo bin2hex(random_bytes(32));'`)
- `security.cron_secret` — Secret for cron endpoint URL (generate with: `php -r 'echo bin2hex(random_bytes(32));'`)

### 3. Create Database

```bash
mysql -u root -p -e "CREATE DATABASE photo_gallery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p photo_gallery < migrations/001_initial_schema.sql
```

Or use the migration runner:

```bash
php app/cli/migrate.php
```

### 4. Set Up Directory Permissions

Create storage directories:

```bash
mkdir -p storage/hires
mkdir -p storage/zips
chmod 755 storage
```

Ensure Apache can write to public directories:

```bash
chmod 755 public
chmod 755 public/media
chmod 755 public/media/d
```

### 5. Configure Apache

Create `.htaccess` in `public/` to route all requests through `public/index.php`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

Enable HTTPS (via Let's Encrypt or your hosting provider).

### 6. Configure Cron

Set up a 5-minute cron job to run the job drain:

```bash
*/5 * * * * php /path/to/app/cron/run.php >> /var/log/photo-gallery-cron.log 2>&1
```

Or use the URL-based fallback (if CLI cron is unavailable):

```bash
*/5 * * * * curl -s https://yoursite.com/cron/{CRON_SECRET} >> /var/log/photo-gallery-cron.log 2>&1
```

Replace `{CRON_SECRET}` with the value from `config/config.php`.

### 7. Create Admin Account

```bash
php app/cli/create-admin.php your@email.com
```

Follow the prompts to set a password and enable TOTP 2FA.

### 8. Set Up Stripe Webhooks

1. Log in to Stripe Dashboard
2. Navigate to Developers → Webhooks
3. Add webhook endpoint:
   - URL: `https://yoursite.com/webhook/stripe`
   - Events: `checkout.session.completed`, `charge.refunded`
   - Signing secret: Copy to `config/config.php` as `stripe.webhook_secret`

### 9. Test the Installation

1. Visit `https://yoursite.com/admin/login` and log in
2. Upload a test event and photo
3. Tag and publish the photo
4. Visit the gallery home page
5. Add a photo to cart and complete a test checkout (use Stripe test card 4242 4242 4242 4242)

## File Structure

```
.
├── public/                  # Web root (DocumentRoot)
│   ├── index.php           # Front controller
│   ├── .htaccess           # Apache routing rules
│   └── assets/             # CSS, JavaScript, images
├── app/
│   ├── bootstrap.php       # App initialization, config loading
│   ├── controllers/        # Route handlers
│   ├── lib/                # Shared libraries
│   ├── views/              # HTML templates
│   ├── cli/                # CLI utilities
│   └── cron/               # Cron job runner
├── migrations/             # Database schema
├── config/
│   └── config.php          # Application configuration
└── storage/
    ├── hires/              # Original uploaded photos
    └── zips/               # Pre-built download bundles (optional)
```

## Backup and Maintenance

### Database Backups

```bash
mysqldump -u user -p photo_gallery > backup-$(date +%Y%m%d).sql
```

### Storage Backups

Back up `storage/hires/` regularly; this contains all original photos.

### Log Rotation

Configure log rotation for `cron.log` and audit logs to prevent disk bloat.

## Troubleshooting

### Photos Not Showing

- Verify `public/media/d/` directory is writable
- Check cron job is running (verify `audit_log` table has recent `process_derivative_job` entries)
- Ensure GD extension is enabled: `php -m | grep gd`

### Stripe Webhooks Not Processing

- Verify webhook secret matches Stripe Dashboard
- Check audit logs for webhook errors: `SELECT * FROM audit_log WHERE action = 'webhook_error' ORDER BY created_at DESC`
- Confirm outbound HTTPS connectivity to Stripe API

### Cart Not Working

- Check browser console for JavaScript errors
- Verify `config.php` has a valid `security.hmac_key` (32 bytes, hex-encoded)
- Clear browser cookies and try again

### Email Not Sending

- Email delivery is stubbed in `app/lib/cron.php`. Implement via PHP's `mail()` function or an external SMTP service.

## Security Considerations

- All passwords use Argon2id hashing
- Admin sessions use strict CSRF tokens
- Cart is cryptographically signed; prices re-verified at checkout
- Stripe webhook signatures validated with HMAC-SHA256
- Rate limiting prevents brute force on checkout and downloads
- All user input is escaped for output (XSS prevention)
- All queries use prepared statements (SQL injection prevention)
- Download links expire after 30 days and have per-customer download limits
- Audit log tracks all sensitive operations (login, checkout, download, refund)

## License

AGPL-3.0. See LICENSE file for details.

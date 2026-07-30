# Installation Guide

Self-hosted photo gallery and sales platform for sports photographers.

**Two separate install flows:**
- **Local Development** (this page) — zero manual steps, auto-config, dummy data, local testing
- **Production** (shared hosting, VPS) — manual config, real database, live deployment

---

## 🚀 Local Development (30 seconds, zero setup)

**For testing and development only.** Auto-detects PHP/MySQL, generates config with dev defaults, seeds dummy data, starts the server.

### macOS
```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
bash install-mac.sh
```

### Linux
```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
bash install-linux.sh
```

### Windows (PowerShell)
```powershell
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force
.\install-windows.ps1
```

**What happens automatically:**
- Detects PHP and SQLite/MySQL
- Generates `config/config.php` with dev defaults
- Drops and recreates database schema
- Seeds dummy data (1 event, 1 session, 10 photos)
- Generates random admin password
- Starts `php -S localhost:8000`

**You get:**
```
Access the gallery: http://localhost:8000
Admin email:        admin@localhost
Admin password:     <printed to terminal>
```

That's it. Everything is configured. Change password, upload real photos, test features.

**Notes:**
- Emails logged to `storage/dev-emails.log` (not sent)
- Rate limits raised 10x (no lockout during testing)
- Dummy Stripe/SMTP config already set
- Database is SQLite by default (no MySQL needed)

---

## 📦 Production Installation

**For live servers:** shared hosting (IONOS, Bluehost, etc), VPS, or any real domain.

```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
php install.php
```

The interactive installer:
- Checks PHP version and extensions
- Asks for database details (MySQL or SQLite on disk)
- Creates database and runs migrations
- Generates `config/config.php` with YOUR values (not dev defaults)
- Shows you what to do next (add cron, enable HTTPS, etc)

**Then:**
1. Visit your gallery URL
2. Go to `/admin/setup` to create your admin account
3. Add Stripe keys and configure email in Settings

**Troubleshooting?** Run this anytime:
```bash
php verify-setup.php
```

---

## Alternative: Docker or MAMP (Development Alternatives)

For local development, you can also use:

### Option 1: Docker (Fastest) ⭐ Alternative

If you have Docker installed, this is all you need:

```bash
docker-compose up
```

Wait for "Gallery - Initializing..." message to complete (~30-60 seconds). Then:

1. Open http://localhost:8080
2. Go to http://localhost:8080/admin/setup
3. Create your admin account
4. Start uploading photos

**Completely automatic setup:**
- Database created and schema imported automatically
- Config (`config/config.php`) auto-generated from environment variables
- Storage folders created with correct permissions
- Database verified ready before Apache starts
- No manual setup required

To stop: `Ctrl+C`  
To restart: `docker-compose up`  
To see logs: `docker-compose logs -f app`

---

## Option 2: MAMP (Mac, with Setup Script)

### Prerequisites
- MAMP installed and running (https://www.mamp.info/)
- Git installed

### Installation

1. **Clone the repo:**
```bash
cd ~/Applications/MAMP/htdocs
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
git checkout claude/plugin-skill-setup-y9v6kx
```

2. **Run setup script:**
```bash
bash setup.sh
```

This automatically:
- Creates `config/config.php` with random security keys
- Creates storage folders
- Imports database schema
- Shows you what to do next

3. **In MAMP app:**
   - Preferences → Web Server
   - Set Document Root to: `/Applications/MAMP/htdocs/open-source-gallery/public`
   - Click OK, restart servers

4. **Go to setup:**
   - http://localhost:8888/admin/setup
   - Create your admin account
   - Start uploading

---

## Option 3: Manual Shared Hosting Setup

**If the installer doesn't work for you:**

### Quick Verification

After uploading, run:
```bash
php host-setup.php
```

This checks everything and tells you what needs fixing.

### Detailed Manual Steps

1. **Upload files** via SFTP/FTP to your hosting provider's public HTML directory

2. **Create config file:**
   - Open `config/config.example.php` in a text editor
   - Copy it, rename to `config/config.php`
   - Fill in your database credentials (from your hosting control panel)
   - Save and upload

3. **Create database:**
   - Use your hosting's control panel (usually cPanel or similar)
   - Create database: `photo_gallery`
   - Create user: `gallery` with a strong password
   - Give user full permissions on the database

4. **Import schema:**
   - Use your hosting's phpMyAdmin
   - Select the `photo_gallery` database
   - Click Import
   - Choose `migrations/001_initial_schema.sql`
   - Click Import

5. **Set permissions:**
   - Create folder: `storage/hires`
   - Create folder: `storage/zips`
   - Create folder: `public/media/d`
   - Make these writable by the web server (usually `755` or `777`)

6. **Set up cron:**
   - In your hosting control panel, add cron job:
   ```
   */5 * * * * php /home/username/public_html/app/cron/run.php
   ```
   - Or use the URL-based fallback in `config/config.php`: `security.cron_secret`

7. **First admin account:**
   - Visit: `https://yourdomain.com/admin/setup`
   - Create your account
   - You'll be redirected to login
   - Set up two-factor authentication when prompted

8. **Stripe setup (for payments):**
   
   **Easiest:** Use the admin Settings page (no file editing required):
   - Log in to `/admin`
   - Click "Settings" in the Configuration section
   - Get test keys: https://dashboard.stripe.com/test/apikeys
   - Paste Stripe keys into the form
   - Save
   
   **Or manually** edit `config/config.php` (requires server access):
   - Set `stripe.publishable_key` and `stripe.secret_key`
   
   **Webhook setup** (required for payment confirmation):
   - Go to Stripe Dashboard → Webhooks
   - Create webhook with URL: `https://yourdomain.com/webhook/stripe`
   - Select events: `checkout.session.completed`, `charge.refunded`
   - Copy webhook secret to admin Settings page (or `config/config.php` if editing manually)

---

## Troubleshooting

### "Internal Server Error" (500)
- **Check database:** Can you connect? Is `photo_gallery` database created?
- **Check config:** Is `config/config.php` readable and filled in correctly?
- **Check permissions:** Are `storage/` and `public/media/` writable by Apache?

### "MySQL command not found" on Mac
- MAMP's MySQL isn't in your PATH. Use phpMyAdmin instead (http://localhost:8888/phpmyadmin) to create the database and import the initial schema (migrations/001_initial_schema.sql).

### Photos not showing / No derivatives
- Check that `cron` is running: Look for recent `process_derivative_job` entries in the database (or check your hosting's cron logs)
- Ensure `public/media/d/` is writable

### "Email not sending"
- Check that email configuration is set:
  - **Easy:** Log in to `/admin` → Settings → configure SMTP (or leave blank for mail())
  - **Manual:** Edit `config/config.php` with SMTP details
  - See [docs/EMAIL.md](docs/EMAIL.md) for provider setup (Gmail, SendGrid, AWS SES, Mailgun)
- Test: Upload a test photo, complete a test order (use Stripe test card `4242 4242 4242 4242`)
- Check spam folder and verify cron is running: `php verify-setup.php`

### Stripe webhooks not working
- Verify webhook secret in `config/config.php` matches Stripe Dashboard
- Check audit logs: `SELECT * FROM audit_log WHERE action LIKE '%webhook%' ORDER BY created_at DESC LIMIT 10;`

---

## Advanced: Remote Admin Mode (Optional)

**Default behavior:** Admin panel runs on the same server as your public gallery. Everything is self-contained, simplest setup.

**Remote admin mode:** Admin panel runs on a **separate server** (your home machine, laptop, staging server, etc.) and connects to your production database and file storage over the network. Useful if you want:
- Admin panel isolated from the public-facing server for security
- Admin panel on a local machine while the gallery runs on shared hosting
- Separate backup/development admin server

### When You DON'T Need This

**Use local mode (default) if:**
- You want the simplest possible setup ✓ (recommended for most)
- Your hosting supports local-only access (all shared hosting does) ✓
- You want everything on one server ✓

### Enabling Remote Admin Mode

**Prerequisites:** Your hosting provider must support:
1. **Remote MySQL connections** — check if your host allows connecting to MySQL from an external IP. Most shared hosts do NOT allow this by default. Contact support: "Can we enable remote MySQL connections restricted to IP X.X.X.X?"
2. **SFTP access** — virtually all hosts support this (same credentials as uploading files)

**To enable:**

1. **In `config/config.php` on the production server:**
   ```php
   'admin_mode' => 'remote',  // Changed from 'local'
   ```

2. **On your separate admin machine, clone the repo and create `config/config.php`:**
   ```php
   return [
       // ... regular config ...
       'admin_mode' => 'remote',
       'admin_remote' => [
           // Production database (ask your host for these)
           'db_host'    => '192.0.2.1',      // Production server's IP/hostname
           'db_port'    => 3306,
           'db_name'    => 'photo_gallery',
           'db_user'    => 'gallery_remote', // Usually restricted user
           'db_pass'    => 'your_password',

           // SFTP to access uploaded files
           'sftp_host'      => '192.0.2.1',
           'sftp_port'      => 22,
           'sftp_user'      => 'your_sftp_user',
           'sftp_pass'      => 'your_sftp_pass',
           'sftp_key_file'  => '',  // Or path to SSH private key instead of password
           'storage_path'   => '/home/username/storage',  // Full path on production server
       ],
   ];
   ```

3. **On the admin machine, run the admin entry point:**
   ```bash
   # Local development:
   php -S localhost:8001 -t admin/

   # Or deploy admin/ to a separate web server
   # Visit: http://localhost:8001/
   ```

4. **On the production server:** The public-facing site continues as normal, but returns 404 for `/admin/*` routes.

### Requirements & Limitations

- **Remote MySQL must be enabled on your host** — most shared hosts require you to enable this and often restrict by IP address for security
- **SFTP access required** — for uploading and managing files
- **Network latency** — database queries and file operations will be slightly slower (milliseconds)
- **Admin panel is the only thing remote** — public site still runs where you deploy it
- **No special dependencies** — uses phpseclib (pure PHP, no system extensions needed)

### Checking If Your Host Supports It

**Before attempting remote mode:**

```sql
-- Connect to your production database from your admin machine:
mysql -h 192.0.2.1 -u gallery_remote -p photo_gallery

-- If this works, you're good. If it fails with "Access denied", your host
-- doesn't allow remote connections. Contact support to enable it.
```

**For SFTP, test with any SFTP client:**
```bash
sftp your_sftp_user@192.0.2.1
# If you can log in, SFTP is configured correctly
```

### IONOS-Specific Notes

**IONOS shared hosting:**
- ✓ Supports SFTP (standard)
- ? Remote MySQL connections: Check with support. Some plans allow it (requires enabling), some don't. **Not all IONOS plans support remote MySQL.** Ask specifically: "Does this plan allow remote MySQL connections? Can we restrict by IP?"
- If not supported, use local mode (default)

---

## Local Development Configuration (DEV_MODE)

When running locally for development, set `'dev_mode' => 'local'` in `config/config.php` to disable security restrictions that don't apply to `http://localhost`:

**Open `config/config.php` and change:**
```php
'dev_mode' => 'production',  // ← Change this
```

**To:**
```php
'dev_mode' => 'local',
```

### What Changes in Local Mode

| Feature | Production | Local Dev |
|---------|-----------|-----------|
| HTTP Cookies | HTTPS only | HTTP allowed (still secure) |
| HSTS Header | Enabled | Disabled (meaningless on HTTP) |
| Rate Limiting | 5 attempts per window | 50+ attempts (no lockout during testing) |
| Email Sending | Real SMTP/mail() | Logged to `storage/dev-emails.log` |
| Background Jobs | Cron every 5 min | Manual trigger at `/admin/jobs/run` |

**Everything else stays fully active:** CSRF protection, session management, audit logging, security headers, output escaping, rate limits themselves.

### Running Jobs Manually in Local Dev

Background jobs (photo processing, email delivery) don't run on `http://localhost` without cron. To trigger them:

1. Go to the admin panel: `http://localhost:8080/admin`
2. Click **"System"** → **"Run Jobs"** or visit `/admin/jobs/run`
3. Jobs process for up to 20 seconds per click
4. Repeat to drain the queue

### Viewing Sent Emails in Local Dev

Emails don't get sent in local mode — they're written to `storage/dev-emails.log`:

```bash
tail -f storage/dev-emails.log
```

Each email shows the recipient, subject, and full HTML body. Useful for testing the checkout flow.

### Important: Never Deploy With `dev_mode: 'local'`

This is a development-only setting. Production servers must use `'dev_mode' => 'production'` or omit it (default). Accidentally deploying with `'dev_mode': 'local'` disables HSTS and allows cookie theft over HTTP.

**Checklist before deploying:**
- [ ] `'dev_mode' => 'production'` in `config/config.php`
- [ ] Real SMTP configured or mail() working
- [ ] Cron scheduled to run `/public/cron.php` every 5 minutes

---

## After Installation

### First: Configure Stripe & Email (if not done during install)

1. **Log in:** `/admin/login` (with the account you created)
2. **Go to Settings:** Click "Settings" in the Configuration section
3. **Add Stripe keys:** Get test keys from https://dashboard.stripe.com/test/apikeys
4. **Configure email:** Set up SMTP or use `mail()` (see [docs/EMAIL.md](docs/EMAIL.md))
5. **Set site details:** Gallery name, support email, currency

### Then: Create & Publish Content

1. **Create an event:** Events → Create → fill in name, date, venue
2. **Upload photos:** Upload → select photos → wait for processing
3. **Publish event:** Go back to Events, toggle "Published" for your event
4. **View gallery:** Visit home page (/) → see your event as a card → click to view photos
5. **Test checkout:** Click the + button on photos → View Cart → Checkout with Stripe test card `4242 4242 4242 4242`
6. **Verify email:** Check inbox for order receipt email

---

## File Structure

```
public/                          # Web root (Apache serves this)
├── index.php                    # Entry point
├── .htaccess                    # URL routing rules
├── assets/                      # CSS, JavaScript
└── media/d/                     # Derivative images (resized versions)

app/                             # Application logic (hidden from web)
├── controllers/                 # Page handlers
├── lib/                         # Business logic
├── views/                       # HTML templates
└── cron/                        # Background jobs

config/
├── config.example.php           # Template (copy this)
└── config.php                   # Your config (gitignored, never commit)

storage/                         # Uploaded files (gitignored)
├── hires/                       # Original photos
├── zips/                        # Download bundles
└── tmp/                         # Upload chunks

migrations/                      # Database schema
└── 001_initial_schema.sql
```

---

## Going Live

**Ready to deploy to production?** Use the deployment guide:

→ **[docs/DEPLOYMENT.md](../docs/DEPLOYMENT.md)** — Complete pre-launch checklist and go-live guide

This guide covers:
- [ ] Security checklist (HTTPS, 2FA, keys, Stripe, email)
- [ ] Performance & storage monitoring
- [ ] Full customer flow testing
- [ ] Day-one launch steps
- [ ] Post-launch monitoring tasks
- [ ] Scaling guidance

**Running into issues?** See:

→ **[docs/TROUBLESHOOTING.md](../docs/TROUBLESHOOTING.md)** — Quick reference for common problems

---

## Security

See `docs/SECURITY.md` for security audit and hardening guidelines.

---

## License

AGPL-3.0. See LICENSE file.

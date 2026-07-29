# Installation Guide

Self-hosted photo gallery and sales platform for sports photographers.

## Universal Installer (Works Everywhere) ⭐ Recommended

**Works on Windows, Mac, Linux, shared hosting, VPS:**

```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
php install.php
```

The interactive installer will:
- Check your environment (PHP version, extensions)
- Ask for your database details (defaults provided)
- Create database and import schema automatically
- Generate secure config file
- Tell you exactly what to do next

Then visit the URL it gives you and create your admin account. Done.

**Troubleshooting?** Run this anytime:
```bash
php verify-setup.php
```

---

## Option 1: Docker (Fastest) ⭐ Alternative

If you have Docker installed, this is all you need:

```bash
docker-compose up
```

Wait for "PowerMedia Gallery - Initializing..." message to complete (~30-60 seconds). Then:

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

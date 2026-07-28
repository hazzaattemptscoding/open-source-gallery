# Open Source Photo Gallery

A self-hosted, premium photo gallery and sales platform built for sports photographers. No Node, Docker, or build steps required—just PHP, MySQL, and Apache.

**Live demo:** See this software in production at [PowerMedia Gallery](https://powermedia-gallery.example.com) (real-world usage with thousands of motorsport photos).

## Features

### For Photographers

- **Simple upload workflow** — chunked uploads for large files
- **Auto-tagging** — tag photos by kart number, driver name, or custom categories
- **Watermarking** — configurable watermark on preview sizes (not on purchased originals)
- **Pricing tiers** — sell individual photos, full sessions, or entire events
- **Photo derivatives** — automatic 400/800/1600px versions; 1600px deleted after 7 days to save storage
- **Video hosting** — display event recap videos alongside photo grids

### For Customers

- **No accounts required** — guest checkout via Stripe
- **Shopping cart** — lightweight, signed cookie-based (prices always from DB)
- **Instant delivery** — download clean (unwatermarked) originals immediately after purchase
- **Download links** — time-limited, per-customer download limits, audit-logged

### For Store Owners

- **Real-time stats** — photo sales, revenue per image, top sellers
- **Audit trail** — all checkouts, downloads, logins logged with IP and timestamp
- **Rate limiting** — 5 checkout attempts/hour per (email, IP); 30 downloads/hour per IP
- **Webhooks** — Stripe integration with signature validation and idempotency
- **Multi-currency** — configure currency and pricing in one place

## Design Philosophy

**Premium editorial aesthetic.** White and black only, minimal, generous whitespace. No gradients, no decorative elements. Maximizes focus on photographs.

**No vendor lock-in.** Plain PHP, MySQL, vanilla HTML/CSS/JS. Runs on any shared hosting (IONOS, GoDaddy, etc.) with PHP 8.2+. No Docker, no build step, no daemons.

**Security by design.** Argon2id passwords, CSRF tokens, XSS protection, SQL injection prevention via prepared statements. Stripe webhooks validated with HMAC-SHA256. Download tokens cryptographically signed.

**Shared hosting friendly.** ~200GB storage limit? Supported (with 7-day image tiering). No CLI daemons? URL-based cron fallback. Database backup? Use standard MySQL tools.

## Quick Start

```bash
# 1. Clone and configure
git clone https://github.com/yourusername/open-source-gallery.git
cd open-source-gallery
cp config/config.template.php config/config.php
# Edit config.php with your Stripe keys and MySQL credentials

# 2. Set up database
mysql photo_gallery < migrations/001_initial_schema.sql

# 3. Create directories
mkdir -p storage/hires storage/zips public/media/d
chmod 755 storage public

# 4. Create admin account
php app/cli/create-admin.php your@email.com

# 5. Set up cron
echo "*/5 * * * * php /path/to/app/cron/run.php" | crontab -

# 6. Configure Apache
# Copy .htaccess (provided) to public/
# Point DocumentRoot to public/
# Enable mod_rewrite

# 7. Open https://yoursite.com/admin/login and start uploading!
```

For detailed setup instructions, see [INSTALL.md](INSTALL.md).

## Architecture

See [docs/architecture.md](docs/architecture.md) for:
- Database schema
- Request/response flows
- Cron job design
- Security requirements
- Rate limiting strategy

## Build Stages (Progress)

1. ✅ **Architecture + schema** — Database design, migrations, security requirements
2. ✅ **Admin auth, CRUD, upload, tagging, derivatives** — Login, TOTP 2FA, photo management, auto-tagging, image processing
3. ✅ **Public gallery** — Hero layout, photo grid, video section, search/filtering
4. ✅ **Cart, Stripe, delivery** — Shopping cart, Stripe Checkout, webhook handling, download delivery with download limits
5. ✅ **Stats + hardening** — Revenue reporting, rate limiting, audit logging, input validation
6. 🚧 **Open source release prep** — Config-driven branding, documentation, LICENSE, TRADEMARK

## Technology Stack

- **Backend:** PHP 8.2+, MySQL 5.7+
- **Frontend:** Vanilla HTML, CSS, JavaScript (no frameworks)
- **Deployment:** Apache 2.4+ with mod_rewrite
- **Payments:** Stripe Checkout (PCI-compliant, no credit card processing on your server)
- **Images:** GD library for derivatives, EXIF metadata extraction

## File Structure

```
public/                    # Web root (DocumentRoot)
├── index.php              # Single entry point
├── assets/                # CSS, JS, images
└── media/d/               # Photo derivatives

app/
├── bootstrap.php          # Config loading, DB init
├── controllers/           # Route handlers
│   ├── public/            # Checkout, download, gallery
│   ├── admin/             # CRUD, upload, tagging
│   └── webhook/           # Stripe webhooks
├── lib/                   # Shared libraries
│   ├── auth.php           # Login, TOTP, sessions
│   ├── cart.php           # Shopping cart logic
│   ├── orders.php         # Order lifecycle
│   ├── stripe.php         # Stripe API wrapper
│   ├── rate_limit.php     # Rate limiting
│   ├── audit.php          # Audit logging
│   └── ...
├── views/                 # HTML templates
├── cli/                   # CLI utilities
└── cron/                  # Cron job runner

config/
└── config.php             # All config here; nothing hardcoded

storage/
├── hires/                 # Original uploaded photos
└── zips/                  # Pre-built download bundles

migrations/
└── 001_initial_schema.sql # Database schema

docs/
└── architecture.md        # Design docs
```

## Security Audit

Before production:
1. Review [security checklist](docs/architecture.md#security)
2. Enable HTTPS (Let's Encrypt or your host's SSL)
3. Configure Stripe webhook signing secret
4. Set `security.hmac_key` to a strong random value
5. Set `security.cron_secret` and configure cron URL
6. Run admin CLI tool to create first admin user with TOTP
7. Review audit logs regularly

See [INSTALL.md](INSTALL.md#security-considerations) for full security details.

## License

AGPL-3.0. See [LICENSE](LICENSE) for details.

This project is built for and used by working photographers. Commercial redistribution or reselling the software/derivatives is prohibited. See [TRADEMARK.md](TRADEMARK.md) for branding restrictions.

## Contributing

Contributions welcome! Please:
1. Follow the code style (no comments unless WHY is non-obvious; descriptive names)
2. Write prepared statements for all DB queries
3. Use `e()` for XSS prevention on HTML output
4. Add audit logging for sensitive operations
5. Update CHANGELOG and docs with your changes

## FAQ

**Q: Can I add feature X?**  
A: If it fits the design goals (simple, self-hosted, minimal dependencies), yes. Open an issue to discuss.

**Q: What about email delivery?**  
A: Receipt emails are queued but not implemented. Plug in your mail service (PHP's `mail()`, SendGrid, etc.) in `app/lib/cron.php:process_email_job()`.

**Q: How many photos can I host?**  
A: As many as your storage allows. 400/800px derivatives are kept forever; 1600px deleted after 7 days. A typical 5MP photo takes ~400KB hires + 200KB derivatives.

**Q: Is it GDPR compliant?**  
A: Download links and audit logs contain customer IPs for security. Implement data deletion on request per your privacy policy. See `app/lib/audit.php` and `app/controllers/public/download.php`.

**Q: Can I run this on Heroku / Docker / etc?**  
A: Not the intended use case. This is built for shared hosting (Apache + cron). You *can* containerize it if you want, but you'd lose the simplicity benefit.

## Support

- **Issue tracker:** GitHub Issues
- **Discussions:** GitHub Discussions
- **Docs:** [INSTALL.md](INSTALL.md), [docs/architecture.md](docs/architecture.md)

---

Built by [PowerMedia](https://powermedia.com). Used in production with thousands of motorsport photos. Proven and battle-tested before open source.

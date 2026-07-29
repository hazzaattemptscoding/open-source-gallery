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
- **Shopping cart** — lightweight, signed cookie-based (prices always from DB); prevents duplicate items
- **Volume discounts** — automatic discounts on photo packages (e.g., 10+ photos = 15% off)
- **Instant delivery** — download clean (unwatermarked) originals immediately after purchase
- **Download links** — time-limited, per-customer download limits, cryptographically signed, audit-logged
- **Email receipts** — automatic order confirmation with download link via email

### For Store Owners

- **Real-time stats** — photo sales, revenue per image, top sellers
- **Audit trail** — all checkouts, downloads, logins logged with IP and timestamp
- **Rate limiting** — 5 checkout attempts/hour per (email, IP); 30 downloads/hour per IP
- **Webhooks** — Stripe integration with signature validation and idempotency
- **Multi-currency** — configure currency and pricing in one place

## Design Philosophy

**Premium dark aesthetic.** Pixieset-inspired design with dark backgrounds, compact layouts, and full-bleed images. Maximizes visual impact while maintaining editorial clarity. Responsive across desktop, tablet, and mobile.

**No vendor lock-in.** Plain PHP, MySQL, vanilla HTML/CSS/JS. Runs on any shared hosting (IONOS, GoDaddy, etc.) with PHP 8.2+. No Docker, no build step, no daemons.

**Security by design.** Argon2id passwords, Stripe webhook HMAC-SHA256 validation, cryptographically signed download tokens, OWASP Top 10 mitigations. See [docs/SECURITY.md](docs/SECURITY.md) for full audit.

**Shared hosting friendly.** ~200GB storage limit? Supported (with 7-day image tiering). No CLI daemons? URL-based cron fallback. Database backup? Use standard MySQL tools. Email delivery via mail() or SMTP.

## Quick Start

**Automatic setup for macOS, Linux, or Windows:**

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

**What the installer does:**
- Detects and installs PHP 8.2, MySQL, Git (if missing)
- Runs the interactive setup wizard
- Creates database and config automatically
- Starts the development server
- Opens your browser to the admin setup page

Then:
1. Create your admin account
2. Go to Settings and add your Stripe keys (optional, for sales)
3. Configure email (SMTP or mail() — see [docs/EMAIL.md](docs/EMAIL.md))
4. Start uploading photos

**Or use Docker:**
```bash
docker-compose up
```

See [QUICK-START.md](QUICK-START.md) and [INSTALL.md](INSTALL.md) for detailed options.

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
3. ✅ **Public gallery** — Dark theme, hero layout, photo grid, video section, search/filtering
4. ✅ **Cart, Stripe, delivery** — Shopping cart, volume discounts, Stripe Checkout, webhook handling, download delivery
5. ✅ **Stats + hardening** — Revenue dashboard, order history, rate limiting, audit logging, security headers
6. ✅ **Open source release prep** — Config-driven branding, documentation, LICENSE, TRADEMARK, SECURITY audit
7. ✅ **Production readiness** — Email templates, admin settings UI, data export, audit logs, session management, deployment guide

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
A: Professional HTML emails are sent automatically after orders. Set up via admin Settings page (no coding required): configure SMTP (Gmail, SendGrid, AWS SES, etc.) or use your server's `mail()`. See [docs/EMAIL.md](docs/EMAIL.md) for provider setup.

**Q: How many photos can I host?**  
A: As many as your storage allows. 400/800px derivatives are kept forever; 1600px deleted after 7 days. A typical 5MP photo takes ~400KB hires + 200KB derivatives.

**Q: Is it GDPR compliant?**  
A: Download links and audit logs contain customer IPs for security. Implement data deletion on request per your privacy policy. See `app/lib/audit.php` and `app/controllers/public/download.php`.

**Q: Can I run this on Heroku / Docker / etc?**  
A: Not the intended use case. This is built for shared hosting (Apache + cron). You *can* containerize it if you want, but you'd lose the simplicity benefit.

## Documentation

- **[QUICK-START.md](QUICK-START.md)** — Automatic OS-specific installers (macOS, Linux, Windows)
- **[INSTALL.md](INSTALL.md)** — Manual installation for all platforms
- **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** — Pre-launch checklist and go-live guide
- **[docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)** — Common issues and solutions
- **[docs/EMAIL.md](docs/EMAIL.md)** — Email configuration (SMTP, Gmail, SendGrid, AWS SES, Mailgun)
- **[docs/SECURITY.md](docs/SECURITY.md)** — Security audit and hardening
- **[docs/architecture.md](docs/architecture.md)** — Technical design and database schema

## Support

- **Issue tracker:** [GitHub Issues](https://github.com/hazzaattemptscoding/open-source-gallery/issues)
- **Discussions:** [GitHub Discussions](https://github.com/hazzaattemptscoding/open-source-gallery/discussions)
- **Stuck?** Check [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) first—it covers 99% of issues

---

Built by [PowerMedia](https://powermedia.com). Used in production with thousands of motorsport photos. Proven and battle-tested before open source.

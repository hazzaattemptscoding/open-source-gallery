# Open Source Photo Gallery

A self-hosted photo gallery and sales platform for sports photographers. Plain PHP, MySQL and Apache. No Node, no build step, no daemons.

**Status: pre-production.** This is being built and proven on the maintainer's own motorsport gallery before a tagged release. The schema and the checkout path are stable. Driver discovery, the feature the project exists for, is actively being built. There is no public demo yet.

## Features

### For Photographers

- **Simple upload workflow**: chunked, resumable uploads for large files
- **Bulk tagging**: tag photos by kart number and class, in batches, from one screen
- **Watermarking**: configurable watermark on preview sizes, never on purchased originals
- **Pricing tiers**: sell individual photos, full sessions, or entire events
- **Photo derivatives**: automatic 400/800/1600px versions, all kept for as long as the photo exists
- **Video hosting**: display event recap videos in their own section, separate from the photo grid

### For Customers

- **No accounts required**: guest checkout via Stripe
- **Shopping cart**: lightweight, signed cookie-based, prices always read from the database
- **Favourites**: shortlist photos before deciding, no account needed
- **Volume discounts**: automatic discounts on larger photo sets, configurable
- **Instant delivery**: download clean, unwatermarked originals immediately after purchase
- **Download links**: time-limited, per-customer limits, cryptographically signed, audit-logged
- **Email receipts**: order confirmation with download link sent automatically

### For Store Owners

- **Real-time stats**: photo sales, revenue per image, top sellers
- **Audit trail**: checkouts, downloads and logins logged with IP and timestamp
- **Rate limiting**: 5 checkout attempts/hour per (email, IP), 30 downloads/hour per IP
- **Webhooks**: Stripe integration with signature validation and idempotency
- **Multi-currency**: currency and pricing configured in one place

## Design Philosophy

**Editorial, near-monochrome.** Warm-neutral chrome that reads as tinted ink rather than a coloured site, with a single deep plum accent reserved for primary actions, selection and focus. Generous whitespace, full-bleed images, no decorative elements. Supports both light and dark via `prefers-color-scheme`, and the palette is overridable per-site from the admin.

**No vendor lock-in.** Plain PHP, MySQL, vanilla HTML/CSS/JS. Runs on ordinary shared hosting (IONOS, GoDaddy and similar) with PHP 8.2+.

**Security by design.** Argon2id passwords, Stripe webhook HMAC-SHA256 validation, cryptographically signed download tokens, OWASP Top 10 mitigations. See [docs/SECURITY.md](docs/SECURITY.md).

**Privacy by design.** Photos are found by kart number and class, never by face and never by driver name. This is a deliberate choice, not an omission: a large share of karting drivers are minors, and identifying people from images is special-category biometric data under UK GDPR Article 9. See [docs/PRIVACY-DESIGN.md](docs/PRIVACY-DESIGN.md).

**Shared hosting friendly.** Works inside a ~200GB storage allowance. No CLI daemons required; there is a URL-based cron fallback. Database backup uses standard MySQL tools. Email delivery via `mail()` or SMTP.

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
- Detects and installs PHP 8.2, MySQL and Git if missing
- Runs the interactive setup wizard
- Creates the database and config automatically
- Starts the development server
- Opens your browser at the admin setup page

Then:
1. Create your admin account
2. Go to Settings and add your Stripe keys (optional, for sales)
3. Configure email, SMTP or `mail()`. See [docs/EMAIL.md](docs/EMAIL.md)
4. Start uploading photos

**Local development with Docker.** A `Dockerfile` and `docker-compose.yml` are included and are supported for local development only, so contributors can mirror the PHP and MySQL versions without installing them:

```bash
docker-compose up
```

Docker is not how this is meant to be deployed. The deployment target is ordinary shared hosting with Apache and cron.

See [QUICK-START.md](QUICK-START.md) and [INSTALL.md](INSTALL.md) for detailed options.

## Architecture

See [docs/architecture.md](docs/architecture.md) for:
- Database schema
- Request/response flows
- Cron job design
- Security requirements
- Rate limiting strategy

## Build Stages

1. **Architecture and schema**: done. Database design, migrations, security requirements
2. **Admin auth, CRUD, upload, tagging, derivatives**: done. Login, TOTP 2FA, photo management, bulk tagging, image processing
3. **Public gallery**: done. Hero layout, photo grid, favourites, separate video section, search and filtering
4. **Cart, Stripe, delivery**: done. Cart, volume discounts, Stripe Checkout, webhook handling, clean-file delivery
5. **Stats and hardening**: done. Revenue dashboard, order history, rate limiting, audit logging, security headers
6. **Open source release prep**: in progress. Config-driven branding, commenting standard, LICENSE, TRADEMARK, install guide tested end to end

**What is being built now:** driver discovery. Today a visitor filters by kart number and class using two separate dropdowns, which cannot tell #7 in Cadet from #7 in Senior X30. The work in progress replaces that with a find-me flow keyed on the real composite identity of a driver, returning a shareable personal page. That is the feature this project exists for, and it is not finished yet.

## Technology Stack

- **Backend:** PHP 8.2+, MySQL 5.7+ (SQLite supported for local development)
- **Frontend:** Vanilla HTML, CSS, JavaScript, no frameworks
- **Deployment:** Apache 2.4+ with mod_rewrite
- **Payments:** Stripe Checkout, so no card data touches your server
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

migrations/                # Schema, additive only, MySQL and SQLite

docs/
├── architecture.md        # Design docs
└── archive/               # Superseded working notes
```

## Security Checklist

Before going live:
1. Review the [security checklist](docs/architecture.md#security)
2. Enable HTTPS (Let's Encrypt or your host's SSL)
3. Configure the Stripe webhook signing secret
4. Set `security.hmac_key` to a strong random value
5. Set `security.cron_secret` and configure the cron URL
6. Create the first admin user and enrol TOTP
7. Review audit logs regularly

See [INSTALL.md](INSTALL.md#security-considerations) for full details.

## License

AGPL-3.0. See [LICENSE](LICENSE).

The code is open. The project name and branding are not: see [TRADEMARK.md](TRADEMARK.md). You can fork, modify and self-host freely, including commercially, provided you publish your source per the AGPL and do not present your fork as the official project.

## Contributing

Contributions welcome. Please:
1. Comment for a reader who has never seen the file, and explain why rather than what. A non-technical maintainer should be able to follow your code with the help of an AI tool
2. Use prepared statements for every database query
3. Use `e()` for XSS prevention on all HTML output
4. Add audit logging for sensitive operations
5. Keep migrations additive, and write both the MySQL and SQLite version
6. Update the docs alongside your change

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full guide.

## FAQ

**Q: Can I add feature X?**
A: If it fits the design goals (simple, self-hosted, minimal dependencies), yes. Open an issue to discuss.

**Q: What about email delivery?**
A: HTML emails are sent automatically after orders. Set this up from the admin Settings page with no coding: configure SMTP (Gmail, SendGrid, AWS SES and similar) or use your server's `mail()`. See [docs/EMAIL.md](docs/EMAIL.md).

**Q: How many photos can I host?**
A: As many as your storage allows. All derivative sizes are kept for as long as the photo exists, because motorsport galleries are often discovered late by word of mouth and a missing preview at that moment costs the sale. A typical 5MP photo takes roughly 400KB hires plus 200KB of derivatives.

**Q: Is it GDPR compliant?**
A: The software is built to make compliance achievable, but compliance is a property of how you operate it, not of the code alone. Download links and audit logs store customer IPs for security. Driver names are never published. You will need your own privacy policy and a process for deletion requests. See [docs/PRIVACY-DESIGN.md](docs/PRIVACY-DESIGN.md), `app/lib/audit.php` and `app/controllers/public/download.php`.

**Q: Can I run this on Heroku, Docker or similar?**
A: Docker is supported for local development and ships in the repo for that purpose. For deployment it is not the intended target: this is built for shared hosting with Apache and cron, and containerising it loses the simplicity that is the point. It will run in a container if you want it to.

## Documentation

- **[QUICK-START.md](QUICK-START.md)**: Automatic OS-specific installers
- **[INSTALL.md](INSTALL.md)**: Manual installation for all platforms
- **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**: Pre-launch checklist and go-live guide
- **[docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)**: Common issues and solutions
- **[docs/EMAIL.md](docs/EMAIL.md)**: Email configuration
- **[docs/SECURITY.md](docs/SECURITY.md)**: Security audit and hardening
- **[docs/PRIVACY-DESIGN.md](docs/PRIVACY-DESIGN.md)**: Why discovery is by number, not by face or name
- **[docs/architecture.md](docs/architecture.md)**: Technical design and database schema

## Support

- **Issue tracker:** [GitHub Issues](https://github.com/hazzaattemptscoding/open-source-gallery/issues)
- **Discussions:** [GitHub Discussions](https://github.com/hazzaattemptscoding/open-source-gallery/discussions)
- **Stuck?** Start with [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)

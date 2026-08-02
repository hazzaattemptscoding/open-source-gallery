# Self-Hosted Gallery Setup Guide

This is an open-source, self-hosted photo gallery for sports photographers. You run it on your own server, not someone else's cloud.

**What this means:**
- Full control over your data and photos
- No recurring SaaS fees
- No vendor lock-in
- You manage the hosting yourself (or hire someone to)

**Time required:** 30 minutes to 1 hour depending on your setup.

---

## Option 1: Docker (Easiest)

**Time: 5 minutes**

If you have [Docker](https://docker.com) installed:

```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
docker-compose up
```

Then:
1. Open http://localhost:8080
2. Click "Setup" and create your admin account
3. Done. Start uploading photos.

Docker handles PHP, Apache, MySQL, and everything else automatically. No configuration needed.

---

## Option 2: Local PHP + MySQL (10-15 minutes)

**Time: 10-15 minutes**

**Prerequisites:**
- PHP 8.2+ ([download](https://www.php.net/downloads.php))
- MySQL 5.7+ or MariaDB 10.4+ ([download](https://dev.mysql.com/downloads/))
- Git ([download](https://git-scm.com/))

**Installation:**

```bash
# 1. Clone
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery

# 2. Create config
cp config/config.example.php config/config.php

# 3. Run installer (fill in database details when prompted)
php install.php

# 4. Start the server
php -S localhost:8080 -t public/

# 5. Open http://localhost:8080 and create admin account
```

The installer asks for your database details and creates everything automatically.

---

## Option 3: Shared Hosting (30-60 minutes)

**Time: 30-60 minutes**

**Requirements:**
- PHP 8.2+ (most hosts support 8.2+)
- MySQL 5.7+ (every host has this)
- SFTP access (every host has this)
- Control panel (cPanel, Plesk, etc.)

**Process:**

1. **Upload files** via SFTP/FTP to your hosting's public directory
2. **Create database** in your host's control panel (cPanel: "MySQL Databases")
3. **Import schema** using phpMyAdmin (your host provides this)
4. **Copy and edit** `config/config.example.php` with your database details
5. **Set up cron** for the background image processing job (5-minute intervals)
6. **Visit** your domain and create your admin account

See **[INSTALL.md](../INSTALL.md)** for detailed shared hosting instructions.

---

## What You Get

After setup, you have:

- **Public gallery** — Beautiful, minimal photo gallery at your domain
- **Admin panel** — Upload photos, create events, manage pricing
- **Shopping cart** — Customers select photos and purchase with Stripe
- **Delivery** — Automatic clean-file download links sent via email
- **Analytics** — Track sales, popular photos, customer locations
- **No ads** — Your gallery, your branding

---

## What It Costs

**Hosting:** $5–$50/month depending on traffic
- Shared hosting: $5–$15/month (for a new gallery)
- VPS: $10–$50/month (for moderate traffic)

**Domains:** $10–$15/year

**Stripe fees:** 2.9% + $0.30 per transaction

**Everything else:** Free (it's open source)

---

## Is This Right For You?

**Choose this if you:**
- Want full control over your data
- Don't mind managing a server (or paying someone to)
- Want to own your gallery long-term
- Need custom features or integrations

**Don't choose this if you:**
- Want zero technical setup (use Smugmug, Zenfolio, etc.)
- Can't handle basic server administration
- Want someone else to manage security and backups

---

## After Setup

### First Login
1. Go to `/admin/setup`
2. Create your admin account
3. Set up Stripe keys (get them at [dashboard.stripe.com](https://dashboard.stripe.com))
4. Configure email (optional, but needed for order receipts)

### Uploading Photos
1. Create an "Event" (e.g., "Austin Grand Prix 2024")
2. Upload photos (JPEG only, any size)
3. Tag photos (driver, kart, class) — used by customers to filter
4. Set prices (per-photo or volume discounts)
5. Publish the event

### How It Works
- Customers visit your gallery
- Click `+` on photos to add to cart
- View cart, modify quantities, get volume discounts
- Checkout with Stripe (no account needed, guest checkout)
- Receive download link + receipt email automatically
- Photos are watermarked in the gallery preview, clean files delivered

---

## Common Questions

**Q: Can I run this on shared hosting?**  
A: Yes, but upload/processing will be slower. Docker or a VPS is better.

**Q: Do I need to know PHP?**  
A: No. The installer handles everything. You just need to follow the prompts.

**Q: What if I need help?**  
A: See [INSTALL.md](../INSTALL.md) for setup troubleshooting. Check [docs/architecture.md](architecture.md) for technical details.

**Q: Can I customize the design?**  
A: The public gallery design is fixed (minimalist, black and white). You can customize the admin panel.

**Q: How do I back up my photos?**  
A: All original photos are stored in `storage/hires/` on your server. Back up this folder regularly.

**Q: Is my data secure?**  
A: Yes. All payment data goes directly to Stripe (you never see credit cards). Passwords are hashed with Argon2id. Two-factor authentication is available.

---

## Technical Stack

- **Server:** Apache 2.4+ with mod_rewrite
- **Language:** PHP 8.2+
- **Database:** MySQL 5.7+ or MariaDB 10.4+
- **Frontend:** Vanilla HTML/CSS/JavaScript (no build step)
- **Payment:** Stripe Checkout
- **Email:** SMTP or mail()

No Node, no webpack, no Docker required (though Docker is available).

---

## Next Steps

1. **Quick Start:** See [QUICK-START.md](../QUICK-START.md) for the fastest path
2. **Full Docs:** See [INSTALL.md](../INSTALL.md) for detailed setup options
3. **Deployment:** See [docs/DEPLOYMENT.md](DEPLOYMENT.md) for going live
4. **Troubleshooting:** Run `php verify-setup.php` to diagnose issues

---

## License

AGPL-3.0. See [LICENSE](../LICENSE) for details.

This means: you can run it privately on your own server. If you modify the code and share it with others, you must share your modifications too.

# Quick Start (2 Minutes)

## Instant: SQLite (No Setup Required)

**All you need:** PHP 8.2+

```bash
php -S localhost:8080 -t public/
```

That's it. Site is live at http://localhost:8080.

SQLite database is created automatically and persists in `storage/gallery.sqlite`. No MySQL server, no config, nothing to install.

---

## With Docker (5 minutes, includes everything)

```bash
docker-compose up
```

Then open http://localhost:8080.

---

## With MySQL (10 minutes, for production)

```bash
php install.php
# Choose option 2 (MySQL)
# Answer the prompts for your database
php -S localhost:8080 -t public/
```

---

## First Time Setup

1. Open http://localhost:8080/admin/setup
2. Create admin account
3. Go to Settings, add Stripe keys (or skip for testing)
4. Start uploading photos

---

## Seeing Your Gallery

1. Click Upload → select photos
2. Tag them (optional, but helps customers filter)
3. Go back to home page
4. Click on your event to see gallery
5. Click + on photos to add to cart

---

## Exporting as Static HTML

Generate a static gallery that doesn't need a database:

```bash
php generate-static.php output/
# Creates standalone HTML in output/ directory
```

Then upload `output/` to any web server (GitHub Pages, Netlify, etc.) or open `output/index.html` in a browser.

---

## Production Deployment

See **[SELF_HOSTED.md](SELF_HOSTED.md)** to understand options and costs.

See **[INSTALL.md](INSTALL.md)** for shared hosting step-by-step.

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for security checklist before going live.

# Quick Start

**One command to get running everywhere:**

## Any Computer (Mac, Linux, Windows)

```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
php install.php
```

This interactive installer will:
- Check your PHP version and extensions
- Ask for database details (or use defaults)
- Create the database and import schema automatically
- Generate your config file with secure keys
- Tell you exactly where to go next

Then just visit the URL it gives you and create your admin account.

---

## With Docker (Fastest)

```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
docker-compose up
```

Wait for "Gallery - Initializing..." message, then visit `http://localhost:8080/admin/setup`.

---

## That's It

After either method above:
1. Visit the URL your installer provided (or `http://localhost:8080`)
2. Go to `/admin/setup`
3. Create your admin account
4. Start uploading photos

No config editing, no manual database imports, no confusion.

---

## Troubleshooting

Run the verification script:
```bash
php verify-setup.php
```

This checks everything and tells you what needs fixing.

See [INSTALL.md](INSTALL.md) for detailed options and troubleshooting.

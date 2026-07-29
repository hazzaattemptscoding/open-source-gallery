# Quick Start (5 Minutes)

## Using Docker (Recommended)

**All you need:** Docker and this command.

```bash
docker-compose up
```

Then open http://localhost:8080 and create your admin account.

That's it. Database, Apache, PHP, everything is automatic.

---

## Using PHP + MySQL (Local Development)

**Requirements:** PHP 8.2+, MySQL 5.7+

```bash
# 1. Copy config
cp config/config.example.php config/config.php

# 2. Run installer (asks for database details)
php install.php

# 3. Start PHP server
php -S localhost:8080 -t public/

# 4. Open http://localhost:8080 and create admin account
```

The installer handles creating the database and importing the schema.

---

## Using Shared Hosting (IONOS, GoDaddy, etc)

**The hard way:** 30 minutes of file uploads and control panel clicks.

**The easy way:** Use Docker on your local machine first to verify everything works. Then upload files to your host.

See **[INSTALL.md](INSTALL.md)** for shared hosting step-by-step.

---

## Having Issues?

**Database won't connect:**
```bash
# Start MySQL (if it's not running)
brew services start mysql        # Mac
sudo systemctl start mysql       # Linux
net start MySQL80                # Windows (if installed as service)

# Then try php install.php again
```

**Docker not installed:**
Get Docker here: https://www.docker.com/products/docker-desktop

**Still stuck?**
- See [INSTALL.md](INSTALL.md) for detailed troubleshooting
- Run `php verify-setup.php` to check your environment
- Check [docs/architecture.md](docs/architecture.md) for how the system works

---

## Next Steps

1. **Log in:** Create admin account at http://localhost:8080/admin/setup
2. **Configure:** Go to Settings → fill in Stripe keys and email
3. **Upload photos:** Upload → select JPEGs → tag and publish
4. **View gallery:** Go to http://localhost:8080 to see your event

That's the core flow. You're done.

---

## Going Live

When you're ready to deploy to production, see **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for the security checklist and DNS configuration.

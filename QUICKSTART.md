# Quick Start (2 Minutes)

## For Local Testing (Mac/Linux)

```bash
# 1. Clone
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
git checkout claude/plugin-skill-setup-y9v6kx

# 2. Start (requires Docker)
docker-compose up

# 3. Open
http://localhost:8080/admin/setup
```

Create admin account → Upload photos → Done.

---

## For MAMP on Mac

```bash
cd ~/Applications/MAMP/htdocs
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
bash setup.sh
```

Then:
1. In MAMP app: Preferences → Web Server → Document Root → set to `/Applications/MAMP/htdocs/open-source-gallery/public`
2. Restart MAMP
3. Go to http://localhost:8888/admin/setup

---

## For Production Hosting

See [INSTALL.md](INSTALL.md) → "Option 3: Manual Setup"

---

## Stuck?

1. **Docker won't start?** You need Docker Desktop installed: https://www.docker.com/products/docker-desktop
2. **MAMP not connecting to DB?** Use phpMyAdmin instead: http://localhost:8888/phpmyadmin
3. **Something else?** See [INSTALL.md](INSTALL.md) Troubleshooting section

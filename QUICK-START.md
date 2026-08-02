# Quick Start

Three ways in, fastest first. Pick one.

| Route | Time | Needs | Good for |
|---|---|---|---|
| [SQLite](#1-sqlite-nothing-to-install) | ~1 min | PHP 8.2+ | Trying it out, development |
| [Guided installer](#2-guided-installer) | ~5 min | PHP 8.2+ | A real install on your own machine |
| [OS installer scripts](#3-os-installer-scripts) | ~10 min | Admin rights | A machine with nothing installed yet |

Deploying to shared hosting instead? Go straight to [INSTALL.md](INSTALL.md).

---

## 1. SQLite, nothing to install

The fastest way to see the software running. Everything you need is PHP 8.2+.

```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
php -S localhost:8080 -t public/
```

The site is live at `http://localhost:8080`. A SQLite database is created for you
at `storage/gallery.sqlite`. No MySQL server, no config file to edit.

This is for local use. MySQL is the supported production database.

---

## 2. Guided installer

Interactive, asks what it needs, works the same on macOS, Linux and Windows.

```bash
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery
php install.php
```

It will:
- check your PHP version and extensions
- ask for database details, or use sensible defaults
- create the database and import the schema
- generate `config/config.php` with freshly generated secure keys
- print the URL to open next

---

## 3. OS installer scripts

For a machine that does not have PHP or MySQL yet. These install dependencies,
so they need administrator rights.

### macOS
```bash
bash install-mac.sh
```
Installs Homebrew, PHP 8.2, MySQL and Git, runs the setup wizard, starts the dev
server and opens your browser.

### Linux
```bash
bash install-linux.sh
```

If the script will not run: `chmod +x install-linux.sh` first.

### Windows

PowerShell, as Administrator:
```powershell
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force
.\install-windows.ps1
```

Or right-click `install-windows.bat` and choose "Run as administrator".

Installs Chocolatey, PHP 8.2, MySQL Server and Git, then the same wizard as above.

---

## Local development with Docker

A `Dockerfile` and `docker-compose.yml` are included so contributors can mirror
the PHP and MySQL versions without installing them:

```bash
docker-compose up
```

Then open `http://localhost:8080`.

Docker is supported for local development only. It is not the deployment target;
that is ordinary shared hosting with Apache and cron.

---

## First run

1. Open `http://localhost:8080/admin/setup`
2. Create your admin account
3. Set your gallery name and currency
4. Add Stripe keys if you want to sell, or skip and add them later
5. Configure email if you want download links delivered, or skip

Then, to see a gallery end to end:

1. Create an event under `/admin/events`
2. Upload photos
3. Tag them by kart number and class, which is what customers filter on
4. Open the home page and click into your event

---

## Stopping the server

- macOS and Linux: `Ctrl+C` in the terminal
- Windows: close the "Photo Gallery Server" window

---

## If something is wrong

Run the diagnostic first. It checks your environment, permissions and database
and tells you what to fix:

```bash
php verify-setup.php
```

Common problems:

**PHP not found after installing it.** Restart your terminal. On macOS you can
also run `eval "$(brew shellenv)"`.

**MySQL connection refused.** Start it: `brew services start mysql` on macOS,
`sudo systemctl start mysql` on Linux.

For anything else, see [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md).

---

## Next

- [INSTALL.md](INSTALL.md) for shared hosting, step by step
- [docs/SELF_HOSTED.md](docs/SELF_HOSTED.md) for hosting options and what they cost
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for the pre-launch security checklist
- [docs/architecture.md](docs/architecture.md) for schema and request flows
- [config/config.example.php](config/config.example.php) for every setting

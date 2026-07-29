# Quick Start Guide — Automatic Installation

One-command setup for Windows, macOS, and Linux.

## macOS

```bash
# Clone the repository (if you haven't already)
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery

# Run the installer
bash install-mac.sh
```

**What it does:**
- Installs Homebrew (if missing)
- Installs PHP 8.2
- Installs MySQL
- Installs Git
- Runs the PHP setup wizard
- Starts the development server
- Opens your browser to the setup page

## Linux

```bash
# Clone the repository (if you haven't already)
git clone https://github.com/hazzaattemptscoding/open-source-gallery.git
cd open-source-gallery

# Run the installer
bash install-linux.sh
```

**What it does:**
- Detects your package manager (apt-get, yum, or pacman)
- Installs PHP 8.2 with required extensions
- Installs MySQL Server
- Installs Git
- Runs the PHP setup wizard
- Starts the development server
- Opens your browser to the setup page

## Windows

### Option 1: PowerShell (Recommended)

```powershell
# Run PowerShell as Administrator, then:
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force
.\install-windows.ps1
```

### Option 2: Batch File

```batch
# Right-click install-windows.bat and select "Run as administrator"
```

**What it does:**
- Installs Chocolatey package manager
- Installs PHP 8.2
- Installs MySQL Server
- Installs Git
- Runs the PHP setup wizard
- Starts the development server
- Opens your browser to the setup page

## After Installation

1. **Admin Setup Page** will open automatically in your browser
2. **Create your admin account:**
   - Email: your-email@example.com
   - Password: choose a strong password
3. **Configure your gallery:**
   - Gallery name
   - Currency (GBP, USD, EUR, etc.)
   - Email settings (optional)
   - Stripe keys (optional, for sales)
4. **Start uploading photos!**

## Stopping the Server

- **macOS/Linux:** Press `Ctrl+C` in the terminal
- **Windows:** Close the "Photo Gallery Server" window

## Troubleshooting

### PHP not found after installation
```bash
# macOS: Restart your terminal or run
eval "$(brew shellenv)"

# Linux: Restart your terminal
```

### MySQL connection error
```bash
# macOS: Start MySQL manually
brew services start mysql

# Linux: Start MySQL manually
sudo systemctl start mysql
```

### Permission denied on shell script
```bash
chmod +x install-mac.sh
bash install-mac.sh
```

## Manual Installation

If you prefer to install dependencies manually, see [INSTALL.md](INSTALL.md).

## Next Steps

Once your admin account is set up:

1. **Upload photos:** Go to `/admin/events` and create an event
2. **Configure Stripe** (optional): For selling photos
3. **Customize branding:** `/admin/customize`
4. **Configure email** (optional): For download links

## Documentation

- **Architecture:** [docs/architecture.md](docs/architecture.md)
- **API Reference:** [docs/API.md](docs/API.md)
- **Configuration:** [config/config.example.php](config/config.example.php)

---

**Need help?** Run `php verify-setup.php` to diagnose issues.

#!/bin/bash
set -e

# Local development install for macOS (zero manual steps)
# This is for local development only. Production install uses php install.php

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Open Source Gallery - Local Dev Setup"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check PHP
if ! command -v php &> /dev/null; then
    echo "[✗] PHP not found. Install via: brew install php"
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1)
echo "[✓] $PHP_VERSION"

# Check for required extensions
for ext in pdo_sqlite json; do
    if ! php -m | grep -q "$ext"; then
        echo "[✗] PHP extension '$ext' not found. Install with: brew install php"
        exit 1
    fi
done

# Check if in project root
if [ ! -f "public/index.php" ] || [ ! -d "app/lib" ]; then
    echo "[✗] Not in project root. Run this script from the gallery directory."
    exit 1
fi

echo "[✓] Project structure found"

# Create storage directory
mkdir -p storage
chmod 755 storage

# Run setup via PHP to use shared setup functions
php <<'PHPEOF'
<?php
// Load setup helpers
require_once __DIR__ . '/app/lib/dev_setup.php';

echo "\n[*] Detecting database...\n";
$dbConfig = dev_detect_database();

echo "[*] Generating configuration...\n";
$result = dev_generate_config($dbConfig);
$config = $result['config'];
$adminPassword = $result['adminPassword'];

echo "[*] Writing config/config.php\n";
dev_write_config($config);
echo "[✓] Config written\n";

echo "[*] Connecting to database...\n";
$pdo = dev_connect_db($config);

// Reset schema (local dev, safe to drop)
dev_reset_schema($pdo);

// Seed dummy data
dev_seed_dummy_data($pdo);

echo "\n[✓] Setup complete!\n";
PHPEOF

if [ $? -ne 0 ]; then
    echo "[✗] Setup failed. See errors above."
    exit 1
fi

URL="http://localhost:8000"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Starting development server..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Access the gallery:"
echo "  Public:  $URL"
echo "  Admin:   $URL/admin"
echo ""
echo "First-time setup:"
echo "  1. Open $URL/admin/setup in your browser"
echo "  2. Create your first admin account"
echo "  3. Enable two-factor authentication"
echo ""
echo "After login:"
echo "  - Add real Stripe keys: Admin → Settings → Payment"
echo "  - Configure email: Admin → Settings → Email"
echo "  - View sample photos: Admin → Manage Content"
echo "  - Press Ctrl+C to stop the server"
echo ""

cd public
php -S localhost:8000

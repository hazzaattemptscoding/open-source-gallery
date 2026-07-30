# Local development install for Windows (zero manual steps)
# This is for local development only. Production install uses php install.php

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "Open Source Gallery - Local Dev Setup" -ForegroundColor Cyan
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host ""

# Check PHP
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "[X] PHP not found. Download from https://windows.php.net/" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

$phpVersion = php -v | Select-Object -First 1
Write-Host "[+] $phpVersion" -ForegroundColor Green

# Check for required extensions
$extensions = @('pdo_sqlite', 'json')
foreach ($ext in $extensions) {
    $found = php -m | Select-String "$ext"
    if (-not $found) {
        Write-Host "[X] PHP extension '$ext' not found. Verify PHP installation." -ForegroundColor Red
        Read-Host "Press Enter to exit"
        exit 1
    }
}

# Check if in project root
if (-not (Test-Path "public\index.php") -or -not (Test-Path "app\lib")) {
    Write-Host "[X] Not in project root. Run this script from the gallery directory." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "[+] Project structure found" -ForegroundColor Green

# Create storage directory
if (-not (Test-Path "storage")) {
    New-Item -ItemType Directory -Path "storage" | Out-Null
}

# Run setup via PHP to use shared setup functions
$setupScript = @'
<?php
require_once __DIR__ . '/app/lib/dev_setup.php';

echo "\n[*] Detecting database...\n";
$dbConfig = dev_detect_database();

echo "[*] Generating configuration...\n";
$result = dev_generate_config($dbConfig);
$config = $result['config'];
$adminPassword = $result['adminPassword'];

echo "[*] Writing config/config.php\n";
dev_write_config($config);
echo "[+] Config written\n";

echo "[*] Connecting to database...\n";
$pdo = dev_connect_db($config);

dev_reset_schema($pdo);
dev_seed_dummy_data($pdo);

echo "\n[+] Setup complete!\n";
'@

$setupScript | php

if ($LASTEXITCODE -ne 0) {
    Write-Host "[X] Setup failed. See errors above." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

$url = "http://localhost:8000"

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "Starting development server..." -ForegroundColor Cyan
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host ""
Write-Host "Access the gallery:" -ForegroundColor Yellow
Write-Host "  Public:  $url" -ForegroundColor Cyan
Write-Host "  Admin:   $url/admin" -ForegroundColor Cyan
Write-Host ""
Write-Host "First-time setup:" -ForegroundColor Yellow
Write-Host "  1. Open $url/admin/setup in your browser" -ForegroundColor Cyan
Write-Host "  2. Create your first admin account" -ForegroundColor Cyan
Write-Host "  3. Enable two-factor authentication" -ForegroundColor Cyan
Write-Host ""
Write-Host "After login:" -ForegroundColor Yellow
Write-Host "  - Add real Stripe keys: Admin > Settings > Payment"
Write-Host "  - Configure email: Admin > Settings > Email"
Write-Host "  - View sample photos: Admin > Manage Content"
Write-Host "  - Press Ctrl+C to stop the server"
Write-Host ""

Set-Location public
php -S localhost:8000

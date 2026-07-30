# Photo Gallery Auto-Installer for Windows (PowerShell)
# This script checks for dependencies, installs if needed, and starts the app

# Check if running as admin
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "This script requires administrator privileges."
    Write-Host "Please run PowerShell as Administrator and execute this script again."
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "📸 Photo Gallery Installer for Windows" -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Green
Write-Host ""

# Set execution policy for this session
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force

# Check and install Chocolatey
if (-not (Get-Command choco -ErrorAction SilentlyContinue)) {
    Write-Host "Installing Chocolatey package manager..." -ForegroundColor Yellow
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
    iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
}

# Check and install PHP
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "Installing PHP 8.2..." -ForegroundColor Yellow
    choco install php --version=8.2.0 -y
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
} else {
    Write-Host "✓ PHP already installed" -ForegroundColor Green
}

# Check and install MySQL
if (-not (Get-Command mysql -ErrorAction SilentlyContinue)) {
    Write-Host "Installing MySQL Server..." -ForegroundColor Yellow
    choco install mysql -y
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
} else {
    Write-Host "✓ MySQL already installed" -ForegroundColor Green
}

# Check and install Git
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Host "Installing Git..." -ForegroundColor Yellow
    choco install git -y
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
} else {
    Write-Host "✓ Git already installed" -ForegroundColor Green
}

Write-Host ""
Write-Host "✓ All dependencies installed" -ForegroundColor Green
Write-Host ""

# Get the directory where the script is located
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir

# Run the PHP installer
Write-Host "Running PHP installer..." -ForegroundColor Yellow
Write-Host ""
php install.php

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "❌ Installer failed. Check the errors above." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "✓ Installation complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Starting Photo Gallery..." -ForegroundColor Green
Write-Host ""

# Start PHP development server
$Process = Start-Process -FilePath "php" -ArgumentList "-S", "localhost:8080" -PassThru -WindowStyle Normal

Write-Host "✓ Server started on http://localhost:8080" -ForegroundColor Green
Write-Host ""
Write-Host "Opening browser in 2 seconds..." -ForegroundColor Yellow
Start-Sleep -Seconds 2

# Open browser
Start-Process "http://localhost:8080/admin/setup"

Write-Host ""
Write-Host "The server is running. Close the server window to stop." -ForegroundColor Yellow
Write-Host ""
Read-Host "Press Enter to exit"

# Clean up the process
Stop-Process -Id $Process.Id -Force -ErrorAction SilentlyContinue

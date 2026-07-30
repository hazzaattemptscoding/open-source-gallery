@echo off
REM Photo Gallery Auto-Installer for Windows
REM This script checks for dependencies, installs if needed, and starts the app

setlocal enabledelayedexpansion

echo.
echo 📸 Photo Gallery Installer for Windows
echo ======================================
echo.

REM Check if running as admin
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo This script requires administrator privileges.
    echo Please right-click and select "Run as administrator"
    pause
    exit /b 1
)

REM Check and install Chocolatey
where choco >nul 2>&1
if %errorLevel% neq 0 (
    echo Installing Chocolatey package manager...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "iex ((New-Object System.Net.ServicePointManager).SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072); iex ((New-Object Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))"
)

REM Refresh PATH
call refreshenv

REM Check and install PHP
where php >nul 2>&1
if %errorLevel% neq 0 (
    echo Installing PHP 8.2...
    choco install php --version=8.2.0 -y
    call refreshenv
) else (
    echo ✓ PHP already installed
)

REM Check and install MySQL
where mysql >nul 2>&1
if %errorLevel% neq 0 (
    echo Installing MySQL Server...
    choco install mysql -y
    call refreshenv
) else (
    echo ✓ MySQL already installed
)

REM Check and install Git
where git >nul 2>&1
if %errorLevel% neq 0 (
    echo Installing Git...
    choco install git -y
    call refreshenv
) else (
    echo ✓ Git already installed
)

echo.
echo ✓ All dependencies installed
echo.

REM Get the directory where the script is located
cd /d "%~dp0"

REM Run the PHP installer
echo Running PHP installer...
echo.
php install.php

if %errorLevel% neq 0 (
    echo.
    echo ❌ Installer failed. Check the errors above.
    pause
    exit /b 1
)

echo.
echo ✓ Installation complete!
echo.
echo Starting Photo Gallery...
echo.

REM Get the current directory
for /f "tokens=*" %%i in ('cd') do set CURRENT_DIR=%%i

REM Start PHP development server in background
start "Photo Gallery Server" cmd /k "php -S localhost:8080"

REM Wait for server to start
timeout /t 2 /nobreak

echo ✓ Server started on http://localhost:8080
echo.
echo Opening browser in 2 seconds...
timeout /t 2 /nobreak

REM Open browser
start http://localhost:8080/admin/setup

echo.
echo The server is running. Close the server window to stop.
pause

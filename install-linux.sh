#!/bin/bash

# Photo Gallery Auto-Installer for Linux
# This script checks for dependencies, installs if needed, and starts the app

set -e

echo "📸 Photo Gallery Installer for Linux"
echo "====================================="
echo ""

# Color codes
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Detect package manager
if command -v apt-get &> /dev/null; then
    PKG_MANAGER="apt-get"
    INSTALLER="sudo apt-get install -y"
    UPDATE="sudo apt-get update"
elif command -v yum &> /dev/null; then
    PKG_MANAGER="yum"
    INSTALLER="sudo yum install -y"
    UPDATE="sudo yum check-update"
elif command -v pacman &> /dev/null; then
    PKG_MANAGER="pacman"
    INSTALLER="sudo pacman -S --noconfirm"
    UPDATE="sudo pacman -Sy"
else
    echo -e "${RED}❌ Unsupported package manager. Please install PHP 8.1+ and MySQL manually.${NC}"
    exit 1
fi

echo "Using package manager: $PKG_MANAGER"
echo ""

# Update package lists
echo -e "${YELLOW}Updating package lists...${NC}"
$UPDATE || true

# Check and install PHP
if ! command -v php &> /dev/null; then
    echo -e "${YELLOW}Installing PHP 8.2 and extensions...${NC}"
    if [ "$PKG_MANAGER" = "apt-get" ]; then
        sudo add-apt-repository ppa:ondrej/php -y 2>/dev/null || true
        $UPDATE
        $INSTALLER php8.2 php8.2-cli php8.2-mysql php8.2-curl php8.2-gd
    elif [ "$PKG_MANAGER" = "yum" ]; then
        $INSTALLER php php-cli php-mysql php-curl php-gd
    elif [ "$PKG_MANAGER" = "pacman" ]; then
        $INSTALLER php php-sqlite
    fi
else
    echo -e "${GREEN}✓ PHP already installed${NC}"
fi

# Check and install MySQL
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}Installing MySQL Server...${NC}"
    if [ "$PKG_MANAGER" = "apt-get" ]; then
        $INSTALLER mysql-server
        sudo systemctl start mysql
    elif [ "$PKG_MANAGER" = "yum" ]; then
        $INSTALLER mysql-server
        sudo systemctl start mysqld
    elif [ "$PKG_MANAGER" = "pacman" ]; then
        $INSTALLER mysql
        sudo systemctl start mysqld
    fi
else
    echo -e "${GREEN}✓ MySQL already installed${NC}"
    # Ensure MySQL is running
    sudo systemctl start mysql 2>/dev/null || sudo systemctl start mysqld 2>/dev/null || true
fi

# Check and install Git
if ! command -v git &> /dev/null; then
    echo -e "${YELLOW}Installing Git...${NC}"
    $INSTALLER git
else
    echo -e "${GREEN}✓ Git already installed${NC}"
fi

echo ""
echo -e "${GREEN}✓ All dependencies installed${NC}"
echo ""

# Get the directory where the script is located
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

# Run the PHP installer
echo -e "${YELLOW}Running PHP installer...${NC}"
php install.php

echo ""
echo -e "${GREEN}✓ Installation complete!${NC}"
echo ""
echo "Starting Photo Gallery..."
echo ""

# Start PHP development server
php -S localhost:8080 &
PHP_PID=$!

echo -e "${GREEN}✓ Server started on http://localhost:8080${NC}"
echo ""
echo "Opening browser in 2 seconds..."
sleep 2

# Open in default browser
if command -v xdg-open &> /dev/null; then
    xdg-open "http://localhost:8080/admin/setup"
elif command -v gnome-open &> /dev/null; then
    gnome-open "http://localhost:8080/admin/setup"
else
    echo "Please open http://localhost:8080/admin/setup in your browser"
fi

echo ""
echo "Press Ctrl+C to stop the server"
wait $PHP_PID

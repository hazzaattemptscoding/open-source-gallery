#!/bin/bash

# Photo Gallery Auto-Installer for macOS
# This script checks for dependencies, installs if needed, and starts the app

set -e

echo "📸 Photo Gallery Installer for macOS"
echo "======================================"
echo ""

# Color codes
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if Homebrew is installed
if ! command -v brew &> /dev/null; then
    echo -e "${YELLOW}Installing Homebrew...${NC}"
    /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
fi

# Check and install PHP
if ! command -v php &> /dev/null; then
    echo -e "${YELLOW}Installing PHP 8.2...${NC}"
    brew install php@8.2
    brew link php@8.2
else
    echo -e "${GREEN}✓ PHP already installed${NC}"
fi

# Check and install MySQL
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}Installing MySQL...${NC}"
    brew install mysql
    brew services start mysql
    # Create default database
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS photo_gallery;" 2>/dev/null || true
else
    echo -e "${GREEN}✓ MySQL already installed${NC}"
    # Ensure MySQL is running
    brew services start mysql 2>/dev/null || true
fi

# Check and install Git
if ! command -v git &> /dev/null; then
    echo -e "${YELLOW}Installing Git...${NC}"
    brew install git
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
open "http://localhost:8080/admin/setup"

echo ""
echo "Press Ctrl+C to stop the server"
wait $PHP_PID

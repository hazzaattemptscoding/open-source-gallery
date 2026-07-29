#!/bin/bash
set -e

echo "================================"
echo "PowerMedia Gallery Setup"
echo "================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Detect OS
if [[ "$OSTYPE" == "darwin"* ]]; then
    OS="mac"
    HTDOCS_HINT="~/Applications/MAMP/htdocs"
elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS="linux"
    HTDOCS_HINT="/var/www/html or /home/user/public_html"
else
    OS="unknown"
fi

echo -e "${BLUE}Detected OS: $OS${NC}"
echo ""

# Step 1: Check PHP
echo -e "${BLUE}Checking PHP...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗ PHP not found. Install PHP 8.2+ first.${NC}"
    exit 1
fi
PHP_VERSION=$(php -v | head -n 1)
echo -e "${GREEN}✓ $PHP_VERSION${NC}"
echo ""

# Step 2: Check required extensions
echo -e "${BLUE}Checking PHP extensions...${NC}"
REQUIRED_EXTS=("pdo_mysql" "gd" "zip" "exif")
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -m | grep -q "$ext"; then
        echo -e "${GREEN}✓ $ext${NC}"
    else
        echo -e "${RED}✗ Missing: $ext${NC}"
        echo "   Install with: apt-get install php8.2-$ext (Linux) or MAMP (Mac)"
        exit 1
    fi
done
echo ""

# Step 3: Create config
echo -e "${BLUE}Creating config file...${NC}"
if [ ! -f config/config.php ]; then
    # Generate random keys
    HMAC_KEY=$(php -r "echo bin2hex(random_bytes(32));")
    CRON_SECRET=$(php -r "echo bin2hex(random_bytes(32));")

    cat > config/config.php << 'CONFIGEOF'
<?php
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'photo_gallery',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name'          => 'PowerMedia Gallery',
        'base_url'      => 'http://localhost:8888',
        'support_email' => 'you@example.com',
    ],
    'currency' => 'GBP',
    'branding' => ['theme' => 'podium-ink'],
    'stripe' => [
        'publishable_key' => '',
        'secret_key'      => '',
        'webhook_secret'  => '',
    ],
    'smtp' => [
        'host'       => '',
        'port'       => 587,
        'user'       => '',
        'pass'       => '',
        'from_email' => '',
        'from_name'  => '',
    ],
    'discounts' => [
        10 => 0.15,
        20 => 0.20,
        50 => 0.25,
    ],
    'security' => [
        'hmac_key'    => 'HMAC_PLACEHOLDER',
        'cron_secret' => 'CRON_PLACEHOLDER',
    ],
    'timezone' => 'UTC',
];
CONFIGEOF

    # Replace placeholders
    sed -i.bak "s/HMAC_PLACEHOLDER/$HMAC_KEY/" config/config.php
    sed -i.bak "s/CRON_PLACEHOLDER/$CRON_SECRET/" config/config.php
    rm -f config/config.php.bak

    echo -e "${GREEN}✓ Created config/config.php${NC}"
else
    echo -e "${GREEN}✓ config/config.php already exists${NC}"
fi
echo ""

# Step 4: Create storage directories
echo -e "${BLUE}Creating storage directories...${NC}"
mkdir -p storage/hires storage/zips storage/tmp
mkdir -p public/media/d
chmod -R 755 storage public/media 2>/dev/null || true
echo -e "${GREEN}✓ Created storage folders${NC}"
echo ""

# Step 5: Database setup
echo -e "${BLUE}Database setup...${NC}"
echo "Checking MySQL connection..."

# Try to detect mysql
if command -v mysql &> /dev/null; then
    MYSQL_CMD="mysql"
elif command -v /usr/local/mysql/bin/mysql &> /dev/null; then
    MYSQL_CMD="/usr/local/mysql/bin/mysql"
elif command -v /opt/homebrew/bin/mysql &> /dev/null; then
    MYSQL_CMD="/opt/homebrew/bin/mysql"
else
    echo -e "${RED}✗ MySQL client not found in PATH${NC}"
    echo "   You'll need to import migrations manually:"
    echo "   1. Open http://localhost:8888/phpmyadmin"
    echo "   2. Create database: photo_gallery"
    echo "   3. Import: migrations/001_initial_schema.sql"
    echo "   4. Import: migrations/002_add_media_type.sql"
    MYSQL_CMD=""
fi

if [ ! -z "$MYSQL_CMD" ]; then
    # Try connection
    if $MYSQL_CMD -u root -proot -e "SELECT 1;" &> /dev/null; then
        echo -e "${GREEN}✓ MySQL connected${NC}"

        # Create database
        echo "Creating database..."
        $MYSQL_CMD -u root -proot -e "CREATE DATABASE IF NOT EXISTS photo_gallery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
        echo -e "${GREEN}✓ Database created${NC}"

        # Import migrations
        echo "Importing schema..."
        if [ -f "migrations/001_initial_schema.sql" ]; then
            $MYSQL_CMD -u root -proot photo_gallery < migrations/001_initial_schema.sql
            echo -e "${GREEN}✓ Imported 001_initial_schema.sql${NC}"
        fi

        if [ -f "migrations/002_add_media_type.sql" ]; then
            $MYSQL_CMD -u root -proot photo_gallery < migrations/002_add_media_type.sql
            echo -e "${GREEN}✓ Imported 002_add_media_type.sql${NC}"
        fi
    else
        echo -e "${RED}✗ Could not connect to MySQL (root/root)${NC}"
        echo "   Using phpMyAdmin to import manually (see above)"
    fi
fi
echo ""

# Step 6: Summary
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Setup complete!${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo "Next steps:"
echo "1. Make sure Apache and MySQL are running"
echo "2. Edit config/config.php if needed (database host/port/credentials)"
echo "3. Visit: http://localhost:8888/admin/setup"
echo "4. Create your admin account"
echo "5. Start uploading photos!"
echo ""
echo "If database import failed, use phpMyAdmin:"
echo "  http://localhost:8888/phpmyadmin"
echo ""

# Testing Environment Setup Guide

This guide helps you set up a local testing environment to verify the gallery application before deployment.

---

## 1. Local Environment Requirements

### Minimum Requirements
- **PHP 8.2+** (same as production)
- **MySQL 5.7+** or **MariaDB 10.4+**
- **Composer** (for PHP dependencies)
- **Browser** (Chrome, Safari, Firefox - latest versions)
- **Mobile simulator** or device (iOS Safari, Android Chrome) for responsive testing

### Recommended Setup (macOS)
```bash
# Install Homebrew if not already installed
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Install PHP 8.2
brew install php@8.2

# Install MySQL
brew install mysql
brew services start mysql

# Install Composer
brew install composer

# Start MySQL server
mysql -u root
# In MySQL: CREATE DATABASE gallery_test;
# CREATE USER 'test'@'localhost' IDENTIFIED BY 'password';
# GRANT ALL PRIVILEGES ON gallery_test.* TO 'test'@'localhost';
```

### Recommended Setup (Windows/Linux)
Use Docker containers or your system package manager:
```bash
# Docker: Run MySQL in container
docker run --name mysql -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -p 3306:3306 mysql:8.0

# Or use system package manager
# Ubuntu/Debian: sudo apt-get install php8.2 mysql-server php8.2-mysql
# CentOS/RHEL: sudo dnf install php8.2 mysql-server php8.2-mysqlnd
```

---

## 2. Application Setup

### Clone & Install Dependencies
```bash
# Navigate to project directory
cd /path/to/open-source-gallery

# Install PHP dependencies
composer install

# Create .env.test for local testing
cat > .env.test << 'EOF'
TEST_DB_HOST=localhost
TEST_DB_USER=root
TEST_DB_PASSWORD=
TEST_DB_NAME=gallery_test
EOF
```

### Initialize Database
```bash
# Create the test database
mysql -u root < cron/init-db.php

# Or manually:
mysql -u root -e "CREATE DATABASE gallery_test;"
mysql -u root gallery_test < docs/schema.sql  # if schema.sql exists
```

### Configuration
1. Copy `config/config.php.example` to `config/config.php` (or create from scratch)
2. Set test values:
   ```php
   define('BUSINESS_NAME', 'Test Gallery');
   define('APP_ROOT', __DIR__ . '/..');
   define('DB_HOST', 'localhost');
   define('DB_USER', 'test');
   define('DB_PASS', 'password');
   define('DB_NAME', 'gallery_test');
   define('STRIPE_PUBLIC_KEY', 'pk_test_...');
   define('STRIPE_SECRET_KEY', 'sk_test_...');
   ```

---

## 3. Running Automated Tests

### Run All Tests
```bash
./vendor/bin/phpunit
```

Expected output:
```
PHPUnit 11.5.56
21 tests, 32 assertions
14 passed, 7 skipped
```

### Run Specific Test Suite
```bash
# Bulk operations tests
./vendor/bin/phpunit tests/integration/BulkOperationsTest.php

# Admin authentication tests
./vendor/bin/phpunit tests/integration/AdminAuthTest.php

# Search tests
./vendor/bin/phpunit tests/integration/SearchTest.php
```

### Run With Verbose Output
```bash
./vendor/bin/phpunit --verbose --debug
```

### Known Test Results
- **14 tests pass**: Core functionality verified
- **7 tests skipped**: Blocked by pre-existing schema issues
  - `photos.price_pence` column missing (affects search tests)
  - `bulk_change_status()` vocabulary mismatch (affects bulk status test)

---

## 4. Local Web Server Setup

### Built-in PHP Server (for quick testing)
```bash
# Run from project root
cd /path/to/open-source-gallery
php -S localhost:8000

# Access at: http://localhost:8000
```

### Apache (for production-like testing)
```bash
# Configure virtual host to point to /public directory
# Then restart Apache

sudo systemctl restart apache2  # or httpd
```

### Docker (for isolated environment)
```bash
docker-compose up  # if docker-compose.yml exists
```

---

## 5. Cron Job Testing

The image tiering job requires testing the 7-day lifecycle. Since you can't wait 7 days, test the job directly:

### Test Job Queue Creation
After uploading a photo, verify a `derivative` job was created:
```bash
mysql -u test -p gallery_test -e "SELECT * FROM jobs WHERE type='derivative' ORDER BY created_at DESC LIMIT 1\G"
```

Expected output:
```
*************************** 1. row ***************************
           id: 1
         type: derivative
      payload: {"photo_id": 123}
       status: pending
      created_at: 2025-07-29 12:34:56
      run_after: 2025-07-29 12:34:56
    locked_at: NULL
     attempts: 0
```

### Manually Run Cron Job
```bash
# Run once
php cron/run.php

# Or run multiple times to process all jobs
for i in {1..10}; do php cron/run.php; sleep 1; done
```

### Verify Derivative Generation
After running cron, check that derivatives were created:
```bash
# List generated derivatives
ls -lh public/media/d/

# Expected files for each photo:
# {token}-400.jpg   (watermarked if enabled)
# {token}-800.jpg   (watermarked if enabled)
# {token}-1600.jpg  (watermarked if enabled)
```

### Test Cleanup Job (7-day tiering)
```bash
# Simulate creating a cleanup job for a photo 7+ days old
mysql -u test -p gallery_test << 'EOF'
INSERT INTO jobs (type, payload, status, run_after) 
VALUES ('cleanup', '{"photo_id": 123}', 'pending', NOW());
EOF

# Run cron
php cron/run.php

# Verify 1600px file was deleted
ls -lh public/media/d/ | grep "{token}-1600"
# Should NOT find the file
```

---

## 6. Testing Workflow

### Step-by-Step Testing
1. **Create test event**: Visit `/admin/` → Events → Create
2. **Create test session**: Under event, create a photo session
3. **Upload test photo**:
   - Go to Upload page
   - Choose a test image (~2-5 MB JPG)
   - Upload
   - Verify it appears with "processing" status
4. **Run derivative job**: `php cron/run.php`
5. **Verify derivatives**: Check `public/media/d/` for generated files
6. **Test gallery**: Visit `/` to see published event
7. **Test search**: Try `/search?q=photo` (if `/search` works without photos.price_pence error)
8. **Test cart**: Add photos to cart
9. **Test checkout**: Use Stripe test card: `4242 4242 4242 4242`

---

## 7. Mobile & Browser Testing

### iOS Testing (on macOS)
```bash
# Use Xcode Simulator
open -a Simulator

# Or configure Safari to show responsive design
Safari → Develop → Enter responsive design mode
```

### Android Testing
- Use Chrome DevTools Device Emulation (F12 → Device Toggle)
- Or Android Studio Emulator

### Cross-Browser Testing
- **Chrome**: DevTools → Device Emulation (responsive mode)
- **Safari**: Develop menu → Enter responsive design mode
- **Firefox**: Responsive design mode (Ctrl+Shift+M or Cmd+Shift+M)

Test at these breakpoints:
- **375px** (iPhone 6/7/8)
- **390px** (iPhone 12/13/14)
- **768px** (iPad)
- **1024px+** (Desktop)

---

## 8. Database Debugging

### View Test Database
```bash
mysql -u test -p gallery_test

# Useful queries:
SHOW TABLES;
SELECT * FROM photos LIMIT 5;
SELECT * FROM jobs;
SELECT * FROM events;
```

### Clear Test Data
```bash
mysql -u test -p gallery_test << 'EOF'
DROP DATABASE gallery_test;
CREATE DATABASE gallery_test;
EOF

# Then run migrations again
php cron/init-db.php
```

### Monitor Slow Queries
```bash
# Enable slow query log (in MySQL config or at runtime)
mysql -u root -e "SET GLOBAL slow_query_log='ON';"
mysql -u root -e "SET GLOBAL long_query_time=1;"

# View slow query log
tail -f /var/log/mysql/slow.log
```

---

## 9. Performance Testing

### Page Load Times
Use browser DevTools Network tab:
1. Open Developer Tools (F12)
2. Go to Network tab
3. Reload page
4. Check:
   - **DOMContentLoaded**: Should be < 1.5s
   - **Total load time**: Should be < 3s
   - **Largest image download**: Should be < 1s

### Cache Verification
1. Load gallery homepage
2. Open DevTools → Application → Local Storage
3. Check `_cache_*` keys (verify caching is working)
4. Refresh gallery
5. Check Network tab: Facet queries should be cached (HTTP 304 or from cache)

### Query Performance
```bash
# Enable MySQL query log
mysql -u root -e "SET GLOBAL log='ON';"
mysql -u root -e "SET GLOBAL log_output='TABLE';"

# Run tests
php cron/run.php

# View query log
mysql -u root -e "SELECT * FROM mysql.general_log LIMIT 20\G" | grep "SELECT\|UPDATE"

# Reset log
mysql -u root -e "TRUNCATE TABLE mysql.general_log;"
```

---

## 10. Security Testing Checklist

- [ ] **CSRF tokens**: Forms include hidden CSRF token
- [ ] **SQL injection**: Try SQL payload in search: `' OR '1'='1`
  - Expected: No results, no error
- [ ] **File upload security**: Try uploading `.exe` file
  - Expected: Rejected (file type validation)
- [ ] **XSS prevention**: Upload photo with `<script>` in filename
  - Expected: Filename sanitized, no script execution
- [ ] **Session security**: Login, check session cookie flags
  - Expected: HttpOnly flag set (no JavaScript access)
- [ ] **Rate limiting**: Make 100 login attempts in 1 minute
  - Expected: IP gets temporarily blocked after N attempts

---

## 11. Troubleshooting

### MySQL Connection Refused
```bash
# Check if MySQL is running
mysql -u root -e "SELECT 1;"

# Start MySQL
brew services start mysql  # macOS
sudo systemctl start mysql  # Linux
```

### PHP Missing Extensions
```bash
# Check installed extensions
php -m

# Install missing extension (e.g., pdo_mysql)
brew install php@8.2-pdo_mysql  # macOS
# Or rebuild PHP with required extensions
```

### Cron Job Stuck (locked)
```bash
# Clear stuck lock
mysql -u test -p gallery_test -e "UPDATE jobs SET locked_at=NULL;"

# Or check lock status
mysql -u test -p gallery_test -e "SELECT GET_LOCK('pm_cron', 1);"
```

### Test Database Already Exists
```bash
# Drop existing test database
mysql -u root -e "DROP DATABASE gallery_test;"

# Recreate
php cron/init-db.php
```

---

## 12. Continuous Testing (Local Development Loop)

### Watch for Changes
```bash
# Install watchman or fswatch for file change detection
brew install watchman  # macOS

# Run tests on every change
watchman-make -p 'app/**/*.php' 'tests/**/*.php' -r './vendor/bin/phpunit'
```

### Git Pre-commit Hook
```bash
# Create .git/hooks/pre-commit
#!/bin/bash
./vendor/bin/phpunit || exit 1
```

---

## 13. Success Criteria

Your local testing environment is ready when:
- ✅ PHPUnit runs: `./vendor/bin/phpunit` shows 14 pass, 7 skip
- ✅ Cron runs: `php cron/run.php` processes jobs without errors
- ✅ Web server: Browser loads `http://localhost:8000` with no errors
- ✅ Database: `mysql -u test -p gallery_test` connects without errors
- ✅ Mobile: Device emulator shows responsive layout at all breakpoints
- ✅ Images: Derivatives exist in `public/media/d/` after cron run

---

## Next Steps

1. **Run automated tests**: `./vendor/bin/phpunit`
2. **Start web server**: `php -S localhost:8000`
3. **Follow PHASE-3-TESTING-GUIDE.md**: Execute the manual testing checklist
4. **Document findings**: Screenshot issues, note exact reproduction steps
5. **Report blockers**: If you hit the `photos.price_pence` or status vocab issues, document them

Good luck! 🚀

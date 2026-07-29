# Phase 3 Testing - Quick Start

**Goal**: Verify responsive design, form validation, cron jobs, and end-to-end workflows on real devices.

---

## 1. Pre-Testing Checklist (5 min setup)

```bash
# Install dependencies
composer install

# Create test database config
cat > .env.test << 'EOF'
TEST_DB_HOST=localhost
TEST_DB_USER=root
TEST_DB_PASSWORD=
TEST_DB_NAME=gallery_test
EOF

# Start web server
php -S localhost:8000 &

# Start MySQL (if not already running)
brew services start mysql  # macOS
# or: sudo systemctl start mysql  # Linux
```

---

## 2. Run Automated Tests (2 min)

```bash
./vendor/bin/phpunit
```

Expected: **14 pass, 7 skip** ✅

---

## 3. Device Testing (60-90 min)

### Setup
- [ ] Open `http://localhost:8000` in target browser/device
- [ ] Open DevTools (F12) for error checking
- [ ] Create test event: `Admin` → `Events` → `Create`
- [ ] Create test session under event
- [ ] Upload 2-3 test photos (2-5 MB each)

### Mobile Responsiveness (375px - 1024px+)
| Screen | Test | Expected Result |
|--------|------|-----------------|
| 375px | Gallery grid | Single column |
| 375px | Cart button | Accessible, not cut off |
| 768px | Gallery grid | 2 columns |
| 1024px | Gallery grid | 3-4 columns with spacing |

**Test**: Resize browser window or use device emulation (DevTools)

### Browser Compatibility
- [ ] **Chrome**: Latest version, all features work
- [ ] **Safari**: Full-width images, no layout issues
- [ ] **Firefox**: Cart/checkout flow completes

### Form Validation
| Form | Test | Expected |
|------|------|----------|
| Login | Wrong password | Show "Invalid credentials" |
| Login | Empty email | Reject on submit |
| Event | Duplicate slug | Database constraint error |
| Upload | Upload .exe | Reject (file type) |
| Upload | Try .jpg | Accept, status="processing" |

### Workflow Tests
1. **Upload → Derivative → Live**
   - [ ] Upload photo → status="processing"
   - [ ] Run `php cron/run.php`
   - [ ] Check `public/media/d/{token}-*.jpg` exists
   - [ ] Check status="live" in database

2. **Cart → Checkout → Download**
   - [ ] Add 2-3 photos to cart
   - [ ] Click Checkout → Stripe redirects
   - [ ] Use test card: `4242 4242 4242 4242`, any future date
   - [ ] Verify receipt email with download link
   - [ ] Download file, verify watermark removed

3. **Search (if `/search` works)**
   - [ ] Search by filename: "photo" → results appear
   - [ ] Filter by event → results update
   - [ ] Try single-char search: "a" → no results (min 2 chars)

### Performance Checks
- [ ] Homepage loads in < 2s (DevTools Network tab)
- [ ] Images load progressively (scroll should be smooth)
- [ ] No JavaScript errors in Console (DevTools)
- [ ] No red HTTP errors (devTools Network tab)

---

## 4. Cron Job Testing (30 min)

### Create Cleanup Job Test
```bash
# 1. Upload a photo
#    - Note the photo ID (e.g., 123)

# 2. Manually create a cleanup job
mysql -u root gallery_test << 'EOF'
INSERT INTO jobs (type, payload, status, run_after) 
VALUES ('cleanup', '{"photo_id": 123}', 'pending', NOW());
EOF

# 3. Run cron
php cron/run.php

# 4. Verify 1600px file deleted
ls -lh public/media/d/{token}-*
# Should NOT show -1600.jpg, but should show -400.jpg and -800.jpg
```

### Monitor Job Queue
```bash
# Check pending jobs
mysql -u root gallery_test -e "SELECT * FROM jobs\G"

# Run cron multiple times
for i in {1..5}; do php cron/run.php; sleep 1; done

# Verify all jobs processed
mysql -u root gallery_test -e "SELECT COUNT(*) as pending FROM jobs WHERE status='pending';"
# Should return 0
```

---

## 5. Documentation While Testing

**If you find an issue:**
1. Screenshot the problem
2. Note the exact steps to reproduce
3. Check browser Console (F12) for errors
4. Note the URL/page name
5. Add to test results file (see template below)

**Test Results Template**
```markdown
## Device/Browser: [e.g., iPhone 14 / Safari 18]
**Date**: 2025-07-29

### PASS ✅
- [ ] Gallery responsive at all breakpoints
- [ ] Cart button always accessible
- [ ] Form validation works

### FAIL ❌
- [ ] Issue: Form accepts invalid email
  - URL: /admin/login
  - Steps: Enter "not-an-email" in email field, click submit
  - Expected: Show error "Invalid email format"
  - Actual: Form submits successfully
  - Severity: High
  - Screenshot: issue-1.png

### BLOCKED 🚫
- [ ] Search page (/search) - SQL error (photos.price_pence missing)
```

---

## 6. Known Blockers

### Blocker 1: Search Broken (`photos.price_pence` missing)
- **Affected**: `/search` endpoint
- **Error**: SQL error when running real search
- **Reason**: Column referenced but doesn't exist in schema
- **Decision needed**: Add column or remove per-photo pricing
- **Workaround**: Test filtering without search

### Blocker 2: Bulk Status Change Broken
- **Affected**: Bulk change status admin feature
- **Error**: ENUM truncation (draft/live/archived vs processing/live/hidden/failed)
- **Reason**: UI vocabulary doesn't match database schema
- **Decision needed**: Align vocabulary to one approach
- **Workaround**: Skip bulk status test for now

---

## 7. Troubleshooting

| Problem | Solution |
|---------|----------|
| MySQL connection refused | `brew services start mysql` |
| `localhost:8000` shows blank | `php -S localhost:8000` from project root |
| Photos not appearing in gallery | Run `php cron/run.php` to generate derivatives |
| Upload page shows error | Check `/var/log/apache2/error.log` for PHP errors |
| Test database locked | `mysql -u root -e "DROP DATABASE gallery_test;"` then recreate |

---

## 8. When Testing is Complete

```bash
# Document results
# 1. Open PHASE-3-TESTING-RESULTS.md (or create it)
# 2. List all pass/fail/blocked tests from each device

# Push branch
git push -u origin claude/plugin-skill-setup-y9v6kx

# Create PR (if ready)
# or wait for user instructions
```

---

## 9. Success Criteria

Phase 3 is COMPLETE when:
- ✅ Gallery responsive at 375px, 768px, 1024px+
- ✅ Form validation prevents invalid input
- ✅ Cart → Checkout → Download works end-to-end
- ✅ Cron jobs process without errors
- ✅ All test results documented
- ✅ Console shows no JavaScript errors
- ✅ Known blockers documented (not fixed)

---

## Helpful Links

- **Full Testing Guide**: `PHASE-3-TESTING-GUIDE.md`
- **Environment Setup**: `TESTING-ENVIRONMENT-SETUP.md`
- **Architecture**: `docs/architecture.md`
- **Test Results**: Running tests - `./vendor/bin/phpunit`

---

**Estimated Time**: 2-3 hours for thorough testing

**Next Step After Testing**: Create PR → Merge → Move to TIER 2.3 (GitHub Actions verification)

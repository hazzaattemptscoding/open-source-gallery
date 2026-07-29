# Phase 3 Testing Guide

**Status**: Ready for manual verification across devices and browsers  
**Date**: 2025-07-29  
**Coverage**: Mobile responsiveness, browser compatibility, form validation, cron jobs  
**Blocker**: `photos.price_pence` column missing (affects /search endpoint)

---

## 1. Device & Browser Matrix

### Mobile Devices (iOS)
- [ ] iPhone 6/7/8 (375px width) - Minimum supported screen size
- [ ] iPhone 12 (390px width) - Standard modern phone
- [ ] iPhone 14/15 (393px width) - Latest models
- [ ] iPad (768px width) - Tablet breakpoint
- [ ] iPad Pro (1024px width) - Large tablet

**For simulator testing:**
- macOS: Xcode Simulator (`open -a Simulator`, then Xcode → Open Developer Tool)
- Web: Chrome DevTools → Device Emulation (⌘ + Shift + M)

### Browsers (Desktop)
Test on latest versions (as of 2025-07-29):
- [ ] Chrome 130+
- [ ] Safari 18+ (on macOS)
- [ ] Firefox 133+

---

## 2. Responsive Design Checklist

### Gallery Page (`/`)
- [ ] **Mobile (375px)**: Gallery grid shows single column, photos stack vertically
- [ ] **Tablet (768px)**: Grid transitions to 2 columns
- [ ] **Desktop (1024px+)**: Grid shows 3-4 columns with proper spacing
- [ ] **Hero section**: Full-width image displays correctly on all sizes
- [ ] **Cart button**: Remains accessible at bottom on all screen sizes
- [ ] **Video section**: Separates cleanly from photo grid, proper aspect ratios maintained
- [ ] **Typography**: No text truncation, proper line heights on all sizes
- [ ] **Whitespace**: Maintains generous padding on mobile (min 16px sides)

### Admin Pages
- [ ] **Settings page**: Form fields stack on mobile, multi-column on desktop
- [ ] **Bulk operations**: Table doesn't overflow, scroll horizontally on mobile if needed
- [ ] **Analytics dashboard**: Charts resize properly (note: may need manual polishing in TIER 3)
- [ ] **Event list**: Cards stack single-column on mobile
- [ ] **Session edit**: Form inputs remain usable on small screens

### Cart / Checkout
- [ ] **Cart overlay**: Full width on mobile, centered modal on desktop
- [ ] **Stripe Checkout**: Redirects properly, returns correctly
- [ ] **Payment form**: No horizontal scrolling, fields properly sized

---

## 3. Form Validation Tests

### Admin Login (`/admin/login.php`)
- [ ] **Required fields**: Email and password both marked required
- [ ] **Invalid email**: Rejects malformed email addresses (validation happens client + server)
- [ ] **Wrong password**: Returns "Invalid credentials" message (no user enumeration)
- [ ] **Unknown email**: Returns "Invalid credentials" message (not "user not found")
- [ ] **TOTP required**: Second factor prompt appears if user has TOTP enabled
- [ ] **TOTP validation**: Rejects invalid 6-digit codes
- [ ] **Session regeneration**: ID changes after successful login (security check)

### Event Creation / Edit (`/admin/events.php`)
- [ ] **Required fields**: Title, venue, event_date all required
- [ ] **Slug uniqueness**: Duplicate slug shows database error (or polished UX message)
- [ ] **Price fields**: Accept pence values only (integer > 0)
- [ ] **Publish toggle**: Changes immediately without page reload (AJAX or form)
- [ ] **Date picker**: Opens calendar widget on focus
- [ ] **Empty submit**: Shows validation errors before submitting

### Photo Upload (`/admin/upload.php`)
- [ ] **File type**: Only accepts .jpg, .jpeg, .png, .webp
- [ ] **File size**: Shows error if > ~100MB (or your configured limit)
- [ ] **Drag & drop**: Works on desktop browsers
- [ ] **Progress indicator**: Shows upload progress (if implemented)
- [ ] **Chunked upload**: Works for large files (your architecture supports this)

### Bulk Operations (`/admin/bulk.php`)
- [ ] **Empty selection**: Shows "No photos selected" message
- [ ] **Bulk tag**: Applies tags to all selected photos
- [ ] **Bulk status**: Changes status for bulk photos (blocked by enum mismatch - see blockers)
- [ ] **Bulk delete**: Shows confirmation, then deletes
- [ ] **Confirm dialogs**: Appear before destructive actions

---

## 4. Search & Filtering Tests

### Gallery Search (`/search`)
**⚠️ BLOCKER**: `/search` endpoint currently throws SQL error due to `photos.price_pence` column missing. Tests below will fail until this is resolved.

- [ ] **Search by filename**: Type "test" → returns matching photos
- [ ] **Search with filters**: Apply multiple filters (event, session, date range)
- [ ] **Pagination**: Page 1 shows 20 results, Page 2 shows next batch
- [ ] **Min query length**: Single character search returns no results
- [ ] **Empty query**: Blank search returns no results
- [ ] **Facets load**: Event, session, date range dropdowns populate
- [ ] **Mobile filter UI**: Filters collapse into a menu on mobile

### Photo Grid Filtering
- [ ] **Event filter**: Shows only photos from selected event
- [ ] **Session filter**: Shows only photos from selected session
- [ ] **Price filter**: (Blocked by photos.price_pence bug - not testable)
- [ ] **Reset filters**: "Clear all" returns to default grid

---

## 5. Cart & Checkout Tests

### Add to Cart
- [ ] **Single photo**: Add button adds to cart, updates count
- [ ] **Multiple photos**: Cart count increments correctly
- [ ] **Cart persistence**: Cart survives page reload (localStorage or session)
- [ ] **Remove from cart**: Delete button removes photo
- [ ] **Visibility**: Cart overlay visible at all times (sticky)

### Checkout Flow
- [ ] **Cart → Stripe**: Clicking "Checkout" redirects to Stripe Checkout
- [ ] **Stripe flow**: Payment form appears (test card: 4242 4242 4242 4242)
- [ ] **Return from Stripe**: After successful payment, returns to `/receipt` with download link
- [ ] **Receipt email**: Check inbox for receipt with download link
- [ ] **Download link**: Authenticated link works, serves clean (unwatermarked) file

### Download Verification
- [ ] **Signed URL**: Link includes signature (prevents tampering)
- [ ] **Expiration**: Link expires after configured duration (check config)
- [ ] **File integrity**: Downloaded image opens correctly, same as uploaded

---

## 6. Photo Grid & Lightbox Tests

### Gallery Display
- [ ] **Hero photo**: Full-width, proper aspect ratio, watermark visible at 800px+
- [ ] **Grid layout**: Photos maintain aspect ratios, no distortion
- [ ] **Watermark**: Applied only on display (800px+), removed from purchased file
- [ ] **Lightbox open**: Click photo → modal opens with full-size image
- [ ] **Lightbox navigation**: Arrow keys move to prev/next photo
- [ ] **Close lightbox**: Esc key closes, click outside closes, X button closes
- [ ] **Mobile lightbox**: Touch swipe works, buttons accessible

### Video Section
- [ ] **Separate from photos**: Videos don't load in grid (different endpoint/loading)
- [ ] **Video playback**: Click video → plays in modal or modal with video player
- [ ] **Aspect ratio**: 16:9 maintained, no black bars (if applicable)
- [ ] **Mobile playback**: Full-screen playback works

---

## 7. Cron Job Verification (7-day lifecycle)

### Prerequisites
- [ ] Enable cron job via hosting control panel (cron every 5 minutes)
- [ ] Point to `/app/lib/cron.php`
- [ ] Verify logs show execution (check `/storage/logs/` if available)

### Day 0: Upload & Create Event
1. Create new event: "Test Event - 7-Day Tiering"
2. Upload 1-2 test photos to this event
3. Check `/storage/hires/` → new subdirectory for photos exists
4. Check database: Photos have `created_at` timestamp (should be now)

### Day 1-6: Check Derivatives
- [ ] Run cron manually or wait for scheduled run
- [ ] Check `/storage/hires/` → derivatives exist (.800px.jpg, .1200px.jpg, etc.)
- [ ] Each derivative is progressively smaller (file size)
- [ ] Timestamps: derivatives show creation time after cron run

### Day 7: Cleanup Verification
- [ ] After 7 days, original `.tif`/`.raw` files should be deleted
- [ ] Derivatives (`.800px.jpg`, etc.) should remain
- [ ] Check database: `photo.original_file` should be NULL (or marked as deleted)
- [ ] Public gallery still shows photo (uses derivatives)

### Logs
- [ ] Check for cron output/errors: `/storage/logs/cron.log` (if you implement logging)
- [ ] Monitor memory usage (large files × derivative generation can be expensive)

---

## 8. Performance & Load Testing

### Page Load Times
- [ ] **Gallery homepage**: Load in <2s on broadband (target: <1s)
- [ ] **Admin page**: Load in <1s
- [ ] **Search results**: Populate in <500ms (cached facets)
- [ ] **Cart checkout**: Redirect to Stripe in <1s

### Network Tab (Chrome DevTools)
- [ ] **Facet queries**: 4 queries combined to 1 per 15 min (cached)
- [ ] **Photo grid**: Load images progressively (lazy loading if implemented)
- [ ] **Settings load**: Load once per session (session cache)
- [ ] **No N+1 queries**: Bulk operations use multi-row INSERT (verify in slow query log)

### Database Slow Query Log
If your hosting supports slow query logging:
- [ ] Configure MySQL: `long_query_time = 1` second
- [ ] Check `/var/log/mysql/slow.log` or hosting dashboard
- [ ] Verify no queries over 1 second (especially search)

---

## 9. Accessibility & UX Tests

### Keyboard Navigation
- [ ] **Tab through page**: All interactive elements reachable via Tab
- [ ] **Enter key**: Submit forms, activate buttons
- [ ] **Escape key**: Close lightbox, close cart, close dialogs
- [ ] **Arrow keys**: Navigate lightbox prev/next

### Color Contrast
- [ ] **Text on background**: White/black text on white/black background (WCAG AA minimum)
- [ ] **Links**: Underlined or distinct color, not color-only
- [ ] **Form errors**: Red text + icon, not color-only

### Responsive Text
- [ ] **Font sizes**: Readable on mobile (min 16px for inputs)
- [ ] **Line height**: 1.5+ for body text (generous spacing)
- [ ] **Line length**: Max ~65 characters per line (readable)

### Mobile UX
- [ ] **Touch targets**: Buttons ≥44px × 44px (Apple HIG standard)
- [ ] **No hover-only**: Hover states have alternatives on mobile (e.g., active states)
- [ ] **Zoom**: Page remains usable if user pinch-zooms

---

## 10. Security Spot-Checks

### Authentication
- [ ] **Session hijacking**: Session ID changes after login (should not reuse old ID)
- [ ] **CSRF protection**: Forms include CSRF token (if applicable)
- [ ] **SQL injection**: Admin login rejects `' OR '1'='1` (parameterized queries in use)

### File Upload
- [ ] **File type check**: Upload `.exe` file → rejected
- [ ] **Filename sanitization**: Special characters in filename handled safely
- [ ] **Zip extraction**: Bulk download extracts safely (no path traversal)

### Watermarking
- [ ] **Purchased file clean**: Download file has no watermark
- [ ] **Gallery display watermarked**: Gallery view shows watermark at 800px+
- [ ] **Signature verification**: Signed download links can't be forged

---

## 11. Known Blockers & Deferred Issues

### Blocker 1: Missing `photos.price_pence` Column
**Impact**: `/search` endpoint throws SQL error on every real query  
**Affected functionality**:
- [ ] Search page (/search) - BLOCKED
- [ ] Search filtering - BLOCKED
- [ ] Bulk price updates - SKIPPED TEST
- [ ] Analytics price trends - SKIPPED TEST

**Resolution path**:
```
Option A: Add column to schema
  ALTER TABLE photos ADD COLUMN price_pence INT;
  (Then backfill from event prices or set to NULL)

Option B: Remove all per-photo pricing
  - Delete references in search.php, bulk.php, analytics.php, etc.
  - Use event-level pricing only
  - Update UI accordingly
```

**Decision needed from user**: Which approach?

### Blocker 2: Bulk Status Vocabulary Mismatch
**Impact**: `bulk_change_status()` fails with ENUM truncation error  
**Affected functionality**:
- [ ] Bulk change status (draft/live/archived) - BLOCKED

**Issue**:
- UI uses: draft, live, archived
- Schema ENUM: processing, live, hidden, failed
- Status values don't match

**Resolution path**:
```
Option A: Update schema ENUM to match UI
  ALTER TABLE photos MODIFY COLUMN status ENUM('draft','live','archived');

Option B: Update UI to match schema
  - Change bulk.php UI to use: processing, live, hidden, failed
  - Update controller validation
  - Update tests
```

**Decision needed from user**: Which approach?

---

## 12. Test Results Template

Use this to document findings:

```
DEVICE/BROWSER: iPhone 14 / Safari 18
TEST_DATE: 2025-07-29
TESTER: [Your Name]

PASS:
- [ ] Gallery page responsive
- [ ] Hero photo displays
- [ ] Cart button accessible

FAIL:
- [ ] Form validation: Email field accepts invalid format
  - Expected: Reject "not-an-email"
  - Actual: Accepts it
  - Severity: High
  - Fix: Add client-side email validation

BLOCKED:
- [ ] Search filtering (photos.price_pence missing)
- [ ] Bulk status change (ENUM mismatch)

DEFERRED TO TIER 3:
- [ ] Admin UI CSS polish (not tested, deferred for visual refinement)
- [ ] Performance monitoring (not in scope)
```

---

## 13. Execution Checklist

- [ ] **Before starting**: Create test event + photos in staging environment
- [ ] **Mobile testing**: Use physical devices or Xcode Simulator
- [ ] **Browser testing**: Open DevTools to check console errors
- [ ] **Document findings**: Screenshot failures, note exact steps to reproduce
- [ ] **Check logs**: Review PHP error logs (`/var/log/apache2/error.log` or hosting dashboard)
- [ ] **Database verification**: Check cron job actually ran (query `photo.created_at` timestamps)

---

## 14. Success Criteria (TIER 1.3 Complete)

Phase 3 testing passes when:
- ✅ All responsive design tests pass (mobile/tablet/desktop)
- ✅ All form validation tests pass
- ✅ Cart checkout flow works end-to-end
- ✅ Cron job lifecycle verified (derivatives created, cleanup after 7 days)
- ✅ All tests documented with results
- ✅ Console shows no JavaScript errors
- ✅ No SQL errors in PHP error log (except for photos.price_pence blocker)
- ✅ Download links work and files integrity verified

---

## Next Steps After Testing

1. **If all tests pass**: Move to TIER 2.3 (GitHub Actions CI/CD setup)
2. **If tests find bugs**: Create issues, fix, and re-test
3. **If blockers encountered**: Document decision needed (photos.price_pence, status vocab)
4. **Performance issues**: Document and prioritize for TIER 3

Good luck! 🚀

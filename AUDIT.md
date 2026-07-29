# Comprehensive Site Audit

Date: 2026-07-29  
Branch: claude/plugin-skill-setup-y9v6kx

---

## Performance Issues

### 1. SELECT * Queries (14 instances)
**Impact**: High | **Effort**: Low | **Priority**: P1

Queries that fetch all columns waste bandwidth and memory. Should specify only needed columns.

**Locations**:
- `app/controllers/admin/totp_enroll.php:14` — SELECT * FROM admin_users
- `app/controllers/admin/watermarks.php:50` — SELECT * FROM watermark_settings
- `app/lib/reporting.php:40,105` — SELECT * FROM cohorts
- `app/lib/settings.php:14,73` — SELECT * FROM settings
- `app/lib/permissions.php:134` — SELECT * FROM admin_roles
- `app/lib/api.php:39,104` — SELECT * FROM api_keys, photos
- `app/lib/auth.php:44` — SELECT * FROM admin_users
- `app/lib/email.php:52,86,179` — SELECT * FROM email_templates, emails
- `app/lib/wishlist.php:14` — SELECT * FROM wishlists

**Fix Strategy**: Audit each query to identify actually-needed columns, replace with explicit column list.

---

### 2. Image Loading Performance
**Impact**: Medium | **Effort**: Low | **Priority**: P2

Images use `loading="lazy"` (good) but missing:
- `<img>` tags lack `width`/`height` attributes (causes layout shift)
- Missing `srcset` for responsive images (desktop/mobile)
- No WebP format alternatives
- Derivative sizes not optimized for all breakpoints

**Examples**:
- Home page event cards: `/media/d/{token}-800.jpg` only
- Event galleries: mixed sizes without clear breakpoints
- Admin thumbnails: no size hints

**Fix Strategy**:
1. Add `width` and `height` to all `<img>` tags
2. Generate WebP derivatives alongside JPEG
3. Use `srcset` for responsive images (400px, 800px, 1200px)
4. Update derivatives.php generation to include more sizes

---

### 3. Missing Query Result Caching
**Impact**: Medium | **Effort**: Medium | **Priority**: P2

Expensive queries repeated unnecessarily:
- Settings loaded multiple times per request (use $_SESSION cache)
- Admin roles loaded repeatedly in permission checks
- Email templates queried per email generation
- Facet queries (kart, driver, class counts) called twice per search

**Fix Strategy**:
1. Implement request-level caching in common query paths
2. Add per-request cache invalidation
3. Cache admin roles in session after first fetch
4. Cache email templates in $_SESSION for 1 hour

---

### 4. Synchronous View Count Updates
**Impact**: Medium | **Effort**: Medium | **Priority**: P3

Current: `public_api_photo_view_controller` queues async view count increment.  
But sync updates to stats_daily table could block on high traffic.

**Fix Strategy**: Ensure view count batching works reliably, consider rate limiting per IP.

---

## SEO Issues

### 1. Missing Core SEO Files
**Impact**: High | **Effort**: Low | **Priority**: P1

No `public/robots.txt` or XML sitemap.

**Missing**:
- `robots.txt` (controls crawler access)
- `sitemap.xml` (lists all public pages)
- Sitemap generation endpoint or static file

**Fix Strategy**:
1. Generate `public/robots.txt` with:
   ```
   User-agent: *
   Allow: /
   Allow: /search
   Disallow: /admin
   Disallow: /cart
   Sitemap: https://[domain]/sitemap.xml
   ```
2. Add `/sitemap.xml` endpoint that returns:
   - Home page
   - All published events
   - All public search pages (limited patterns)
   - Last-Modified timestamps

---

### 2. Missing Meta Tags
**Impact**: High | **Effort**: Low | **Priority**: P1

Public pages missing:
- `<meta name="description">` (used in search results)
- `<meta name="theme-color">` (mobile UI theming)
- `<link rel="canonical">` (SEO duplicate prevention)
- Open Graph tags (`og:title`, `og:description`, `og:image`, `og:url`)
- Twitter Card tags

**Examples**:
- `app/views/public/home.php` — No description, no OG tags
- `app/views/public/event.php` — Could use event-specific description
- Search results page — No description

**Fix Strategy**:
1. Add default meta tags to base template
2. Pass `pageTitle`, `pageDescription`, `pageImage` to views
3. Generate Open Graph tags for each page type
4. Add Twitter Card tags for image-heavy pages

---

### 3. Missing Structured Data (JSON-LD)
**Impact**: Medium | **Effort**: Medium | **Priority**: P2

No JSON-LD markup for:
- Organization (website identity)
- LocalBusiness (if applicable)
- Product schema (for photos/events)
- BreadcrumbList (navigation structure)
- ImageObject (for SEO image discovery)

**Fix Strategy**:
1. Add Organization schema to home page
2. Add Product schema to search results
3. Add ImageObject schema to gallery pages
4. Add EventName schema to event pages

---

### 4. Alt Text for Images
**Impact**: Medium | **Effort**: Low | **Priority**: P2

Some images have empty `alt=""` (not descriptive):
- Event cards: `alt=""` should be event name
- Gallery photos: `alt=""` should be meaningful description
- Watermarked images: include photo ID and event in alt

**Fix Strategy**:
1. Generate descriptive alt text from photo metadata
2. Add event/session/kart/driver to alt text
3. Audit all `<img>` tags for proper alt text

---

### 5. Missing Favicon & App Metadata
**Impact**: Low | **Effort**: Low | **Priority**: P3

No favicon, no manifest.json for PWA features.

**Missing**:
- `favicon.ico`
- `apple-touch-icon.png`
- `manifest.json` (PWA metadata)

---

## Security Issues

### 1. Consistent Error Messages
**Impact**: Low | **Effort**: Low | **Priority**: P2

Some endpoints return bare error text:
- `app/controllers/public/api_photos.php:27` — Returns `'not found'` string (not JSON for API)
- Error handling inconsistent between HTML and JSON endpoints

**Fix Strategy**: Ensure all API endpoints return JSON with error structure, HTML endpoints return proper error pages.

---

### 2. Rate Limiting on Public Endpoints
**Impact**: Medium | **Effort**: High | **Priority**: P3

No rate limiting on:
- `/search` endpoint (could be abused for scanning)
- `/api/photos` endpoint (could be scraped)
- View count API (could be spammed)
- Download link generation (could be brute-forced)

**Status**: Rate limiting exists (`app/lib/rate_limit.php`) but not applied to public endpoints.

**Fix Strategy**: Apply rate limiting to:
1. `/search` — 30 requests/minute per IP
2. `/api/photos` — 50 requests/minute per IP
3. `/api/photos/view` — 1 per second per IP (fire-and-forget)
4. Photo/video downloads — 10 per minute per customer token

---

### 3. Input Validation Inconsistency
**Impact**: Medium | **Effort**: Medium | **Priority**: P2

Some controllers validate thoroughly, others don't:
- Search filters accept arbitrary integers (should validate ranges)
- Date filters not validated for format
- Pagination page numbers not validated (could be 0 or negative)

**Fix Strategy**: Create shared validation helpers for common inputs (pagination, dates, numbers).

---

## Code Quality Issues

### 1. Duplicated Filter Building Logic
**Impact**: Low | **Effort**: Medium | **Priority**: P3

Same filter extraction code appears in:
- `app/controllers/public/search.php` (lines 18-42)
- `app/controllers/public/search.php` (lines 65-77) — duplicate in API
- `app/controllers/public/event.php` (similar pattern)

**Fix Strategy**: Extract to `build_search_filters()` helper function.

---

### 2. Inconsistent Error Page Structure
**Impact**: Low | **Effort**: Low | **Priority**: P3

Error pages use inline HTML instead of templates:
- 404 errors: `echo '404 Not Found'`
- 405 errors: `echo 'Method Not Allowed'`

**Fix Strategy**: Create shared error view templates (`_error_404.php`, etc.).

---

### 3. Missing Constants for Configuration
**Impact**: Low | **Effort**: Low | **Priority**: P3

Some magic numbers/strings hardcoded:
- Page sizes: `20` appears in multiple files
- Cache TTLs: `900`, `3600`, `86400` hardcoded
- TOTP window: hardcoded in auth.php

**Fix Strategy**: Define constants in `config/config.php`:
```php
define('SEARCH_PAGE_SIZE', 20);
define('CACHE_TTL_SHORT', 600);
define('CACHE_TTL_LONG', 86400);
```

---

## Missing Features for Production

### 1. Custom 404/500 Error Pages
**Impact**: Medium | **Effort**: Low | **Priority**: P2

Current error handling returns bare text. Should have branded error pages.

**Missing**:
- `app/views/errors/404.php`
- `app/views/errors/500.php`
- `app/views/errors/503.php`

---

### 2. Health Check / Status Endpoint
**Impact**: Low | **Effort**: Low | **Priority**: P3

No public health check endpoint for monitoring.

**Missing**:
- `/health` endpoint (returns 200 if database is accessible)
- Used by load balancers / monitoring

---

### 3. Security.txt
**Impact**: Low | **Effort**: Low | **Priority**: P3

No `.well-known/security.txt` for responsible disclosure.

---

## Testing & Deployment Readiness

### Missing Test Coverage
- [ ] Pagination edge cases (page=0, page=-1, page=999999)
- [ ] Search filters with invalid ranges
- [ ] Rate limiting under load
- [ ] Image derivative generation with various input sizes
- [ ] Download link expiration
- [ ] Email delivery queue

---

## Summary by Priority

| Priority | Count | Issues |
|----------|-------|--------|
| P1 | 3 | SELECT * queries, robots.txt/sitemap, meta tags |
| P2 | 6 | Image loading, query caching, error handling, input validation, alt text, rate limiting |
| P3 | 5 | View count sync, structured data, favicon, error pages, constants |

---

## Recommended Implementation Order

1. **Week 1: Core SEO & Performance**
   - Fix all SELECT * queries
   - Add robots.txt and sitemap
   - Add meta tags and Open Graph
   - Add image width/height attributes

2. **Week 2: Security & Code Quality**
   - Fix error handling consistency
   - Add input validation helpers
   - Apply rate limiting to public endpoints
   - Add 404/500 error pages

3. **Week 3: Nice-to-Haves**
   - Structured data (JSON-LD)
   - Alt text improvements
   - Image srcset and WebP variants
   - Security.txt and health check

---

## Files to Create/Modify

### New Files
- `public/robots.txt`
- `app/views/errors/404.php`
- `app/views/errors/500.php`
- `app/.well-known/security.txt`
- `app/lib/validation.php` (shared validators)

### Modified Files
- All controllers with SELECT * (14 files)
- All public views (add meta tags)
- `public/index.php` (add error page handling)
- `app/bootstrap.php` (define constants)
- Public controllers (apply rate limiting)

---


# Site Audit: Correctness, Robustness, and Dependencies

Date: 2026-07-29
Scope: every controller, view, and library under `app/`, plus `cron/`, `config/`, and `composer.json`. This is a findings document. No fixes have been applied for the items below; each entry states what would need to change.

Severity key:
- **CRITICAL**: a paying customer or the money path is broken today.
- **HIGH**: wrong behavior a real user will hit, or a security gap.
- **MEDIUM**: silent failure, race, or inconsistency that will bite during operation or debugging.
- **LOW**: consistency and polish.

---

## CRITICAL

### C1. Bundle purchases can never be downloaded
`app/controllers/public/download.php:67-93`

The download loop only reads `photo_id` from each order item. Session bundles and event bundles are stored with `photo_id = NULL` (they set `session_id` or `event_id` instead, see `create_order()` in `app/lib/orders.php:36-38`). The loop skips them, `$files` ends up empty, and the customer who just paid gets `404 No files found`.

The `zip_build` background job does not cover this either: `process_zip_build_job()` in `app/lib/cron.php:108` is a stub that returns true. The download endpoint is the only delivery path, and it cannot deliver bundles.

**Failure scenario:** customer buys a session bundle, pays via Stripe, clicks the download link in their receipt, gets a 404. Every bundle sale is undeliverable.

**Fix shape:** when an item has `session_id` or `event_id`, expand it to all live photos in that session/event and add each hires file to `$files`.

### C2. Every templated customer email renders broken placeholders
`app/lib/email.php:73-78` vs `migrations/004_add_email_system.sql:43-57`

The seeded templates use `{{order_id}}`-style placeholders. The interpolator builds its search string as `"{{$key}}"`, which PHP double-quote interpolation evaluates to `{order_id}` with single braces. So `{{download_link}}` has only its inner `{download_link}` replaced, and the customer sees `{https://...}` wrapped in stray braces. In the HTML template, `<a href="{{tracking_url}}">` becomes `href="{https://...}"`, a broken link.

**Failure scenario:** every receipt, refund, and shipping email shows brace-wrapped values, and links in them do not work.

**Fix shape:** `str_replace('{{' . $key . '}}', ...)` (one line), plus a test that renders a seeded template.

### C3. Job queue and rate limiter are fatal on SQLite deployments
`app/lib/cron.php:15`, `app/controllers/admin/jobs.php:13`, `app/lib/rate_limit.php:33`

`GET_LOCK()` is MySQL-only and is called unguarded as the first statement of the cron entrypoint and the admin "run jobs" button. On SQLite the whole job queue throws before draining anything: no receipt emails, no cleanup jobs, ever.

Worse, `check_rate_limit()` uses `ON DUPLICATE KEY UPDATE` (also MySQL-only) and sits directly on the login, checkout, TOTP, and download paths. On SQLite, the first login attempt from a new IP throws, which means **login itself is broken**, not just background work.

CLAUDE.md targets MySQL, so this is a portability finding rather than a target-host bug, but the test suite and the zero-setup dev path both run SQLite, and nothing guards or documents the MySQL requirement at these call sites.

Full list of MySQL-only SQL sites found:

| File | Construct |
|---|---|
| `app/lib/cron.php:15,26,69` | `GET_LOCK`, `DATE_SUB`, `DATE_ADD`, `NOW()` |
| `app/controllers/admin/jobs.php:13` | `GET_LOCK` |
| `app/lib/rate_limit.php:33` | `ON DUPLICATE KEY UPDATE` |
| `app/lib/bulk.php:32` | `ON DUPLICATE KEY UPDATE`, `NOW()` |
| `app/lib/reporting.php:72` | `ON DUPLICATE KEY UPDATE` |
| `app/lib/analytics.php:26,32,136,179` | `DATE_FORMAT`, `DATE_SUB`, `NOW()` |
| `app/lib/derivatives.php:63` | `DATE_ADD`, `NOW()` |
| `app/controllers/admin/health.php:252,260` | `DATE_SUB`, `NOW()` |
| `app/controllers/public/download.php:102` | `NOW()` |
| `app/lib/cli/commands.php:224` | `DATE_SUB`, `NOW()` |

**Fix shape:** a small `db_compat.php` with driver-aware helpers (`db_advisory_lock()`, `db_interval_sql()`, upsert branches), or an explicit documented decision that only MySQL is supported, enforced with one clear check at bootstrap instead of ten scattered fatal errors.

---

## HIGH

### H1. `get_or_create_download_link()` always creates
`app/lib/orders.php:132-134`

The function is named get-or-create but unconditionally calls `create_download_link()`. Every refresh of `/checkout/success/{token}` mints a new `download_links` row with its own token and its own `max_downloads` budget. Old links stay valid. A customer refreshing the success page five times has five live links and five times the intended download cap.

**Fix shape:** select an unexpired, unrevoked link for the order first; only create when none exists. Requires storing the raw token or returning the existing link differently (token is hashed), so the practical fix is: create once at payment time, persist the URL on the order or in the email, and have the success page reuse it.

### H2. Missing `download_cap_multiplier` setting collapses the cap to 1
`app/lib/orders.php:114`

```php
$multiplier = (int)($stmt->fetchColumn() ?? 5);
```

`fetchColumn()` returns `false` (not `null`) when the settings row is missing, and `false ?? 5` is `false`, so the multiplier becomes `(int)false = 0` and `max(1, $itemCount * 0)` is 1. A fresh install without that seed row gives every customer exactly one download attempt regardless of order size.

**Fix shape:** `($stmt->fetchColumn() ?: 5)` or an explicit `=== false` check. Same `??` vs `false` trap should be grepped for across the codebase (`fetchColumn() ??` appears nowhere else, verified).

### H3. Stripe webhook signature parsing is fragile and has no replay protection
`app/lib/stripe.php:47-58`

The header is parsed with `str_replace('t=', '', str_replace('v1=', '', $sig))` then one `explode(',')`. Stripe's header is `t=...,v1=...` but can carry multiple `v1=` entries (during webhook secret rotation) and `v0=` entries. With more than two segments the parse breaks and valid webhooks are rejected, which means **payments stop confirming exactly when you rotate the webhook secret**. There is also no timestamp tolerance check, so a captured webhook can be replayed indefinitely (mitigated by the event-ID idempotency table, but the timestamp check is the documented Stripe defense).

**Fix shape:** parse the header into key/value pairs properly, compare against every `v1` candidate with `hash_equals`, reject timestamps older than 5 minutes. This stays inside the existing hand-rolled wrapper; it does not require the Stripe SDK.

### H4. Stripe API errors are swallowed into empty arrays
`app/lib/stripe.php:77-82`

Two problems: when curl itself fails, `$response` is `false` and `$httpCode` is 0, so the thrown message is `Stripe API error: HTTP 0` with the real `curl_error()` discarded. And on a 2xx with malformed JSON, `json_decode(...) ?? []` returns an empty array instead of throwing, so the caller reports the misleading `Failed to create Stripe checkout session`.

**Fix shape:** capture `curl_error()` before `curl_close()`, include Stripe's own error body (it returns JSON with `error.message`) in the exception, and throw on JSON decode failure.

### H5. Checkout failures are invisible
`app/controllers/public/checkout.php:77-80`

The catch block returns `{"error": "Checkout failed"}` and records nothing. No `error_log`, no `audit_log`. A live checkout outage (bad Stripe key, network egress blocked, Stripe API change) can only be diagnosed by reproducing it.

**Fix shape:** `error_log()` the exception and `audit_log()` a `checkout_failed` event with the message. The customer-facing response stays generic.

### H6. Bulk status vocabulary does not match the schema
`app/lib/bulk.php`, `app/views/admin/bulk.php:276-280`

The bulk UI and library accept `draft` / `live` / `archived`, but `photos.status` is `processing` / `live` / `hidden` / `failed`. Bulk status changes to `draft` or `archived` fail with an ENUM truncation error on strict MySQL. Related and already logged in PROGRESS.md: `photos.price_pence` is referenced by bulk pricing, search, analytics, and export, but the column does not exist, so `/search` errors on every real query. Both are known product decisions still pending; listed here so the audit is complete.

---

## MEDIUM

### M1. Broad `try/catch { return []; }` hides database errors
`app/lib/permissions.php`, `app/lib/analytics.php`, `app/lib/email.php`, `app/lib/cache.php`, `app/lib/reporting.php`

Around 20 functions catch `Throwable` and return `null`/`[]`/`false`. An operator cannot distinguish "no data" from "the query is broken". This interacts badly with C3: on SQLite, the analytics dashboard renders all zeros instead of surfacing the incompatible SQL. `create_admin()` returns `false` identically for "duplicate email" and "database down".

**Fix shape:** keep the catch where a page must degrade gracefully, but `error_log()` inside every one of them, and let genuinely unexpected errors propagate in mutation paths (`create_admin`, `update_admin_role`, `delete_admin`).

### M2. `mark_order_paid()` verifies the wrong thing
`app/lib/orders.php:79-90`

After the UPDATE it re-selects the order by the same ID and only queues the email/zip jobs if the row exists. If the ID is bad, the UPDATE silently did nothing and the jobs silently do not queue. The check adds a query without adding safety.

**Fix shape:** check `$stmt->rowCount()` on the UPDATE; log or throw when it is 0. Queue jobs unconditionally after a confirmed update.

### M3. Zip entries collide on duplicate original filenames
`app/controllers/public/download.php:152-154`

Entries are named by `original_filename`. Two purchased photos that were both uploaded as `IMG_0001.jpg` produce one zip entry; the second silently replaces the first. Customer receives fewer files than they bought.

**Fix shape:** prefix entries with the photo token or an index when names collide.

### M4. Download count check is not atomic
`app/controllers/public/download.php:50,102`

Check-then-increment: parallel requests can both pass the `download_count >= max_downloads` check. The cap is advisory, so impact is low, but the fix is one line: `UPDATE ... SET download_count = download_count + 1 WHERE id = ? AND download_count < max_downloads` and check `rowCount()`.

### M5. `cart_save()` signs with an empty key on misconfigured installs
`app/lib/cart.php:63-68`

`cart_get()` rejects an empty `hmac_key`, but `cart_save()` happily signs with `''`. On an install missing the key, every add-to-cart writes a cookie the very next read rejects. The user experience is a cart that silently never keeps anything.

**Fix shape:** throw from `cart_save()` when the key is empty (misconfiguration should be loud), or validate the key at bootstrap.

### M6. `Content-Disposition` filename handling breaks on non-ASCII names
`app/controllers/public/download.php:130`

`addslashes()` is not a header-safe encoding. An original filename with quotes or UTF-8 (common from customer cameras and phones: `Rennen München.jpg`) produces a malformed header. Use the RFC 5987 `filename*=UTF-8''...` form with a plain ASCII fallback.

### M7. Upload init silently drops invalid files
`app/controllers/admin/upload.php:55-66`

Files whose JSON fails to parse or whose size is 0 are skipped with `continue`. The response contains fewer entries than were sent, and the uploader UI has no idea which files were rejected or why.

**Fix shape:** return a `rejected` list alongside the accepted entries so the JS can show per-file errors.

### M8. `LIMIT ?` bound as a string
`app/lib/email.php:85-91`

`$stmt->execute([$limit])` binds the LIMIT as a string. Works under PDO's default emulated prepares; breaks if anyone sets `ATTR_EMULATE_PREPARES => false`. Bind with `PDO::PARAM_INT` or interpolate the already-cast int.

---

## LOW

### L1. Config `currency` shape is inconsistent
`config/config.php:36` stores `'currency' => 'GBP'` (a string). `app/lib/stripe.php:19` and `app/lib/orders.php:17` read it as a string; a dozen controllers read `$config['currency']['code'] ?? 'GBP'` as if it were an array. The array reads only avoid an error because `??` uses `isset()` semantics on an illegal string offset and silently falls back to the default. If anyone "fixes" the config to the array shape the controllers imply, `create_order()` inserts an array and throws. Pick one shape (the array, with `code` and `symbol`) and migrate all readers.

### L2. `NOW()` sweep incomplete
PROGRESS.md records a full `NOW()` to `CURRENT_TIMESTAMP` sweep, but `NOW()` remains in the ten files listed under C3. If the sweep mattered (it did, for SQLite), these sites are the remainder.

### L3. Rate-limit key style inconsistent
Login uses `'ip:' . $ip` / `'acct:' . $email`; download uses the bare IP; checkout uses `"{$email}:{$ip}"`. Harmless today, but bucket keys that collide across meanings would be hard to debug. Standardize the prefix convention.

### L4. `checkout_success` unpaid branch is a bare `echo`
`app/controllers/public/checkout.php:99` prints an unstyled plain-text sentence with a 400. Real customers land here when the webhook is slow. Render a small styled "payment is still confirming, check your email" page instead.

---

## Dependency audit

The runtime dependency surface is already minimal, which matches the CLAUDE.md constraint (plain PHP, no build step):

```json
"require":     { "php": ">=8.2" }
"require-dev": { "phpunit/phpunit": "^11.0" }
"suggest":     { "phpseclib/phpseclib": "remote admin mode only" }
```

### KEEP (security or protocol critical, do not replace)

- **phpseclib** (currently `suggest`, loaded only when `admin_mode = 'remote'`): SFTP and cryptographic protocol handling. Stays per project rule. It is correctly optional, so shared-hosting installs that never use remote mode carry zero crypto dependency weight.
- **Stripe**: there is **no Stripe SDK installed**. `app/lib/stripe.php` is a hand-rolled curl wrapper. Per project rule nothing here is being removed or replaced, but be aware the wrapper currently lacks things the SDK gives for free: robust signature parsing with multiple `v1` entries, timestamp tolerance, and structured error bodies (findings H3/H4 above patch these gaps inside the wrapper).
- **phpunit** (dev only): never ships to production hosts. Keep.

### CANDIDATE FOR INLINING

None. There is nothing to inline; the runtime `require` block is empty. The `vendor/` directory in the working tree contains only PHPUnit and its transitive dev dependencies.

### Vendoring for no-SSH hosts

Because the runtime has zero Composer dependencies, the app already deploys by plain upload with no `composer install` step. The only case needing vendoring is remote admin mode (phpseclib). Recommended INSTALL.md wording: default path needs no Composer at all; remote-mode path either runs `composer require phpseclib/phpseclib` or downloads a provided `vendor.zip` release artifact. Not yet written into INSTALL.md; tracked as follow-up.

---

## What was checked and found sound

- **Admin auth** (`app/lib/auth.php`): timing-safe email enumeration defense via decoy Argon2id hash, rate limiting before hash verification, TOTP replay protection via `totp_last_step`, session regeneration after login. Solid.
- **CSRF** (`app/lib/csrf.php`): per-session token, `hash_equals`, and a documented, defensible exemption for cart mutations.
- **Cart cookie** (`app/lib/cart.php` read path): HMAC-verified, IDs only, prices always re-read from DB, type whitelist on parse. Sound design, one write-path gap (M5).
- **Download token storage**: raw token never stored, SHA-256 hash compared server-side. Correct.
- **Webhook idempotency**: event-ID dedupe table, and the failure path deliberately does not mark the event processed so Stripe retries. Correct behavior (worth a comment saying it is deliberate).
- **Order creation**: wrapped in a transaction with rollback. Correct.
- **Derivative worker**: failure flips the photo to `failed` instead of leaving it stuck in `processing`. Correct.

## Suggested fix order

1. C1 bundle downloads (paying customers, data-loss equivalent)
2. C2 email placeholders (one line plus a test)
3. H2 download cap default, H1 duplicate links (same file, same session of work)
4. H3/H4/H5 Stripe wrapper hardening and checkout logging
5. C3 as a deliberate decision: either `db_compat.php` or a bootstrap-time "MySQL required" check
6. H6 pending the existing product decisions on `price_pence` and status vocabulary
7. M and L items opportunistically, M1 (logging inside catches) first since it makes everything else diagnosable

# Security audit report

Date: 2026-07-30
Skill: security-audit v2.5.0, mode: full (scope reduced, see below)
Scope: whole repository, git HEAD at time of run

## Honest scope note

The skill's default full mode fans out 12 deep-dive categories across the
top 8 risk-ranked partitions (up to 96 sub-agent invocations). That budget
was not available in this session. Instead, two targeted deep-dive agents
covered the highest-risk code directly:

1. **auth, crypto/mitm, secret_sprawl, injection** against `app/lib/`
   (auth, TOTP, session, signer, Stripe wrapper, CSRF, orders, cart,
   rate limiter) and `app/controllers/webhook/stripe.php`.
2. **idor/collection_scope, deployment/config, supply_chain** against
   `app/controllers/public/`, `app/controllers/admin/`, bootstrap/front
   controllers, upload validation, and dependency declarations.

Phase 2's attack-surface, sink, credential, and collection-scoping
inventories were built by direct code reading rather than the skill's
automated `phase-02-surface.md` sub-agent procedure. No external SAST/SCA/
secret scanners were available in this environment (Phase 4 ran in
degraded mode, see `.claude-audit/current/phase-04-scanners/status.json`).

**What this means for the reader:** the findings below are real and were
verified against actual source (not sampled or guessed), but a clean
result here is not equivalent to a full 12x8 fan-out with scanner
corroboration. Treat this as a thorough manual review of the money path
and admin surface, not an exhaustive mechanical sweep. `docs/v2-plan.md`
should schedule a full-budget run of this skill once sub-agent capacity
allows.

## Severity key

CRITICAL = unauthenticated RCE, auth bypass, full data exfiltration.
HIGH = authenticated privilege escalation, significant data exposure, or a
payment/money-path integrity bug. MEDIUM = requires unusual preconditions
or has partial mitigation. LOW = defense-in-depth gap, no direct exploit
path. INFO = code smell, not a vulnerability.

## Findings fixed during this run

### SEC-01 (HIGH): API collection-scoping gap
`app/lib/api.php:89,111`. `api_get_photos()`/`api_get_photo()` filtered on
`photos.status='live'` but never checked `events.is_published`, unlike
every other read path in the app (`event.php`, `search.php`). An API key
holder could read metadata for photos belonging to events the admin has
not published yet. Fixed: both queries now require
`events.is_published = 1`. Test: `tests/integration/ApiPhotosScopeTest.php`.

### SEC-02 (HIGH): CSRF verification discarded or absent
Two related bugs across nine admin controllers:
- `events.php`, `migrations.php`, `photos.php`, `sessions.php` called
  `csrf_verify()` but discarded its boolean return. A wrong or missing
  token never actually blocked the mutation: the code read as protected
  but wasn't.
- `admins.php`, `bulk.php`, `settings.php`, `watermarks.php`,
  `upload.php` never called `csrf_verify()` at all.

Fixed: all nine now check the return value and return 403 on failure;
missing hidden `csrf_token` inputs added to the relevant forms. The
chunked upload flow (init/chunk/finalize across multiple requests) needed
a token that survives reuse without weakening the one-time-use guarantee
everywhere else, so a new `csrf_verify_reusable()` was added alongside the
existing one-time-use `csrf_verify()` rather than loosening it globally.
Test: `tests/integration/CsrfProtectionTest.php`.

### SEC-03 (INFO): dead column caused the API to fail closed
`app/lib/api.php:108` (pre-fix). `api_get_photo()`'s SELECT list included
`p.description`, a column that exists on `order_items` (a price/receipt
snapshot) but not on `photos`. Every call threw inside the surrounding
`try/catch` and returned `null` (not a security defect, since it fails
closed), but it means the single-photo API endpoint has never returned data for any
photo, published or not, and the `is_published` gate above had never
actually executed successfully in production. Fixed by removing the bogus
column. Caught by the same test that verified SEC-01's fix.

## Findings deferred (MEDIUM/LOW/INFO, logged per the governing plan's fix-order rule)

### SEC-04 (MEDIUM): rate limiter TOCTOU race
`app/lib/rate_limit.php:16-55`. `check_rate_limit()`'s read (`SELECT
hits`) and increment (`UPDATE hits = hits + 1`) are separate, unlocked
statements. Concurrent requests against the same bucket can all read
`hits < max_hits` before any increment commits, letting more attempts
through per window than configured on the login, TOTP, and checkout
buckets. Requires an attacker to fire genuinely concurrent requests, not
sequential ones. Fix shape: `UPDATE rate_limits SET hits = hits + 1 WHERE
bucket=? AND rl_key=? AND hits < ?` and branch on `rowCount()`.

### SEC-05 (LOW): webhook can double-queue jobs on overlapping delivery
`app/lib/orders.php:110-118`, `app/controllers/webhook/stripe.php:39-45`.
`mark_order_paid()` queues receipt/zip jobs whenever its `UPDATE`
matches a row, not whenever that row was actually still `pending`. Two
overlapping Stripe deliveries landing before the first request's
`webhook_events` idempotency insert commits could both pass the
`status==='paid'` guard and both queue jobs. Not attacker-triggerable
(signature-gated) and self-limiting (the idempotency table's primary key
stops it shortly after). Fix shape: make the `UPDATE` conditional on
`WHERE id=? AND status != 'paid'` and only queue jobs when it matched.

### SEC-06 (INFO): TOTP secret stored in plaintext
`app/lib/auth.php:44`. Standard for TOTP: the server must hold the raw
secret to compute codes, there's no one-way-hash alternative. Flagged as
a defense-in-depth note: a DB dump gives full TOTP forgery for that admin.

### SEC-07 (INFO): Stripe TLS verification relies on curl defaults
`app/lib/stripe.php:89-100`. `CURLOPT_SSL_VERIFYPEER`/`VERIFYHOST` are
never set explicitly. No live exposure today (PHP-curl's compiled-in
defaults verify peer and host), but a future refactor or a copy-pasted "disable
SSL for testing" edit could silently regress this. Recommend setting both
explicitly as a regression guard.

### SEC-08 (LOW): no lockfile pin for optional dependencies
`composer.json`. `phpseclib`/`phpmailer` are `suggest`-only and `vendor/`
is correctly gitignored, but there's no `composer.lock` pinning the
version a self-hoster's `composer require` resolves to. Low impact:
optional, admin-only dependencies. Follow-up: document a maintainer-
verified version in INSTALL.md.

## What was checked and found sound

- Admin auth: decoy-hash timing defense, dual rate-limit buckets checked
  before Argon2id verification, TOTP replay protection, session
  regeneration on login, session destruction on logout.
- CSRF token generation/comparison itself (HMAC via `hash_equals`): the
  bug was in call sites not checking the result, not in the primitive.
- Cart cookie: HMAC-signed, IDs only, prices always re-read from DB
  server-side, correctly CSRF-exempt.
- Download tokens: raw token never stored server-side except the
  documented `orders.download_url` trade-off; SHA-256 hash compared.
- Order creation: transactional with rollback.
- Upload validation: real MIME sniffing (`finfo` cross-checked against
  `getimagesize()`'s embedded type), not extension-only.
- Security headers (CSP, X-Frame-Options, X-Content-Type-Options,
  Referrer-Policy, HSTS) applied once at the top of both front
  controllers, before routing: every dispatched response inherits them.
- `display_errors` forced off in production bootstrap.
- `config/` and `storage/` both `Require all denied` via `.htaccess`.
- No SQL injection, command injection, or unsafe deserialization found
  anywhere in the reviewed code (repo-wide grep for
  `shell_exec/exec/system/eval/unserialize` found only benign
  `PDO::exec()`/`curl_exec()` and one safe-form `unserialize()` with
  `allowed_classes: false` in `cache.php`).
- Row/collection scoping: this is a single-admin, single-tenant
  application by explicit product design (no customer accounts). Admin
  controllers gate on `require_admin()` with no per-row ownership check,
  which is correct here: there is no second admin's data to leak from.
  Multi-admin mode shares full access by design; `audit_log` records
  which admin acted, it does not partition data. Flagged for `docs/
  v2-plan.md` as a decision to revisit only if multi-tenant/agency use is
  ever in scope.

## Severity gate

0 CRITICAL, 0 HIGH open. 1 MEDIUM, 2 LOW, 2 INFO open (all deferred with
disposition recorded in `.claude-audit/current/phase-05-findings.jsonl`
and `findings.sarif`). Gate: **pass**.

## Next steps

1. Fix SEC-04 (rate-limit race): small, well-scoped, worth doing even
   though it's MEDIUM, since it sits on the login/TOTP brute-force
   control.
2. Fix SEC-05 (webhook double-queue) opportunistically alongside other
   `orders.php` work.
3. Schedule a full-budget run of this skill (12 categories x top-8
   partitions) before the actual v2 build starts, once sub-agent capacity
   allows. This run's reduced scope should not be treated as a substitute
   for that.

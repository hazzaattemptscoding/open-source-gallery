# Plan: PowerMedia Gallery — Phase 1 Stability

## Context

The maintainer's brief: no new features until the platform is stable. Find every
bug, fix root causes (never patch symptoms or suppress warnings), regression
test, repeat until clean. Then, and only then, build the Phase-1 features
(hero contrast, masonry grid, favourites, admin gallery manager, per-event
sales, settings polish, plain-English health).

Decisions confirmed with the maintainer:
- **Order: bugs to zero first, all features after.** Checkout + email, uploads,
  warnings/console sweep — then frontend and admin features.
- "Card number/name fields" to remove = the motorsport driver/car-number and
  driver-name tagging fields; hide them to simplify the gallery workflow.
- Favourites: Pixieset-style — first tap on a heart prompts once for
  name + email, then favourites save silently against that identity
  (cookie + server-side persistence). docs/v2-plan.md §1.1 already scopes
  this; migrations/006 wishlist tables + app/lib/wishlist.php exist but are
  completely unwired dead code (no routes, no UI, no email/name columns).

---

## STAGE 1 — Checkout + email (the revenue path)

Root causes found by exploration (file:line verified):

### 1.1 CSP blocks Stripe.js — checkout cannot complete in any browser
`public/index.php:32` sends CSP with no `script-src`, so scripts fall back to
`default-src 'self'`. `cart.js:108-109` injects `https://js.stripe.com/v3/`,
which the browser refuses.
**Fix:** add `script-src 'self' https://js.stripe.com` (plus
`frame-src https://js.stripe.com https://hooks.stripe.com` per Stripe docs)
to the public CSP. Keep everything else tight.

### 1.2 CSP failure is silent — button "does nothing"
`cart.js:114-116` throws inside `script.onerror`, outside the `try` at :90,
so `showFormError` never runs.
**Fix:** show the visible error from inside the onerror callback.

### 1.3 Live config is unconfigured — cart and checkout structurally broken
`config/config.php`: `security.hmac_key`, `security.cron_secret`, `stripe.*`
all **empty**; `site.base_url` still `https://example.com`.
Empty `hmac_key` → `cart_get()` always `[]` (`app/lib/cart.php:37-40`) and
`cart_save()` throws → `POST /cart/add` 500s (uncaught in
`app/controllers/public/cart.php:53`).
**Fix:** (a) dev config: generate real `hmac_key`/`cron_secret`, set dev
`base_url`, Stripe test keys if available; (b) root cause: `cart_add` returns
clear JSON error instead of uncaught fatal, and setup/health flags an empty
`hmac_key` as a blocker instead of silent cart death.

### 1.4 Order tracking 500s — columns don't match schema
`app/controllers/public/order_tracking.php:40` selects `stripe_session_id`
(schema: `stripe_checkout_id`); :56 selects `oi.price_pence` (schema:
`unit_price_pence`/`line_total_pence`). Same bad column at
`app/controllers/admin/jobs.php:124`.
**Fix:** correct column names in both files.

### 1.5 Webhook queues an email type the worker doesn't handle
`app/controllers/webhook/stripe.php:91-94` queues `order_confirmation`;
`process_email_job()` (`app/lib/cron.php:106-113`) handles only
`receipt`/`refund` → every paid order leaves a permanently failed job.
**Fix:** remove the duplicate queueing (receipt from `mark_order_paid()`
already covers the confirmation email).

### 1.6 Refund path dead — `stripe_payment_intent` never populated
`update_order_stripe_ids()` called with the payment-intent arg defaulted to
null (`checkout.php:79`); `handle_charge_refunded` looks up by that column
(`webhook/stripe.php:103-108`) → always 0 rows.
**Fix:** store `payment_intent` from the `checkout.session.completed` payload
in the webhook.

### 1.7 `/admin/jobs/run` fatals — redeclared + missing functions
`app/controllers/admin/jobs.php:167` redeclares `send_refund_email()`
(collides with `mailer.php:233`); :87 redeclares `process_email_job()`
(vs `cron.php:93`); :159/:182/:204 call nonexistent `send_email()`; reads
`$config['mail'][...]` keys that exist in no config.
**Fix:** delete the duplicated logic; call the real mailer/cron functions.

### 1.8 Cron worker swallows all exceptions silently
`app/lib/cron.php:69-71` catches every Throwable with zero logging.
**Fix:** `error_log()` job id/type/message before marking the attempt failed.

### 1.9 Cart never cleared after purchase
No cart clear anywhere post-checkout.
**Fix:** clear the cart cookie on verified `checkout_success` load.

### 1.10 Smaller checkout-path defects (fix while in there)
- Discount tier selection depends on config array order
  (`app/lib/cart.php:194-200`) — select the max qualifying tier.
- Per-photo `photos.price_pence` override written by bulk tool but never read
  by `cart_price()` (`cart.php:132`) — honour it.
- Dead `/api/download/` block with hardcoded `'fallback-key'`
  (`order_tracking.php:87-91`) — delete; real path is `/download/{token}`.
- Non-atomic, MySQL-syntax job claim (`cron.php:31-46` and `jobs.php:28-43`):
  claims oldest pending then re-reads *newest running* row — reprocesses a
  stuck row forever. **Fix:** claim by explicit id, portable SQL. (Also the
  root cause of "photos stuck at processing", see Stage 2.)
- `app/lib/email.php` + its `queue_email*` functions: fully unwired second
  email subsystem by its own doc comment — delete the file, leave the
  migration's orphan tables alone.

### 1.11 `$config['site']['url']` does not exist — warnings everywhere
Config defines `site.base_url` only. Unguarded reads warn + concat null:
`app/views/public/cart.php:4`, `checkout_success.php:4`,
`checkout_pending.php:4`, `order_tracking.php:4`, `order_verify.php:4`,
`event.php:4`, `404.php:4`. Guarded reads silently emit wrong URLs
(`layout_header.php:14`, `search.php:6`, `home.php:5`, `app/lib/seo.php:73,113`,
`app/controllers/public/sitemap.php:13`).
**Fix root cause:** one helper (e.g. `site_base_url()`) reading
`site.base_url`; replace every `['site']['url']` read.

### Stage 1 tests
`tests/bootstrap.php:38-43` hard-requires MySQL; dev config is sqlite — the
suite cannot run at all. Fix the harness first (honour the sqlite driver or
run MySQL in the dev container). Then add missing coverage: checkout
controller end-to-end (empty cart / invalid email / happy path with stubbed
Stripe), webhook through the controller (signature, idempotency, queued job
types must be ones the worker accepts), order-tracking render.

---

## STAGE 2 — Uploads

Root causes for each reported symptom:

### 2.1 "INIT failed" — three real causes behind one generic alert
- `admin-upload.js:97` discards the server's JSON error body: CSRF 403,
  `session_id required`, `Session not found`, `No files specified` all become
  the same "Init failed" alert. **Fix:** surface the server's `error` field.
- **CSRF token goes stale:** `csrf_verify()` (`app/lib/csrf.php:40`) unsets
  the session token on success, so any admin POST in another tab invalidates
  the token embedded in the upload page → 403 → "Init failed" intermittently.
  **Fix root cause:** stop single-use-consuming the session token (keep one
  per-session token verified with `hash_equals`, standard practice), or at
  minimum re-issue and update the page token via response header. This also
  fixes random 403s across all 25 admin forms.
- **`post_max_size` overflow wipes $_POST** → CSRF failure masquerading as
  auth error, and no PHP limit handling exists anywhere. **Fix:** detect
  empty `$_POST` + `CONTENT_LENGTH > post_max_size` and return a clear
  "file exceeds server limit" JSON error; surface limits in the UI.

### 2.2 Upload glitches — protocol defects
- `rejected[]` shifts indexes: `uploadFiles()` (`admin-upload.js:107-115`)
  indexes by selected-files position; one rejected file uploads every
  subsequent file under the wrong `file_id`/`chunks_total`. **Fix:** map
  accepted entries back by name/size, show rejected files in UI.
- Chunk retry double-counts: server increments `chunks_received` blindly
  (`upload.php:158-160`), so a retried chunk overshoots and finalize's strict
  `!==` check (`upload.php:202`) fails. **Fix:** dedupe by chunk file
  existence server-side; make the check `>=` insufficient — count actual
  chunk files instead.
- One failed file aborts the whole batch (`uploadFile` rethrows :153, no
  try/catch in `uploadFiles`). **Fix:** per-file error handling, continue
  batch.
- Error responses lack `Content-Type: application/json` (13 sites in
  `upload.php`). **Fix:** set the header once at dispatch.

### 2.3 Leaving the page interrupts upload
No resume, no beforeunload warning, batch state in module variables only.
The schema (`upload_files.chunks_received`, on-disk chunks) already supports
resume; no code reads them for it.
**Fix (stability tier):** add a `beforeunload` warning while a batch is
active, and a resume-status endpoint (`/admin/upload/status?file_id=`)
returning which chunks exist so the JS restarts a file from the first
missing chunk rather than chunk 0. True background upload is Phase 2.

### 2.4 Uploaded images don't appear in gallery — the drain never runs
- `#jobDrain` UI exists (`app/views/admin/upload.php:40-42`, "keep this tab
  open") but **no JS anywhere calls the drain endpoint** — with no OS cron,
  photos sit at `processing` forever. **Fix:** small JS that polls
  `/admin/jobs/run` (fixed in 1.7) while jobs are pending, and auto-refreshes
  the upload page's status list when jobs complete.
- Claim/execute race (see 1.10) makes even the cron drain reprocess a stuck
  `running` row forever. Fixed in Stage 1.
- Silent dead-lettering: 3 failed attempts → `status='failed'` invisible;
  photo stuck at `processing`. **Fix:** admin jobs page shows failed jobs +
  a retry button; photo grid in admin shows processing/failed states.

### 2.5 Derivative correctness bugs (found while tracing)
- The +7-day `cleanup` job deletes `{token}-1600.jpg`, but the lightbox
  unconditionally loads `-1600` (`event.js:41,46,52`) and grid srcset lists
  it — photos older than 7 days 404 in the lightbox. **Fix:** decide the
  actual policy: keep 1600 (it's the sales-preview size) and delete the
  cleanup queueing, or make the lightbox fall back. Recommend: keep 1600,
  drop the cleanup job type, purge the 144 stranded pending cleanup jobs.
- Derivative math is wrong (`app/lib/images.php:45,101`): scales by smallest
  dimension so `-1600.jpg` of a 6000×4000 file is written at 6000px and
  `-400.jpg` at ~1500px — wrong sizes, huge files, and the watermark
  min-width gate compares the nominal size not the real output. **Fix:**
  scale by longest edge to the nominal size.
- `-1200.jpg` is requested by home hero (`home.php:15`) and event OG image
  (`event.php:5`) but never generated → home hero image is a guaranteed 404.
  **Fix:** add 1200 to the derivative set (or point hero at 1600; decide by
  what srcset needs — adding 1200 is cheap and correct).
- Orphaned `storage/tmp/uploads/` dirs (15 present) never cleaned. **Fix:**
  age out `uploading` rows + chunk dirs older than 24h in the cron drain.

### Stage 2 tests
Integration tests for init/chunk/finalize happy path, chunk retry idempotency,
rejected-file index mapping, resume-status endpoint, derivative job → `live`
status, and a regression test that every size in grid srcset/lightbox/hero
markup is actually generated.

---

## STAGE 3 — PHP warnings, console errors, QA sweep to zero

(Being finalised — third exploration agent still reporting. Known so far:)
- `['site']['url']` warnings fixed in Stage 1.11.
- `tests/` harness fixed in Stage 1.
- Sweep: run every public + admin page via the dev server, tail PHP log,
  fix every warning/notice at root cause. Browser console pass on every page
  (Chromium is available for driving).
- Audit for `@` suppression / `error_reporting` downgrades — remove.
- Repeat page-by-page until a full crawl produces zero log lines.

---

## STAGE 4 — Frontend features (only after Stages 1–3 are clean)

### 4.1 Hero contrast
Current treatment is only a bottom-up `color-mix` gradient
(`podium-ink.css:669-684`); title/eyebrow use `--text`/`--text-muted` over a
50%-transparent band of photo. **Fix:** strengthen the overlay (deeper
gradient stop behind the text block), use `--surface-on-media` tokens, and
verify against WCAG contrast on light and dark photos. No text-shadow slop;
do it with the gradient + token values.

### 4.2 Masonry grid (Pixieset-style)
Current: uniform squares (`aspect-ratio: 1`, centre-crop) via CSS Grid
(`podium-ink.css:1038-1076`). `photos.width/height` are in the DB and already
rendered as width/height attributes (`_photo_grid_items.php:27-28`) — layout
can be computed with no schema work.
**Approach:** CSS-first justified-rows or column masonry with JS measurement
fallback (vanilla, no deps per hard constraints). Must integrate with:
- filter re-fetch doing `photoGrid.innerHTML = html` (`event.js:201-213`)
- client-side search toggling `display:none` + empty-state
  `gridColumn: 1 / -1` (`event.js:233,242`) — both assume CSS Grid today.
Handle `width/height = 0` rows (photos created outside the upload path).

### 4.3 Favourites
Reuse-but-rework the dead scaffolding: `migrations/006` wishlist tables +
`app/lib/wishlist.php` (unwired), orphan `.heart-button` CSS
(`podium-ink.css:1658-1690`), orphan a11y strings (`accessibility.js:79,123`).
Needs: migration adding `email`/`name` to wishlists (schema has neither),
routes (`/favourites/*`), heart UI on grid + lightbox (single-tap per product
rules), first-tap identity prompt (name + email, then silent), HMAC cookie
identity (same pattern as cart), favourites view page, admin list view
(per-event: who favourited what).

---

## STAGE 5 — Admin features (after Stages 1–3)

(Details pending third exploration report: events form fields, sales overflow,
settings buttons, health page contents.)
- Gallery manager replacing Events: card layout, sort (date/A-Z/sales/custom),
  drag/drop ordering, collections/folders.
- Hide driver/car-number + driver-name fields from the gallery workflow.
- Move sales analytics into per-event view; fix Total Orders overflow.
- Settings: consistent buttons, descriptions/tooltips/help text.
- System health: plain-English Good/Warning/Error, explain cron status.

---

## Verification (every stage)

1. Dev server via /tmp/router.php + PHP log tail: zero warnings/notices on
   every page touched.
2. Browser drive (Chromium/Playwright available): console clean, network
   clean (no 404 assets), CSP violations zero.
3. `vendor/bin/phpunit` green after the harness fix; new tests per stage.
4. Checkout: end-to-end with Stripe test keys if provided, else verified to
   the Stripe API boundary with a stub + webhook simulated via signed payload.
5. Email: `dev_mode=local` writes to storage/dev-emails.log — assert receipt
   content after simulated webhook.
6. Uploads: real multi-file upload through the browser, kill/retry a chunk,
   verify resume, verify derivatives appear and photo goes `live` without
   manual cron.

## Commit strategy
One commit per numbered fix-group, on `claude/plugin-skill-setup-y9v6kx`.
Stage order is strict: 1 → 2 → 3, then 4/5.

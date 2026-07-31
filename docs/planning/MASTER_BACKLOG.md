# PowerMedia Gallery — master backlog

Every idea submitted and discussed across this project, consolidated. Paste this whole file into a fresh session to resume anything below — each item has enough context to stand alone. Two items (marked ⚠️) only ever existed in chat and were never written to a file until now; everything else links to a delivered artifact.

Repo: `hazzaattemptscoding/open-source-gallery`. Last confirmed state: `0b85a00` (main), which includes the admin-refined.css v2 coverage pass and the cohorts date bug still unfixed.

---

## 0. Design system reference (needed for any UI work below)

Established across several sessions, kept here so nothing has to be re-derived:

- Tokens: `--bg --bg-alt --text --text-muted --border --error --success`, plus `--accent` family added at OKLCH hue 315–318 (mulberry, not Tailwind's 293 violet — 293 is the AI-gradient tell).
- Type scale `--text-xs` … `--text-hero`, spacing `--space-1` … `--space-8`.
- Easing: `--ease-out` = `cubic-bezier(0.23, 1, 0.32, 1)`, `--ease-in-out` = `cubic-bezier(0.77, 0, 0.175, 1)` — already correct in the codebase, never touched.
- Square corners, deliberate, kept throughout.
- Fonts: Geist Sans / Newsreader / Geist Mono — well-paired, never swap them.
- Rules: one accent-filled button per screen, everything else outlined; three font weights only (600/500/400); animate only `transform`/`opacity`, never `transition: all`, never `ease-in` on UI; numbered steps only for genuine sequences; `prefers-reduced-motion` and `hover: none` always handled.
- Constraint that overrides everything: the admin customiser (`app/lib/customize.php`) can override five tokens at runtime via `/api/styles.css`, which must stay last in the cascade. Any new rule must derive from tokens, never hardcode a colour.

Source docs: `POWERMEDIA_PURPLE.md`, `POWERMEDIA_ADMIN_INSTALL.md`.

---

## 1. Lightroom export plugin — PAUSED

**Status:** built, then explicitly paused ("ignore the plugin stuff for now"), not resumed since.

- Full Lua plugin delivered: `main.lua`, `config.lua`, `http_client.lua`, `api.lua`, `export.lua`, `keyword_mapper.lua`, `ui.lua`, tests, docs. Chunked upload, keyword→tag mapping (`kart:101`→`kart_number`), retry logic.
- Gallery-side endpoints (`api-photos-upload.php`, `api-photos-tags.php`) were designed **before** the real codebase was read closely, and are now known to be wrong in two ways:
  1. **Auth**: invented a standalone Bearer check instead of reusing the real `api_keys` / `validate_api_key()` / `has_api_permission()` system that already exists and is already wired up for reads at `/api/v1/photos`. Needs a `write:photos` permission added to the real system, not a parallel one.
  2. **Tag columns**: written against a generic `tag_key`/`tag_value` model. Real schema is `photo_tags.kart_number` / `driver_name` / `class` — fixed columns, not EAV.
- **Also blocking**: `validate_image_file()` in `app/lib/upload.php` accepts only `image/jpeg` and `image/png`. The plugin spec assumes chunked RAW (CR3, TIFF) upload. As written, every RAW file would transfer completely, then get rejected at finalize.

**To resume:** rewrite the two endpoint files as additions to the real `app/controllers/api/photos.php`, using real auth and real columns, and resolve the MIME whitelist question first.

---

## 2. Design & appearance

| # | Item | Status | Where |
|---|---|---|---|
| 2.1 | Purple accent direction, grounded against AI-gradient anti-patterns (impeccable, taste-skill, Emil Kowalski skills) | Done | `POWERMEDIA_PURPLE.md` |
| 2.2 | Global admin styling pass v1 | Superseded by 2.4 | — |
| 2.3 | Admin UI confusion audit — bold/centred text, unclear buttons, missing separators | Done | Before/after mockups in chat; fixes folded into `admin-refined.css` + `upload.php.patched` |
| 2.4 | Full admin coverage pass v2 — found 106 of 194 classes had zero CSS, including `.btn-primary`/`.btn-danger` | Done, 0 unstyled remaining | `admin-refined.css` (890 lines, csstree-validated) |
| 2.5 | Table appearance fix | Scoped, not executed | `ADMIN_APPEARANCE_PLAN.md` — root cause is 173 inline `style=` attributes beating the stylesheet, worst in `customize.php` (35), `orders.php` (21), `stats.php` (19). Needs markup surgery per file, not more CSS. |
| 2.6 | Charts — `analytics.php` calls `new Chart()` three times but Chart.js is never loaded anywhere (no CDN link, no vendored file) | Fixed | `charts.php` — server-rendered SVG (line/bar/hbar/sparkline), no JS dependency, inherits design tokens. Install steps in `ADMIN_APPEARANCE_PLAN.md`. Key names from `hourly_distribution`/`sales_by_event` need verifying against the controller before wiring in. |
| 2.7 | Stock/placeholder images not loading | Fixed | `generate_placeholders.php` — was writing to `storage/hires/` (the *original*-upload path); real derivatives live at `public/media/d/{token}-{size}.jpg`, produced by a job the seeder never queues. Rewritten to write all three real sizes (400/800/1600) directly. |
| 2.8 | Video placeholders | Not building | Confirmed: **no video-derivative pipeline exists anywhere in the codebase.** `event.php` renders `<video src="/media/v/{token}.mp4">` but nothing has ever written to that path. This is a from-scratch feature decision, not a placeholder-script fix. Corrected generator now reports and skips video rows rather than faking them. |
| 2.9 ⚠️ | Persistent upload tray, "like Google Drive" — uploads should survive navigating to another admin page | Scoped only in chat, never written to a file | See §5 below for full reproduction |

---

## 3. Photographer workflow

**Status:** Explained, diagrammed. Five real stages traced through actual controllers: create event+session (`admin/events.php`, `admin/sessions.php`) → chunked upload with real init/chunk/finalize handshake (`admin/upload.php`) → automatic processing via job queue, status flips `processing`→`live` only on success (`derivatives.php`) → bulk tag kart/driver/class (`admin/tagging.php`) → publish flips `is_published`, triggers guest checkout, Stripe webhook, signed download link. No file produced — this was a conversational explanation plus an inline diagram.

---

## 4. QOL research — round 1 (general photographer additions)

**Status:** Delivered as research memo, evidence-graded. `POWERMEDIA_QOL_RESEARCH.md`

1. **EXIF capture at ingest** — only `taken_at` is extracted; lens/aperture/shutter/ISO all discarded. Foundation for everything else in this memo.
2. **View→sale conversion** — `photos.view_count` exists, never joined to `order_items`. The high-view/zero-sale list is the actionable output.
3. **Kart-number OCR** — RBNR literature ceiling 93.4%/F0.94, but implementation variance huge (one reimplementation got 38%). `event_entries` gives a closed-set constraint (~40 valid numbers) that should meaningfully beat open-set approaches used elsewhere.
4. **Burst grouping** — perceptual hashing (dHash/pHash), pure PHP, no ML, uses existing job queue.
5. **Delivery-speed instrumentation** — `audit_log` + `orders.created_at` can test the vendor-claimed 14-day/25%-conversion relationship directly against your own events before believing it.
6. **Selfie/face search** — recommended against. Sportograf's own numbers are compelling (+120% photos assigned) but face vectors are UK GDPR Article 9 special-category data; disproportionate compliance surface for a sole trader. Kart OCR gets most of the same coverage without the biometric exposure.
7. **Form/function extras** — "your race day" narrative bundles, personal shooting-fingerprint dashboard (focal length/shutter/aperture vs sale rate), track-position clustering, the panning-shutter-vs-sale-rate curve.

---

## 5. QOL round 1 follow-ups — raised inline, one never written up ⚠️

From the "I like viewcount" discussion, after the round-1 memo:

- **View-count caveats** (rate-limited 1/IP/second, not deduped; shared-IP undercounting; silent loss if cron stalls) — captured, ended up folded into audit finding M1 in `GALLERY_AUDIT.md`.
- **High-view/zero-sale SQL query** — written out in full in chat (joins `photos`/`order_items`/`photo_tags`, flags the `order_items.photo_id IS NULL` bundle-purchase trap). Never saved to a file — reproduce from this conversation if needed, or ask to regenerate.
- **⚠️ Zero-result search logging — never written to any file.** Full idea, reproduced here:

  > The event page parses `?kart=23&driver=...&class=...` and **discards the filters immediately** — nothing logs them. That's demand data sitting unused. A driver searching kart 23 with zero results is either (a) a coverage gap — you never shot them, or (b) a tagging error — you shot them but tagged wrong, which is otherwise nearly undiscoverable, since a mistagged photo looks fine to you and is invisible to the person searching for it. `event_entries` (once populated — see §6.2 below) tells the two cases apart: if kart 23 is a valid entry and zero results come back, it's one of those two; if it's not a valid entry, it's just a typo or a non-existent kart.
  >
  > Proposed: `search_log(event_id, filter_type, filter_value, result_count, created_at)`, one insert on the event controller when a filtered result set is empty. Aggregate only, no visitor identity — stays clear of GDPR entirely. Smaller than the conversion dashboard, arguably higher value.

  This was interrupted by "instead just audit my repo" and never returned to. Worth building.

---

## 6. QOL research — round 2 (new-idea request, all verified against code)

**Status:** Delivered as research memo. `POWERMEDIA_QOL_ROUND2.md`

1. **Public driver-name exposure** — live issue, not just an idea. `event.php` and `search.php` render every driver name into a public, unauthenticated dropdown/facet; `sitemap.php` submits event URLs for indexing. Confirmed against NSPCC Child Protection in Sport Unit guidance (under-16 consent, right to decline regardless of parental consent) and GDPR Art. 6/9 reasoning. Proposed: default to kart-number-only, per-entry suppression flag, `noindex`, a takedown path that doesn't currently exist. **This is the item most worth doing regardless of what else happens** — ties directly into §8.4 below (visibility tiers) as the natural place for it to live.
2. **`event_entries` has no import path** — confirmed only write is the dev seeder. Both consumers (`admin/tagging.php`, public filter hints) are built and read from a permanently empty table on any real install. Blocks the OCR idea in round 1 (no closed set to constrain against) and blocks §9 (QR cards) below. Proposed: CSV paste/upload on the event form, ~80 lines.
3. **No real backup** — `admin/export.php` is metadata-only CSV; nothing copies `storage/hires/`. Largest uninsured risk in the system, disguised by the word "Export." Proposed: backup job type in the existing queue + a restore-drill habit (pull one random photo, confirm it opens, log the date).
4. **No event profitability data** — `events` has three price columns, zero cost columns. Proposed: `cost_travel_pence`, `cost_other_pence`, `hours_worked`, then rank by effective hourly rate rather than raw revenue.
5. **Returning buyers invisible** — guest checkout is correct and shouldn't be reversed, but `orders.email` already exists; hash it, group by hash, no new personal data, no accounts. Currently indistinguishable from one-off visitors.
6. **Organiser report** — one-click shareable read-only summary (photos published, views, drivers covered) per event. Marketing disguised as a feature; the evidence organisers use to justify booking a photographer again.
7. **Drop-folder ingest** — watched folder ingesting against a preselected session while shooting, using the chunked upload path and job queue that already exist. Practical lever on the delivery-speed question from round 1.
8. **QR cards** — the filter URL already works (`/e/{slug}?kart=23`); generate one per entry from the imported list (§6.2), printable sheet, hand out trackside. Directly attacks the discovery problem that item 5 in §5 (zero-result search) measures.

---

## 7. Security & code-health audit — two rounds

### Round 1 — `GALLERY_AUDIT.md`

| ID | Finding | Status as of last check (`0b85a00`) |
|---|---|---|
| C1 | `tagging.php` orphaned library, queries columns that never existed → public event page 500'd on every load | Fixed — file deleted, zero `tag_type`/`tag_value` references remain |
| H1 | `admin/tagging.php` — only admin POST handler with no CSRF check | Still open |
| M1 | 46 `catch (Throwable)` blocks in `app/lib/` silently return null/false/[] | Still open, count unchanged |
| M2 | Upload MIME whitelist is JPEG/PNG only, conflicts with plugin's RAW spec | Still open — decide before resuming §1 |
| M3 | `app/.htaccess` missing (storage/config both have one) | Still open |
| M4 | `.session-select-row` CSS/JS display conflict on upload page | Fixed |
| L1 | `${var}` PHP 8.2 deprecation in `customize.php:158` | Fixed |
| L2 | Webhook timestamp tolerance only checks the past, not the future | Open, low priority |
| L3 | Fatal config errors write to STDERR, unreliable under PHP-FPM | Open, low priority |
| L4/L5 | Five near-identical button classes; `.btn-pill` has `border-radius: 0` | Fixed via `admin-refined.css` consolidation |
| L6 | 18 of 37 admin views bypass the shared layout partials | Still open (still 18) |

**What's confirmed solid, don't touch:** no SQL injection anywhere (parameterised throughout, even the dynamic `WHERE` builders), consistent XSS escaping, download tokens are 256-bit CSPRNG stored as SHA-256, Stripe webhook verification uses `hash_equals` with correct tolerance, session cookies correctly hardened (`httponly`/`secure`/`SameSite=Strict`, regenerated on login), first-run setup self-locks, secrets gitignored and `chmod 0600`, upload MIME is content-sniffed not extension-trusted.

### Round 2 — "compare to now," after 5 new commits

- Verified C1, L1, M4, L4/L5 fixed as above.
- **Independent audit by the other session found 7 schema-drift bugs; this audit had only caught 1 of them (C1).** The other 6, for the backlog record: `admin_roles.permissions` column queried but never migrated (breaks `/admin/admins` role list — **also directly relevant to §8.1 below**, which depends on roles actually working); `cohorts` table column names wrong in two functions; `upload_files`/`upload_batches` column names wrong (broke "reload persisted state" on the upload page); `/admin/my-sessions` was 100% non-functional and got removed entirely; `events/{list,form}.php` had a wrong relative path fataling both pages; `email_templates.php` had two parse errors (ternaries inside heredoc interpolation, invalid PHP) breaking `/admin/jobs`; and a CSS bug — `.lightbox` had unconditional `display: flex`, so the lightbox shell covered the entire public event page in black from first paint on every load, arguably worse than C1 and missed entirely by this audit.
- **Methodological gaps admitted and one fixed**: the schema check only compared table names, never column names (root cause of missing 4 of the 7); no PHP linter available in this environment (root cause of missing the 2 parse errors); CSS was audited for typography/weight but never for layout-breaking rules, and the public page was never actually rendered to check (root cause of missing the lightbox bug); include paths were never checked.
- **`schema-drift-check.py` built** as the corrected method — a real column-level cross-reference between migrations and every `INSERT`/`UPDATE` in the codebase. Run against current tree: found `jobs.wol_sent_at`/`fulfilled_at`/`alert_sent_at` used by `app/lib/fulfillment.php` but absent from the `jobs` schema, plus status values (`'fulfilled'`, `'processing'`) not in the ENUM. This one is already known and explicitly deferred in a code comment — dormant unless Remote-NAS fulfillment mode is switched on, at which point every call fails.

### Round 3 — deep UI audit ("in depth audit... ugly... animations missing")

Covered in §2.4 above. Headline number: **106 of 194 admin classes had zero CSS**, `.btn-primary`/`.btn-danger` among them.

---

## 8. New feature requests — data protection, marketing, gallery org, media

**Status:** All four scoped in full. `FOUR_FEATURES_SCOPE.md`

### 8.1 GDPR data-protection gate (PIN/pass before viewing GDPR data)
Reuse the TOTP infrastructure that already exists on `admin_users` (secret/enabled/last-step, already has an enrolment flow) as a **step-up re-auth** — already logged in, re-confirm TOTP before a sensitive action, valid 10 minutes — rather than inventing a second PIN system. Gate list: `admin/export.php`, `admin/audit_log.php`, order detail view, `admin/admins.php`. Needs `get_all_roles()`'s missing `permissions` column fixed alongside (see §7 round 2), since step-up auth wants real permission bits to key off, not just role names.

### 8.2 Marketing consent
Zero existing infrastructure confirmed (no consent column anywhere). New `marketing_subscribers` table with a `consent_source` field (matters for compliance defensibility, not just analytics — need to show *where* consent was captured). Checkbox at checkout, unchecked by default. Explicitly **not** wiring an actual send pipeline yet — `emails.purpose` ENUM is transactional-only, and that's probably deliberate given different legal bases and deliverability concerns between transactional and marketing mail.

### 8.3 Mandatory email to view galleries
Confirmed **zero access gating exists today** — any published slug is fully open. This is building the first public-side gate from scratch, not extending one. Real fork: **soft gate** (browse freely, email unlocks full-res/download — closer to Pixieset's actual behaviour, keeps current SEO/discoverability) vs **hard gate** (nothing renders without email first — simpler, but kills the tap-a-link-and-see-your-photos flow that the QR-card idea in §6.8 depends on, and kills sitemap value). Recommended soft gate by default. `gallery_access` table proposed, with a `marketing_opt_in` column that can double as §8.2's checkbox if you want one consent capture point instead of two.

### 8.4 Gallery organisation (Pixieset-style groups/visibility)
Turned out to be **three separable asks**, not one:
- **Visibility tiers** (public/unlisted/password) — real security-posture change, `events.visibility` ENUM + password hash. This is the actual "private gallery" feature, and the natural home for §6.1's driver-name suppression and sitemap exclusion to live together.
- **Groups** (season/client/series sitting above individual events) — purely organisational, zero security implication, safe to defer indefinitely. New `galleries_groups` table, nullable FK from `events`.
- **Private-hire vs public-event distinction** — turns out to need almost no new schema at all. Existing per-event pricing (`price_single`/`price_session`/`price_event`) already supports a private job fine; it's just an event with one session and `visibility = 'password'`. At most a `booking_type` label for your own admin-side filtering.

Build order given: `booking_type` (trivial) → `visibility` tiers (medium, pairs with §6.1) → `galleries_groups` (medium, no urgency).

### 8.5 Stock images not loading
Fixed — see §2.7. Root cause was the placeholder generator targeting the wrong path entirely, compounded by the seeder never queuing a derivative job and there being no video pipeline at all.

---

## 9. Bug found outside all of the above: cohorts seeding date-overflow

**Status:** Diagnosed precisely, fix given, **not yet applied to the repo**.

`app/lib/dev_setup.php:369` — `date('Y-m-01', strtotime("-{$m} months"))` in a loop seeding 4 monthly cohort rows against `cohorts.uk_cohort_month` (unique constraint). This hits a classic PHP `strtotime` day-of-month overflow: subtracting months from a day-of-month that doesn't exist in the target month (e.g. "July 31 minus 1 month" — June has no 31st) causes PHP to roll forward into the *next* month instead of clamping, producing a duplicate `cohort_month`. Reproduces on the 29th/30th/31st of any month landing on a shorter target month — roughly a third of all calendar days, not an environment fluke. Simulated exactly for 2026-07-31: `$m=0` and `$m=1` both resolve to `2026-07-01`; a **second**, not-yet-seen collision exists between `$m=2` and `$m=3` (both resolve to `2026-05-01`) that the script never reaches because it exits on the first exception.

**Fix:** anchor to the first of the month before subtracting —
```php
$anchor = strtotime(date('Y-m-01'));
$month = date('Y-m-01', strtotime("-{$m} months", $anchor));
```
Both collisions fixed by this one change to line 369.

---

## Suggested overall priority, everything considered

1. Apply the cohorts date fix (§9) — blocks fresh installs entirely, one line.
2. Public driver-name exposure (§6.1 / §8.4 visibility tiers) — the one live safeguarding/privacy risk in the whole backlog.
3. `event_entries` CSV import (§6.2) — unblocks OCR, QR cards, and makes tagging actually work on real installs.
4. Reconcile MIME whitelist vs plugin RAW spec (§7 M2) — decide before touching §1 again.
5. `admin/tagging.php` CSRF fix (§7 H1) — one function call, restores a universal invariant.
6. Table markup cleanup + charts install (§2.5, §2.6) — mechanical, high visible impact.
7. Zero-result search logging (§5) — small, previously dropped mid-thought.
8. GDPR step-up (§8.1), marketing consent (§8.2), gallery email gate (§8.3) — in that order, each low-medium effort.
9. Backup + restore drill (§6.3) — largest uninsured risk, deserves its own session.
10. Everything else — genuinely optional, no urgency, safe to pick up in any order.

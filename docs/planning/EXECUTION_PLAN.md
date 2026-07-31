# Execution plan

Derived from the actual code at commit `bdddbae`, not from the backlog docs' claims. Where `MASTER_BACKLOG.md`, `MASTER_BACKLOG_ADDENDUM.md`, or the correction prompt disagree with the code, the code won. Every claim below was verified against the working tree on 2026-07-31.

Each task is written to be picked up by a fresh session with no other context. Read the task, do the task. Skills named per task are installed for this repo (see CLAUDE.md); invoke them, and say so if they are not active in your session.

## Verification summary: what is actually true right now

| Claim | Verdict |
| --- | --- |
| Cohorts seeding date bug | Fixed. `app/lib/dev_setup.php:368` anchors to the 1st of the month (commit `6fe7f7d`). Do not re-fix. |
| `seo.php` require path | Still broken. `app/views/public/partials/layout_header.php:36` is one `../` short. Every public event page 500s. |
| Public driver-name exposure | Regressed to worse than originally found. Commit `e997dfb` deleted the whole visibility feature; full names now render publicly with zero gating. |
| Upload persistence | Cosmetic only. `progress-widget.js` is a page-scoped widget; no Service Worker, no server-side resume. Uploads still die on navigation. |
| Inline-style cleanup | Mostly done. `orders.php` and `stats.php` clean; `app/views/admin/customize.php` still has all 35 inline `style=` attributes. |
| Four loose duplicate files at repo root | **Stale claim, flagged.** No such files exist in the tree (`git ls-files` confirms). The two unusual root `.txt` files are the original architecture and schema docs, not duplicates. Nothing to delete. |
| `admin/tagging.php` CSRF gap (H1) | Confirmed precisely. All 16 other admin controllers with POST handling call `csrf_verify`; `handle_bulk_tagging` (`app/controllers/admin/tagging.php:58`) reads raw JSON from `php://input` with no check. |
| `event_entries` import path | Still none. Only writer is the dev seeder (`app/lib/dev_setup.php:270`). |

## Phase A: restore correctness. Small, ordered, can be one session.

- [ ] **A1. Fix the `seo.php` require path.**
  `app/views/public/partials/layout_header.php:36` reads `require_once __DIR__ . '/../../lib/seo.php';`. It needs one more `../` (line 7 of the same file has the correct 3-hop form). This branch fires whenever `$event` is set, meaning every `/e/{slug}` gallery page currently 500s. One character, do it first: nothing on the public side can be evaluated until the page renders.
  Verify: serve locally (`php -S` against the SQLite dev seed from `install-mac.sh` / `install-linux.sh`), load an event page, confirm 200.

- [ ] **A2. Remove public driver-name rendering. This is the top substantive priority.**
  Context a fresh session needs: driver names of (often under-16) kart drivers render on public, unauthenticated pages. NSPCC Child Protection in Sport Unit guidance and GDPR reasoning are summarised in `MASTER_BACKLOG.md` §6.1. A tiered-visibility feature (hidden/initials/full) was built in commit `8f6a9c8` and then removed in `e997dfb`, whose own message records the product decision: "User requested that driver names never appear publicly." The revert restored the pre-feature code instead of the names-hidden state, so the original exposure is live again. Do not rebuild the tiers. Make names never render publicly, full stop.
  Changes:
  - `app/controllers/public/event.php:90-92`: delete the `DISTINCT driver_name` query; stop passing `driverOptions` to the view.
  - `app/views/public/event.php:41, 61-66`: remove the driver `<select>` and the `$hasDriverFilter` logic.
  - `app/lib/search.php`: remove `driver_name` from public search matching (lines ~52, 71, 104, 122) and drop the drivers facet from `get_search_facets`.
  - `app/views/public/search.php:62-72, 118, 155`: remove the drivers facet block, the driver filter param, and the "driver name" mention in the empty-state copy.
  - Check `app/views/public/_photo_grid_items.php:19` (`data-driver-tags`): if it emits driver names into public markup, remove it too.
  - Keep kart-number filtering everywhere: it is the discovery path the QR-card feature depends on.
  - Admin-side tagging and stored driver data are untouched; internal use is fine.
  - `drivers_visibility` column: migration 011 files are already deleted and no code references remain. Fresh installs never get the column. No repo action; note in the commit message that already-migrated dev databases carry an unused column that can be dropped manually.
  Verify: render event and search pages, grep the served HTML for any seeded driver name, expect zero hits.
  Skills: security-audit posture for the review, stop-slop for any copy edits.

- [ ] **A3. Close the CSRF gap on bulk tagging (audit H1).**
  `handle_bulk_tagging` in `app/controllers/admin/tagging.php:58` is the only admin mutation endpoint without CSRF protection. It consumes JSON from `php://input`, which is why greps for `$_POST` miss it. Add a `csrf_verify` check (helpers in `app/lib/csrf.php`; `csrf_verify_reusable` exists for endpoints that should not rotate the token). The client is `public/assets/js/admin-tagging.js`; send the token in a header or JSON field and validate it server-side, matching how the other 16 admin controllers behave.
  Skills: superpowers (test first: request without token is rejected, with token succeeds).

## Phase B: highest-leverage feature

- [ ] **B1. `event_entries` CSV import on the event form.**
  Context: `event_entries` (kart_number, driver_name, class per event) is read by admin tagging autocomplete and public kart filter hints, but the only thing that ever writes it is the dev seeder. On every real install it is permanently empty, so those features silently do nothing. This also blocks kart-number OCR (needs the closed set of valid numbers) and QR cards (one card per entry). Backlog sizing: roughly 80 lines, CSV paste or file upload on the admin event form, parsed into per-event rows.
  Hard constraint carried from A2: imported driver names are admin-side data. Do not surface them in any public view; A2 removed those sinks, do not reintroduce them.
  Skills: superpowers (TDD the parser: quoting, blank lines, duplicate kart numbers), stop-slop on the form copy and error messages.

## Phase C: admin UI completion, paired by page

- [ ] **C1. `customize.php`: retire the 35 inline styles and add the contrast advisor, one session.**
  `app/views/admin/customize.php` still carries all 35 of its original inline `style=` attributes (the worst remaining offender; orders and stats are already clean). While restructuring the markup anyway, add the scoped contrast advisor from the addendum: warn before saving token combinations that fail contrast (white-on-white class of mistake), computed client-side from the chosen values.
  Constraints: every colour derives from tokens, never hardcoded; the runtime override sheet `/api/styles.css` must remain last in the cascade (`app/lib/customize.php` is the owner).
  Skills: design-taste-frontend (audit first), minimalist-ui, impeccable.

- [ ] **C2. Health page job detail.**
  The `jobs` table already stores `type`, `payload`, `last_error`; the health page shows only a bare count. Surface the queue contents (recent failures with `last_error` prominently). Small, self-contained.
  Skills: minimalist-ui.

- [ ] **C3. Sales dashboard restructure.**
  Per the addendum: click an event row to see its revenue and drill into individual orders; remove the best-sellers widget (one-buyer-per-photo economics make it meaningless) and the refund concept entirely (buy-your-own-photo model). Charts use the existing server-rendered SVG renderer (`charts.php` work landed in `3610769`), no JS chart library.
  Skills: impeccable, minimalist-ui.

- [ ] **C4. Watermark presets UI.**
  The hard part already exists: watermarked previews with clean originals delivered post-payment. This is only a preset management UI on top (`app/controllers/admin/watermarks.php` is the existing surface). Do not touch the delivery path.
  Skills: emil-design-eng, design-taste-frontend.

## Phase D: dedicated-session builds. Do not compress these into other work.

- [ ] **D1. Upload persistence, done properly.**
  Current state: `public/assets/js/progress-widget.js` (commit `f531a7f`) is a good floating progress UI, but it is a plain page-scoped class. There is no Service Worker and `handle_init` (`app/controllers/admin/upload.php:39`) has no resume lookup, so navigating away still kills the upload. Keep the widget as the visual layer; build the mechanism beneath it per `MASTER_BACKLOG.md` §2.9: Service Worker (or at minimum beforeunload guard plus server-side resume) and a `handle_init` that recognises an existing incomplete batch for the same file set and resumes rather than restarting. This is a real design-and-build session, not a patch.
  Skills: superpowers (brainstorm the design before code), emil-design-eng for the widget interactions.

- [ ] **D2. Real backup plus restore drill.**
  `admin/export.php` is metadata-only CSV; nothing copies `storage/hires/`. Largest uninsured risk in the system. Add a backup job type to the existing queue (shared-hosting constraints: PHP and cron only, no daemons) and document a restore drill habit (pull one random photo from backup, confirm it opens, log the date).

- [ ] **D3. Privacy and consent block, in order: GDPR step-up auth, marketing consent, gallery email gate.**
  Full scope in `MASTER_BACKLOG.md` §8.1-8.3. Step-up re-auth reuses the existing TOTP infrastructure on `admin_users` (10-minute validity) gating export, audit log, order detail, and admins pages. Prerequisite: fix the `admin_roles.permissions` schema drift (column queried but never migrated, breaks the roles list) before building on roles. Marketing consent is a new `marketing_subscribers` table with `consent_source`, checkbox unchecked by default, no send pipeline yet. The gallery gate should be the soft variant (browse freely, email unlocks downloads); `gallery_access.marketing_opt_in` can double as the consent capture point so there is one capture, not two.
  Skills: security-audit, superpowers.

## Phase E: remaining audit hygiene, any order

- [ ] **E1.** M1: 46 `catch (Throwable)` blocks in `app/lib/` silently swallow errors. Route through a logging helper; keep pages resilient, stop losing the signal.
- [ ] **E2.** M3: add `app/.htaccess` denying direct access (storage and config already have one).
- [ ] **E3.** L6: 18 of 37 admin views bypass the shared layout partials. Public views got this treatment in `f126707`; mirror it for admin.
- [ ] **E4.** L2 (webhook timestamp tolerance ignores future skew) and L3 (fatal config errors write to STDERR, unreliable under PHP-FPM). Low priority, small.

## Parked, with reasons

- **Lightroom plugin** (backlog §1): stays paused. Blocked on the M2 decision (upload MIME whitelist is JPEG/PNG only; plugin spec assumes RAW). Decide M2 before any plugin work, then rewrite the two endpoint files against the real `api_keys` auth and real `photo_tags` columns.
- **Video derivative pipeline**: explicit earlier decision not to build. Placeholder generator correctly skips video rows.
- **`galleries_groups`**: purely organisational, no urgency, safe to defer indefinitely.
- **Kart-number OCR**: needs B1's closed set first. Revisit after B1 ships.
- **Zero-result search logging** (backlog §5): still worth building, small; natural follow-on to B1 since `event_entries` is what makes the logs interpretable. Slot after B1 if there is room.
- **"Total Orders" stat box**: code is clean, symptom unexplained. Needs a screenshot from the maintainer, not another blind code pass.

## Conflicts and discrepancies, stated once

1. **The correction prompt's item 6 is stale.** No loose duplicate files exist at the repo root; they were removed before this plan was written. Flagged rather than silently dropped, per instruction to trust the code.
2. **Driver names vs future visibility tiers.** `e997dfb`'s recorded intent is that names never appear publicly. If §8.4 visibility tiers are ever built, driver-name display must not return as a tier option unless the maintainer explicitly reverses that decision. The tiers feature, if built, covers gallery access (public/unlisted/password), not name exposure.
3. **Hard email gate vs QR cards.** A hard gate on gallery viewing kills the tap-a-QR-and-see-your-photos flow that B1's entry list enables. Soft gate only.
4. **M2 (MIME whitelist) gates the plugin.** Any RAW upload decision changes `validate_image_file` in `app/lib/upload.php` and has security implications; decide it deliberately, not as a side effect of plugin work.

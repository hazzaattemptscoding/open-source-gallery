# Master backlog — addendum (since v1)

Everything below happened after `MASTER_BACKLOG.md` was written. Read this alongside it, not instead of it — this only covers the delta.

---

## Bug fixed: `seo.php` wrong relative path (public event page 500s)

`app/views/public/partials/layout_header.php` requires `app/lib/seo.php` twice — once correctly (`../../../lib/seo.php`, line 7), once one `../` short (`../../lib/seo.php`, line 36), which resolves to a path that doesn't exist. The second one fires whenever `$event` is set — i.e. on every real gallery page. **Fix:** add one more `../` on line 36. One-character fix, but it was a hard 500 on every `/e/{slug}` page.

## Bug diagnosed, not yet applied: cohorts seeding date overflow

`app/lib/dev_setup.php` seeds 4 cohort months with `date('Y-m-01', strtotime("-{$m} months"))` from *today's* date rather than the 1st of the month. PHP's day-of-month overflow behavior means subtracting a month from a 31st, when the target month is shorter, rolls forward into the next month instead of clamping — producing a duplicate `cohort_month` and breaking `install-mac.sh` on roughly a third of calendar days (confirmed via simulation for the exact date this hit). **Fix given, not applied:** anchor to the first of the month before subtracting.

## Dev mode — built and shipped

- `install-mac.sh` now seeds a real `admin_users` row (`dev@localhost` / `dev-local-only`) and sets `security.dev_mode = true` in the generated config — a key that only the installer ever writes, never in `config.example.php`, so there's no path for it to reach production by accident.
- `require_admin()` auto-logs in as that seeded admin when the flag is set, instead of redirecting to `/admin/login`.
- Every admin page shows a banner — *"DEV MODE — login is bypassed"* — for as long as the flag is on, so it can never be silently left active somewhere.
- **Net effect:** re-run the installer once, and login friction should be gone until you deliberately flip the flag off.

## UI bugs found and fixed

- **Horizontal page scroll (global).** Wide tables (`cohorts` especially, 6+ columns with `white-space: nowrap` headers) were forcing the whole admin body wider than the viewport instead of scrolling internally. Tables now scroll within themselves via `overflow-x: auto`; `html, body { overflow-x: hidden }` stops any element from doing this again.
- **Settings basic/advanced toggle "getting bigger."** Root cause was almost certainly the same overflow issue compounding with an un-wrapped toggle bar. `.mode-toggle` now allows wrapping. Flagged as best-guess pending visual confirmation — ask again if it's still off.

## Still open, unresolved

- **"Total Orders" stat box, described as broken with "no top, big U."** Traced the template and the data function — both are clean, defensively coded, structurally identical to the seven other stat cards on the same page. Nothing in the code explains the symptom. **Needs a screenshot**, not another blind guess.
- Every item still marked open in `MASTER_BACKLOG.md` §7 (H1 CSRF gap, M1 silent errors, M3 `app/.htaccess`, L6 partials bypass) — untouched since v1, still accurate.

## New feature requests, mostly scoped rather than built

From one large message covering ~14 distinct issues across almost every admin page. Full detail is in that conversation turn; headline items:

1. **Sales dashboard restructure** — click an event to see its revenue directly; drop the best-sellers widget (fair, given one-buyer-per-photo economics) and the refund concept entirely (fair, given the buy-your-own-photo model); click into individual orders. **Not yet built.**
2. **Health page job detail** — `jobs` already stores `type`, `payload`, `last_error`; just needs surfacing instead of a bare count. **Scoped, not built.**
3. **Email management restructure** — split into admin/marketing/upload sections; templates open full-page instead of stacked. **Not yet built.**
4. **Customize page contrast advisor** — warn before someone picks white-on-white. Small, well-defined. **Not yet built.**
5. **Watermark presets** — good news, the underlying architecture (watermarked preview + separate clean original, download link only ever serves the clean file post-payment) already exists. What's missing is just a preset UI on top of a pipeline that already does the hard part. **Not yet built.**

## Confirmed still correct from v1, re-verified this session

- Upload-persistence gap (Service Worker + resume-lookup scope) — still open, still the right design.
- Events → Pixieset-style groups/visibility scope — still open, still the right three-way split (visibility tiers / groups / booking-type label).
- `event_entries` CSV import — still the highest-leverage single fix in the whole backlog, still unbuilt.

# Brand Alignment Plan: Gallery vs. Main Site

## Context

PowerMedia's main marketing site (powerrmediaa.com) has a bold, dark,
persuasion-driven identity. The gallery — this repo — currently runs on a
deliberately different aesthetic: flat white/black, minimal, editorial,
explicitly marked in `CLAUDE.md` as "Not swappable — one solid, timeless
look." Before any gallery code changes in this direction, this document
lays out what should carry over from the main site, what shouldn't, states
the aesthetic conflict plainly instead of resolving it silently, lists what
real brand assets are still missing, and gives a component-level change
list ready to hand off once a direction is picked.

**This is an analysis document. No code changed to produce it.**

## Research method & limitations

**The live site could not be fetched in this pass — not skipped, blocked.**
`WebFetch` returned a 403. A direct `curl` through this session's egress
proxy confirmed the cause: the proxy rejected the CONNECT to
`powerrmediaa.com:443` with `"gateway answered 403 to CONNECT (policy denial
or upstream failure)"`, visible in `$HTTPS_PROXY/__agentproxy/status` under
`recentRelayFailures`. Per this session's proxy documentation, a 403/407 at
that layer is an organization egress policy denial, to be reported rather
than routed around.

**Consequence: everything below about the live site's actual layout, copy,
and visual treatment comes from the reference description supplied in the
request, not from an independently rendered page.** Treat the site
description in this document as user-supplied ground truth, not as
independently verified fact. If a future pass gets network access to the
live site, re-verify before treating any of this as final.

What *was* verified directly, by reading this repo:

- `public/assets/fonts/` **does not exist.** There are no font files
  anywhere in the repository. `public/assets/css/podium-ink.css` declares
  `@font-face` rules pointing at `/assets/fonts/GeistSans-Variable.woff2`
  and `/assets/fonts/Newsreader-Variable.woff2` — both paths are broken.
  Newsreader has a working fallback via a Google Fonts CDN `@import` in the
  same file; Geist Sans has no shipped fallback and silently resolves to
  `'SF Pro Display', 'Helvetica Neue', sans-serif`. `admin.css` has the
  identical problem for a `GeistMono-Variable.woff2` that also doesn't
  exist.
- **No logo file** (`.png`, `.svg`, `.webp`, or otherwise) exists anywhere
  in the repository.
- An admin-facing customization mechanism **already exists** and already
  covers most of what real brand values would need:
  `app/lib/customize.php`, `app/controllers/admin/customize.php`, and
  `app/views/admin/customize.php` let an admin set hex colors (`text`,
  `text_muted`, `bg`, `bg_alt`, `border`), fonts (`body_font`,
  `heading_font`, `mono_font`), heading letter-spacing, max content width,
  grid column count, and upload a logo. Values persist to
  `storage/customize.json` (gitignored), separate from `config/config.php`.
  Once real values exist, this is where they'd go — no new plumbing needs
  building for that part.
- `config/config.php`'s `branding` key currently holds only
  `['theme' => 'podium-ink']`; no color or font sub-keys live there today.
- `podium-ink.css` already defines a `.hero-eyebrow` / `.hero-title` pattern
  (uppercase, letter-spaced label above a heading) that
  `app/views/public/event.php`'s hero markup doesn't use — the template
  currently renders a plain `<h1>` with no eyebrow. This is a real,
  already-built, currently-unused component.
- `CLAUDE.md`'s design section, quoted exactly: "Premium editorial
  photography aesthetic. White and black only, minimal, generous
  whitespace, full-bleed images. Clean typographic hierarchy, no decorative
  elements. Maximizes focus on the photographs themselves. Not
  swappable—one solid, timeless look."
- Tangential, not addressed by this document: `CLAUDE.md` also states that
  nothing business-specific should be hardcoded in core code, only in
  `config/config.php`. That rule is already violated in a handful of
  bootstrap/install scripts (`host-setup.php`, `install.php`,
  `generate-static.php`, `verify-setup.php`, both schema migration files)
  that hardcode the literal string "PowerMedia Gallery." Noted here only
  because it's evidence of how tightly this particular fork is already
  coupled to PowerMedia's identity — not something this document proposes
  fixing.

## 1. What carries over vs. what's marketing-only

### Carries over — fits a buying tool, not just a marketing site

- **Copy tone and voice.** Short, direct, confident sentences. This is
  pure writing style, costs nothing structurally, and doesn't fight the
  minimal aesthetic in either direction below. Applies to button labels,
  empty states, and error messages across `cart.php`, `checkout_pending.php`,
  `checkout_success.php`, and the event page.
- **The eyebrow-label pattern.** Already built in CSS
  (`.hero-eyebrow`), just not wired into the hero template. Safe to adopt
  regardless of which direction in section 2 gets picked, since it's a
  typographic device, not a color decision.
- **A paired-CTA moment — narrowly, on the event hero only.** The main
  site's primary+secondary CTA pattern ("Enquire Now" / "View Gallery")
  could translate to something like a primary "View Gallery" (scroll to the
  grid) paired with a secondary link back to all events. This should
  **not** spread to the cart or checkout screens: `CLAUDE.md`'s product
  rules already mandate that cart/favourites be "a single tap, no separate
  confirm-to-add step," and adding a second CTA there would contradict an
  existing product rule, not just a style preference.
- **One quiet trust line near checkout — only if real.** A single line
  (e.g. a delivery-time claim) at the moment someone is about to pay could
  reinforce confidence without importing the full marketing treatment. This
  is gated on the maintainer supplying an actual claim true of *this
  gallery's* delivery — not copying the main site's number verbatim if it
  describes a different service.

### Marketing-only — does not belong in a buying tool

- **Testimonials.** These persuade someone who hasn't decided to book yet.
  A gallery visitor has typically already paid for or is a subject of the
  event being photographed — they're not being sold on whether to hire the
  photographer.
- **Service cards** (packages, features, pricing pitches). Irrelevant once
  someone is browsing photos of an event that's already happened.
- **The "recent work" mixed-media portfolio section.** That's an
  acquisition section aimed at prospective clients evaluating the
  photographer's range — not something a repeat, functional interface
  needs to repeat.
- **A repeated, site-wide stat-badge row** (e.g. "100% Recommend,"
  "~48 Hours"). Appropriate as an occasional trust signal (see above) but
  too promotional as a persistent structural element across a transactional
  tool.

## 2. The white/black-minimal vs. brand-bold conflict

This is stated plainly, not resolved silently. `CLAUDE.md` frames the
current look as a deliberate hard constraint the maintainer set — so this
section lays out both options and their tradeoffs, with a recommendation,
but the actual choice belongs to the maintainer.

### Option A (recommended): keep the gallery flat white/black minimal

Adopt only the light-touch carryovers from section 1 — copy tone, the
eyebrow label, one paired-CTA on the hero, maybe one trust line. No palette
change, no typography swap beyond what's already declared.

- **Tradeoff:** less visual brand consistency with the main site. Someone
  moving from powerrmediaa.com to their event gallery will notice the shift
  in tone.
- **Upside:** preserves what `CLAUDE.md` explicitly asked for, and keeps
  the tool optimized for its actual job. A gallery's job is fast,
  frictionless browsing and buying — a different job from a marketing
  site's job of persuading someone to book a shoot. Minimal chrome, full-
  bleed photos, and no competing visual noise serve that job directly.

### Option B: bring the gallery closer to the main site's darker, bolder identity

- **Tradeoff:** requires real brand hex values (not yet supplied — see
  section 3), a WCAG contrast re-check across nearly every component in
  `podium-ink.css` (almost all of it references `var(--bg)`/`var(--text)`),
  an inverted `.hero`/`.hero-overlay` gradient (currently light-to-
  transparent for a white background; a dark theme needs it flipped darker-
  to-transparent with white text), and a broader CTA-pairing rollout to
  match the main site's "everywhere" pattern (more invasive than Option
  A's single hero placement).
- Critically, it also requires the maintainer to edit `CLAUDE.md`'s own
  "Not swappable — one solid, timeless look" line. Shipping Option B while
  that line stands leaves the project's own governing document contradicting
  its build — this isn't optional cleanup, it's a precondition.
- **Upside:** stronger, more immediate brand consistency between the two
  properties.

### Recommendation

**Option A.** The two properties do different jobs — persuade vs. transact
— and CLAUDE.md's "not swappable" language reads as a considered decision,
not an oversight. Light-touch borrowing (tone, eyebrow, one CTA pairing, an
optional real trust line) gets meaningful brand connection without
compromising the thing that makes a photo-buying flow work: speed and lack
of friction. This is a recommendation, not a decision — see section 2's
opening note.

## 3. What's missing — not guessed

None of the following are invented or presented as real anywhere above.
They need to come from the maintainer before implementation of either
option can start:

- **Real brand hex values** for the main site's background, text, and
  accent colors. The live site couldn't be fetched this pass (see
  "Research method & limitations"), and even with access, extracting exact
  hex values from a rendered page rather than its source stylesheet is
  unreliable. Needed: real values, or existing brand-asset files, direct
  from the maintainer.
- **An actual logo file.** None exists anywhere in this repository today.
- **The actual logo/wordmark font, and any licensed web-font files.**
  Confirmed absent: `public/assets/fonts/` doesn't exist, and the currently
  declared fonts (Geist Sans, Newsreader) may not even be the real brand
  typeface — they could be a placeholder choice made during the gallery's
  initial build. Needed: confirmation of the real brand font, plus actual
  `.woff2` (or equivalent) files cleared for web embedding.
- **The source and intended reuse of the "star rating" trust line.** Is
  this a real aggregate score (Google, Trustpilot, etc.), and does the
  maintainer want that exact figure reused in the gallery, or is it
  explicitly a marketing-site-only claim that shouldn't travel?

## 4. Component/page change list

Split by option, so this is ready to hand off as an implementation prompt
once a direction is picked.

### Option A (recommended) — light-touch changes

- `app/views/public/event.php` — wire the existing `.hero-eyebrow` /
  `.hero-title` CSS classes into the hero markup (currently a plain `<h1>`
  with no eyebrow).
- Copy-tone pass only (no new markup) across `app/views/public/cart.php`,
  `checkout_pending.php`, `checkout_success.php` — button labels, empty
  states, error messages.
- One optional trust line near the checkout button in `cart.php` — blocked
  on the maintainer supplying a real, gallery-specific claim (see section
  3).
- A paired-CTA block added only to the event hero (primary "View Gallery"
  + secondary link back to all events) — explicitly not added to
  cart/checkout.
- No change needed to the `customize.php` / `customize.json` mechanism
  itself — it already accepts real hex/font/logo values once supplied.
  Entering a real font name there is moot, though, until actual `.woff2`
  files exist under a newly-created `public/assets/fonts/` and the
  `@font-face` `src` paths in `podium-ink.css` are corrected to match.

### Option B — additional changes on top of Option A's list

- `public/assets/css/podium-ink.css` — full palette swap of `--bg`,
  `--bg-alt`, `--text`, `--text-muted`, `--border` — blocked on real hex
  values.
- `.hero` / `.hero-overlay` — invert the gradient direction and flip
  overlay/heading text color for a dark background.
- A contrast/accessibility re-check across the stylesheet, since nearly
  every rule references the swapped variables.
- A maintainer-authored edit to `CLAUDE.md`'s design section, removing or
  rewriting the "Not swappable — one solid, timeless look" line before this
  ships — otherwise the project's own governing document contradicts its
  build.
- A broader CTA-pairing rollout to the events-list and search-results
  pages, to match the main site's "everywhere" pattern rather than Option
  A's single hero placement.

## Verification

No code changed in this pass, so there's nothing to run. Verification here
is: the maintainer reviews this document, picks Option A or B, confirms
the assets listed in section 3 will be supplied, and a follow-up task
re-scopes implementation against whichever option was chosen.

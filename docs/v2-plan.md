# v2 plan

Planning document only. No code changes here. Written after the Part 1
verification pass (see `PROGRESS.md` and `docs/AUDIT.md`) fixed the money
path and ran a security audit. This document covers what a genuine second
release needs: missing table-stakes features, gaps nobody had discussed
yet, growth features, motorsport-specific work, platform/workflow items,
and what stays explicitly out of scope.

Each item states what it is, why it matters, rough effort, dependencies,
and priority (P0 = blocks a credible v2 release, P1 = should ship in v2,
P2 = worth doing if time allows, P3 = backlog).

---

## 1. Table stakes missing

These aren't differentiators. Every serious competitor has them. Shipping
v2 without them means losing comparisons on the first feature list a
prospective client reads.

### 1.1 Named favourites / proofing
**What:** a client marks photos as favourites during browsing, can see
just their favourites, and (optionally) shares that shortlist back to the
photographer. Pixieset, ShootProof, Pic-Time, CloudSpot, and Picflow all
treat this as baseline, not a premium tier. Picflow's whole pitch is
"collect feedback through favorites, comments and annotations" as the
default gallery experience, and CloudSpot's client-gallery page leads with
the same capability. Building v1 without it is the single most visible
gap against any competitor screenshot.
**Why it matters:** it's the mechanism parents/drivers use to say "these
are the ones I want" before buying, and it's usually the point of first
return-visit re-engagement (a client leaves, comes back to check their
favourites, converts).
**Effort:** M. New table (`favourites`, session-cookie-scoped, no account
needed, matching the no-customer-accounts product rule), a client-side
add/remove UI matching the existing cart's single-tap pattern, a
favourites-only filtered view.
**Dependencies:** none blocking. Can reuse the cart's HMAC-cookie pattern
for anonymous persistence instead of building a second mechanism.
**Priority:** P0.

### 1.2 Per-photo comments
**What:** a lightweight comment/note on an individual photo, visible to
the photographer (and optionally the client who left it). Not full
proofing-with-annotations (Picflow's annotation layer is a heavier
feature); a single free-text field per photo is enough to match table
stakes without the complexity.
**Why it matters:** clients currently have no way to ask "can you crop
this one" or "is there a shot of car #44 in this corner" without email.
**Effort:** S. One table, one form, one admin-side list view.
**Dependencies:** none.
**Priority:** P1.

### 1.3 Guest access without inherited rights
**What:** the ability to share a single photo or a small subset via a
link that does not grant the recipient rights to the whole gallery,
download originals, or see prices. A "look at this one" share distinct
from "buy this gallery." Table stakes for guest-access flows across
Pixieset/ShootProof/Pic-Time (all support password/email-gated
client-branded landing pages with scoped access per [Tov Studio's 2026
roundup](https://tovstudiophoto.com/best-client-gallery-platforms-compared/)
and [FindMe Photo's 2026 comparison](https://findme.photo/blog/pixieset-vs-shootproof-vs-pic-time)).
**Why it matters:** a driver wants to text one photo to a sponsor without
handing them a link to buy the whole session at cost price.
**Effort:** S to M. A scoped share token (reuse the signed-token pattern
already used for download links) that resolves to a fixed photo subset
and a stripped-down view with no cart, no price, no gallery navigation.
**Dependencies:** 1.1's favourites mechanism is a natural source list to
share from, but not required.
**Priority:** P1.

### 1.4 Real search
**What:** actual server-side search: driver name, car number, class,
free text against filename/description, with pagination, not the
current client-side substring filter capped at whatever `LIMIT 500`
already loaded into the DOM (confirmed broken as a differentiator in
Part 1's audit; see `PROGRESS.md`'s verification table: "client-side
substring filter over already-rendered DOM only, capped at whatever
LIMIT 500 loaded").
**Why it matters:** it was already flagged as absent in v1's own gap
report before this document existed. Confirmed, not rediscovered.
**Effort:** M. `app/lib/search.php` exists and has correct SQL patterns
elsewhere in the app to copy; the gap is a dedicated indexed search
endpoint with pagination, not a new subsystem.
**Dependencies:** none.
**Priority:** P0.

---

## 2. Never-discussed gaps

Real risk areas nobody raised while scoping v1. Silent failure modes are
the theme. Each of these degrades gracefully into "nothing happens" or
"looks fine until it doesn't," exactly the failure pattern Part 1 found
repeatedly in the actual code: missing email functions, broken migrations,
wrong storage paths, all silent until someone tried to use them for real.

### 2.1 Email deliverability (SPF/DKIM/DMARC, bounce handling)
**What:** authenticated outbound mail, not just a working SMTP/`mail()`
call. Part 1 fixed the fact that receipt/refund emails were never sent at
all (`app/lib/mailer.php` didn't exist); this item is the next layer:
making sure the emails that now send actually land in an inbox.
**Why it matters:** as of 2026, [Google and Yahoo enforce authentication
for bulk senders, Microsoft followed in early 2025, and unauthenticated
mail is rejected at the SMTP level, not just spam-filtered](https://www.egenconsulting.com/blog/email-deliverability-2026.html).
PHP's `mail()`, the app's documented fallback when SMTP isn't configured,
[provides no authentication at all; the message leaves the server with
no cryptographic proof it came from the sending domain](https://www.acellemail.com/guide/email-deliverability).
A self-hoster who skips SMTP setup (the easy path, since `mail()` is the
zero-config default) will have every receipt silently land in spam or get
rejected outright, and never know it. This is a CRITICAL-adjacent risk
sitting one config choice away from "works in testing, fails in
production," exactly the class of bug this whole audit pass exists to
catch before it happens again.
**Why this is genuinely hard on shared hosting:** the sending domain
and the DNS the self-hoster controls are usually the same registrar
account, so SPF/DKIM records are within reach, but DMARC alignment
(the visible `From:` domain must match the SPF or DKIM domain) is a
common self-hoster failure mode, and shared-hosting mail queues often
share IP reputation with other tenants on the same box.
**Fix shape:** INSTALL.md gets a mandatory (not optional) email
deliverability section: SPF/DKIM record templates, a DMARC record at
`p=none` initially with a documented upgrade path to `p=quarantine`, and
a bounce-handling note (shared hosting rarely gives you a bounce webhook,
so the realistic v2 scope is "detect a hard SMTP rejection at send time
and flag the order for manual follow-up," not full bounce processing).
**Effort:** M (mostly documentation plus one bounce-detection code path in
`app/lib/mailer.php`).
**Dependencies:** builds directly on Part 1's `app/lib/mailer.php`.
**Priority:** P0. This is not optional polish, it's finishing the
receipt-delivery feature Part 1 started.

### 2.2 Backups / disaster recovery on shared hosting
**What:** a documented, cron-triggerable backup of the database and
`storage/` (originals). Shared hosting (the target host per CLAUDE.md)
usually has no snapshot/restore tooling beyond what the host's control
panel offers, and control-panel backups are typically weekly at best and
outside the app's control.
**Why it matters:** original photo files are the actual business asset.
Losing them (bad `rm`, host incident, failed migration) with no
independent backup is an existential risk for a self-hosted business,
and nothing in v1 addresses it.
**Fix shape:** a cron job (fits the existing 5-minute-cron architecture
directly, no daemon needed) that does a `mysqldump` plus an incremental
`storage/` archive to a second location the self-hoster configures
(an S3-compatible bucket, or SFTP to another host, reusing the existing
`phpseclib`-gated SFTP pattern from remote-admin mode). Document a manual
restore procedure and test it during INSTALL.md's "tested end to end"
requirement (CLAUDE.md's own release-prep bar).
**Effort:** M.
**Dependencies:** the existing `app/lib/sftp.php` pattern for remote
destinations; the existing cron job-queue architecture for scheduling.
**Priority:** P0.

### 2.3 Accessibility (WCAG, tap targets, keyboard nav, alt text, lightbox screen-reader behavior)
**What:** the real audience for this app is parents standing trackside on
a phone, often in bright sun, sometimes older, sometimes not tech-fluent.
Not a demographic that tolerates a fussy UI, and a demographic that
statistically includes more accessibility needs than a typical SaaS
dashboard's user base.
**Concretely missing today:** [keyboard accessibility in the lightbox
(Tab to a thumbnail, Enter to open, arrow-key navigate between images,
Escape to close, all without a mouse)](https://www.wcag.com/blog/content-over-images-how-does-this-ux-ui-trend-impact-accessibility/);
screen-reader announcements when the lightbox image changes; alt text
sourced from something real (driver name plus car number plus session,
not the raw filename); tap target sizing on the filter dropdowns and cart
button for outdoor one-handed phone use.
**Why it matters:** beyond the ethical/legal case, this audience
literally cannot use a gallery that only responds to precise mouse clicks
or has 32px tap targets, and that's lost revenue, not just a compliance
checkbox.
**Effort:** M. Mostly CSS/markup/ARIA work on the existing lightbox and
filter bar, not new features.
**Dependencies:** none blocking; touches the same files as 1.4's search
UI, worth doing in the same pass.
**Priority:** P1.

### 2.4 Refunds/chargebacks vs. delivered download links
**What:** the app currently has a refund email path (Part 1 built it) but
no policy or mechanism for what happens to a download link after a
refund. If a customer downloads the clean files, then charges back, they
keep both the money and the files with no revocation.
**Why it matters:** this is a real fraud vector for any digital-delivery
business, and it's currently undefined, not just unhandled.
**Fix shape:** on `charge.refunded`, revoke the associated download link
(the revocation mechanism already exists in `download_links` per Part
1's audit, so this is wiring, not new infrastructure) and log whether the
link had already been used before the refund, so the maintainer has the
evidence needed to dispute a bad-faith chargeback with Stripe.
**Effort:** S.
**Dependencies:** none; reuses existing revocation column.
**Priority:** P1.

### 2.5 UK sole-trader VAT / invoicing on VAT-inclusive gross prices
**What:** the app prices and charges in VAT-inclusive gross terms (per
CLAUDE.md's currency-config model) but generates no VAT-compliant invoice.
[HMRC requires a VAT invoice showing the sole trader's name, any trading
name and an address for legal service, the VAT-inclusive total and rate
charged for simplified invoices under £250, or a full net/VAT/gross
breakdown above that](https://avask.com/blog/vat-invoice-requirements-uk-everything-that-must-be-included-2026/).
Below the £90,000 VAT registration threshold this doesn't apply yet, but
the maintainer's own business (PowerMedia, referenced in CLAUDE.md) may
cross that threshold, and any self-hoster running this as an open-source
project should not discover the gap after they do.
**Why it matters:** it's a legal requirement the moment a self-hoster
registers for VAT, and receipts today don't carry a VAT breakdown at all.
**Fix shape:** a config flag (`vat.registered`, `vat.number`) that, when
set, adds the VAT breakdown line to the existing receipt template. Off by
default, since most self-hosters starting out won't be VAT-registered,
and CLAUDE.md's rule against hardcoding business specifics into core code
means this must be config-driven, not assumed.
**Effort:** S.
**Dependencies:** the existing receipt-email template built in Part 1.
**Priority:** P1 for the config flag existing at all; P2 for anything
beyond the basic breakdown (for example Making Tax Digital digital-
record-keeping integration, which [applies to sole traders with income
over £50,000 from April 2026](https://www.rsbc.uk/blogs/uk-vat-guide-2026-everything-you-need-to-know-vatcalccom)
but is an accounting-software integration, not a gallery-app feature).

---

## 3. Growth / conversion features

### 3.1 Pre-generated per-gallery QR codes for trackside handouts
**What:** a QR code generated automatically per event/session,
downloadable from the admin panel as a print-ready image, pointing at the
gallery's shareable filter URL (already confirmed working in Part 1's
audit).
**Why it matters:** [SmugMug's own marketing states the exact case this
app is built for: "speed is everything when it comes to monetizing
photography, the quicker event clients visit your gallery, the greater
their interest in the photos and the more money you make," and profiles
a photographer who prints SmugMug-generated QR codes for parents to scan
directly to the gallery](https://www.smugmug.com/development-lab/posts/elevate-your-event-photography-business-with-qr-codes).
This is proven, not speculative. It's the single highest-leverage,
lowest-effort growth feature available, and it composes directly with
work already done: point the QR at a pre-filtered URL (by class, by kart
number, by session) so a driver scans straight to their own photos, not
the whole event.
**Effort:** S. A QR-generation library call (pure PHP, several
zero-dependency options exist, keeping the no-Node/no-build constraint)
plus a "download QR code" button in the existing event/session admin
view.
**Dependencies:** the shareable filter URLs already built.
**Priority:** P0. Smallest effort-to-impact ratio of anything in this
document.

### 3.2 Referral / WhatsApp share incentive
**What:** a small incentive (discount code, or nothing more than a
pre-filled WhatsApp share message) when a customer shares their gallery
or purchased photos.
**Why it matters:** trackside social groups (team WhatsApp groups, family
group chats) are the natural distribution channel for this exact
audience, cheaper than any paid acquisition channel.
**Effort:** S to M depending on whether a discount-code mechanism exists
yet (it doesn't, and would need one).
**Dependencies:** a discount/promo-code system, which doesn't exist and
would need to be scoped as its own item if this is prioritized.
**Priority:** P2.

### 3.3 Cron-driven abandoned-cart nudge
**What:** an email reminder when a cart has items but checkout wasn't
completed within some window.
**Why it matters:** standard ecommerce recovery mechanism. Every
mainstream cart-recovery guide assumes a cron daemon
([OpenCart, Magento 2, and PrestaShop all rely on system cron for this](https://prestahero.com/blog/post/90-set-up-cronjob-for-abandoned-cart-reminder-auto-email-module.html)),
which is exactly what this app already has (5-minute cron, per CLAUDE.md's
hard constraint). Not a gap in capability, just an unbuilt job type.
**Fix shape:** the cart is a signed cookie with no server-side row today
(by design, per Part 1's audit: "cart cookie: HMAC-signed, IDs only").
Abandoned-cart tracking needs a minimal server-side record (email
captured, cart contents snapshotted) created only once checkout is
initiated but before payment completes, reusing the existing `orders`
table's pending-status row rather than a new cart-tracking table. A new
`cron.php` job type (`abandoned_cart_nudge`) checks for orders stuck in
`pending` past a threshold and queues a reminder email through the
existing (now-working) mailer.
**Effort:** M.
**Dependencies:** Part 1's mailer.php; existing cron job-queue
architecture.
**Priority:** P1.

### 3.4 Conversion funnel instrumentation
**What:** track views, filter use, search use, favourites, checkout
starts, and checkout completions, aggregated per event, visible in the
existing admin analytics dashboard.
**Why it matters:** right now there's no way to know whether a gallery
underperformed because of price, because of poor photo selection, or
because nobody found the right filter. The funnel data is the difference
between a guess and a decision.
**Effort:** M. `photo_view` events already exist (`view_count` per Part
1's verification table); needs new event types for filter/search/
favourite/checkout-start, and an aggregation view in
`app/controllers/admin/analytics.php`.
**Dependencies:** 1.1 (favourites) and 1.4 (real search) need to exist
before their funnel stages mean anything.
**Priority:** P2.

---

## 4. Motorsport-specific

### 4.1 Time-based EXIF-vs-session-schedule filtering
**What:** filter photos by matching EXIF capture timestamp against a
published session/race schedule, so a driver can filter to "my session"
by time window without any manual per-photo tagging.
**Why it matters:** legally clean (no biometric processing, see section
6), technically low-risk (EXIF `taken_at` already exists per the schema),
and directly useful. A driver knows what time their session ran even when
they don't know which admin-assigned session ID it maps to.
**Effort:** M. Needs the session-schedule data model (start/end time per
session, which doesn't exist yet) plus a filter query against
`photos.taken_at`.
**Dependencies:** the schema gap noted in Part 1's audit: no
championship/track/country/date columns exist yet; session start/end
times are a related but smaller add.
**Priority:** P1. High value, no legal risk, moderate effort.

### 4.2 Kart number OCR
**What:** automatic race-number detection and tagging from the photo
itself, the same class of feature [RaceTagger already ships as a
commercial desktop tool: proprietary computer-vision models trained on
motorsport imagery, claiming 99.2% accuracy even through motion blur and
partial occlusion, importing a CSV start list and writing matched
metadata directly into EXIF](https://racetagger.cloud/blog/NEW-5-AI-Race-Photo-Tagging-How-It-Works).
**Why it matters:** manual per-photo driver tagging is the single biggest
labor cost in running this kind of gallery at volume. This is the
feature that changes the economics of doing it, if it works.
**What this plan actually commits to:** an integration contract only: a
defined write-back path (`photos.detected_number`, confidence score,
matched-entry link into whatever entry-list table 4.1's schedule work
creates) that either this app's own OCR pipeline or a RaceTagger-style
external tool could write into. **No accuracy claim, no build commitment,
until an actual accuracy test has been run against this app's own sample
photos.** Varying light, motion blur, and kart-vs-car number placement
differ enough between disciplines that RaceTagger's karting-tuned model
accuracy doesn't automatically transfer. Building the full OCR pipeline
before that test would be committing effort against an unverified
premise, exactly the mistake this whole audit pass exists to catch.
**Effort:** S for the integration contract; effort for the actual OCR
work is explicitly unscoped pending the accuracy test.
**Dependencies:** 4.1's entry-list/schedule data model, if OCR results
need to resolve to a named driver rather than just a bib number.
**Priority:** P1 for the integration contract. **The OCR build itself is
not prioritized until the accuracy test runs**, tracked as a
decision-needed item (section 7).

### 4.3 Per-driver season view
**What:** a season-long bundle/view aggregating one driver's photos
across multiple events in a championship, as an upsell beyond
single-event sales.
**Why it matters:** repeat customers (a driver's family buying every
round of a season) are the highest-LTV segment this business has, and
nothing currently aggregates across events for them.
**Effort:** M to L. Genuinely depends on 4.1's schema gap being closed
first (championship/track/date columns don't exist); not buildable
before that prerequisite lands.
**Dependencies:** the championship/track/country schema gap noted
throughout Part 1's audit.
**Priority:** P2. Real value, but correctly sequenced behind its
prerequisite, not this release's headline feature.

---

## 5. Platform / workflow

### 5.1 Lightroom Lua export plugin
**What:** a plugin using Lightroom's Lua SDK to push selected/exported
photos directly to this app's upload endpoint from inside Lightroom.
**Constraint, confirmed via research, not assumed:** [Lightroom's SDK
exposes keywords read-only to a plugin; a Lua plugin can read existing
keywords but cannot write or modify them](https://www.lightroomqueen.com/community/threads/programming-extensions-to-lightroom.6158/).
This means any keyword-based metadata (driver tags, kart numbers assigned
in Lightroom) cannot be round-tripped back into Lightroom by a plugin.
It's necessarily one-way export-out. That's a hard SDK limitation, not a
design compromise this project is choosing.
**Why it matters:** photographers' actual workflow is Lightroom-first;
making them leave Lightroom to upload is real friction against adoption
by working photographers, one of the two audiences (alongside
self-hosters) this project needs to win over for AGPL open-source
traction.
**Explicit blocker:** the plugin's export step needs a stable upload API
contract to target. Part 1's chunked-upload CSRF fixes changed the
upload endpoint's request shape (added the reusable CSRF token). Building
the plugin against a still-settling endpoint means rebuilding it on the
next endpoint change.
**Effort:** L.
**Dependencies:** blocked on upload-endpoint stability; do not start
before the endpoint has shipped and been stable through at least one full
release cycle.
**Priority:** P2, explicitly sequenced behind stability, not because it's
low-value.

### 5.2 Print fulfilment via a UK lab (Loxley Colour)
**What:** integrate a print-on-demand lab so photo purchases can include
prints/canvas/metal prints, not just digital delivery.
**Why it matters:** [Loxley Colour already has live API integrations with
ShootProof (and, per their partner list, Pixieset and Lightfolio) covering
prints, fine-art prints, canvases, and metal prints, with photographer
review/approval before an order reaches the lab, across most of the UK
and much of Europe](https://help.shootproof.com/hc/en-us/articles/115010074027-About-the-Loxley-Colour-ShootProof-Partnership).
This is a proven integration pattern, not a novel build, and directly
extends the existing `app/lib/fulfillment.php`/NAS-fulfillment
infrastructure that already exists (currently only for local print
fulfilment, per Part 1's audit) toward an external lab instead of (or
alongside) local printing.
**Effort:** L. New API integration, price-sheet mapping, order-approval
UI.
**Dependencies:** the existing (currently NAS-only, per Part 1's flagged
schema gap) fulfilment infrastructure.
**Priority:** P2.

### 5.3 AVIF/WebP + ThumbHash + Core Web Vitals targets
**What:** modern image formats for derivatives (currently JPEG-only per
Part 1's verification), a low-cost blurred placeholder (ThumbHash or
similar) while full images load, and explicit performance targets: LCP
under 2.5s, INP under 200ms, CLS under 0.1.
**Why it matters:** this audience is on phones, often on patchy trackside
mobile data. This is exactly the use case Core Web Vitals thresholds
exist for, and a slow gallery loses sales at the exact moment of highest
purchase intent (just after an event, checking on a phone).
**Effort:** M. Derivative generation already exists (`app/lib/
derivatives.php`); this extends it with format negotiation
(`Accept: image/avif`) rather than replacing it.
**Dependencies:** none blocking.
**Priority:** P1.

### 5.4 Multi-admin and theme presets
**What:** multi-admin already has a schema (`migrations/002_add_multi_admin.sql`,
confirmed present in Part 1's audit) but no meaningfully different
permission model; every admin has full access. Theme presets would mean
swappable visual themes beyond the current single deliberate aesthetic.
**Honest assessment:** CLAUDE.md is explicit that the visual design is
"not swappable, one solid, timeless look," and the product is currently
built and proven on a single operator's business (PowerMedia). Multi-admin
with real role separation matters only if this becomes a multi-photographer
studio tool, which is not the stated direction. Theme presets directly
contradict the stated design philosophy.
**Recommendation: do not build either for v2.** Multi-admin's existing
schema is enough for "assistant can log in and help," which is probably
the actual use case, without inventing role-based permissions nobody has
asked for. Theme presets are out of step with the product's design
identity, not a gap.
**Priority:** P3 (multi-admin role separation, only if studio/multi-
photographer use becomes an actual goal). Not planned (theme presets).

---

## 6. Explicitly out of scope

- **Face/biometric search.** UK GDPR treats facial recognition as special
  category biometric data. This app's own users include junior drivers
  (minors), which makes this materially riskier than an adult-only
  product, not just generically risky. Not building it, not planning it.
- **Anything requiring Node, Docker, or a daemon on the host.** Hard
  constraint per CLAUDE.md; not revisited by this plan.
- **Replacing phpseclib or the hand-rolled Stripe wrapper.** Both are
  deliberate, audited choices (Part 1's security audit found the Stripe
  wrapper's crypto sound; phpseclib is correctly gated behind
  `class_exists()` and optional). No motivation to change either.
- **Customer accounts / removing guest checkout.** CLAUDE.md is explicit:
  "No customer accounts. Guest checkout via Stripe Checkout." Every item
  in this document (favourites, comments, guest-scoped shares) is
  designed to work without an account, on purpose.

---

## 7. Recommended build order

1. **P0 batch first** (1.1 favourites, 1.4 real search, 2.1 email
   deliverability, 2.2 backups, 3.1 QR codes). These are either
   competitive table stakes, finishing work Part 1 started, or the
   highest-leverage-for-effort item available. None blocks on anything
   else in this list.
2. **P1 batch second** (1.2 comments, 1.3 scoped guest shares, 2.3
   accessibility, 2.4 refund/chargeback revocation, 2.5 VAT flag, 3.3
   abandoned-cart nudge, 4.1 time-based filtering, 4.2 OCR integration
   contract, 5.3 AVIF/ThumbHash/CWV). All buildable independently; do
   4.1 before 4.3 becomes relevant later.
3. **Run the kart-OCR accuracy test** (blocks 4.2's actual build, not its
   contract) and **run a full-budget security-audit pass** (12 categories
   across the top 8 partitions; this session's audit was scope-reduced,
   see `PROGRESS.md`) before committing further engineering time, since
   both results change what's safe to build next.
4. **P2 batch** (3.2 referral share, 3.4 funnel instrumentation, 4.3
   per-driver season view, 5.1 Lightroom plugin once upload-endpoint
   stability is confirmed, 5.2 Loxley Colour integration, 5.4 multi-
   admin role separation only if studio use becomes a real goal).
5. **Open-source release prep** (CLAUDE.md's own stage 6: config-driven
   branding, largely done in Part 1's hardcoded-name cleanup; commenting
   standard; INSTALL.md tested end to end; LICENSE/TRADEMARK.md) can run
   in parallel with the P1 batch once 2.1/2.2 land, since a public
   release without working deliverability or backups would be
   irresponsible to ship as a template for others to self-host.

## Decisions needed (from the maintainer, not this document)

- **Project name.** Referenced throughout as "[PROJECT NAME]" in
  CLAUDE.md; needed before any public-facing release material, README, or
  LICENSE/TRADEMARK.md work.
- **Open-source-or-not, and if so when.** CLAUDE.md's stage 6 assumes
  yes; confirm the timeline, since it affects how much of section 2's
  deliverability/backup documentation needs to be self-hoster-generic
  versus PowerMedia-specific right now.
- **Kart-number OCR accuracy test results.** Section 4.2 is explicitly
  gated on this; no further OCR engineering commitment until it runs.
- **Real brand assets.** Hex colors, logo, font, already flagged
  unresolved in `docs/brand-alignment-plan.md` from earlier work; blocks
  the open-source release prep stage's config-driven branding claim from
  being more than plumbing with placeholder values.

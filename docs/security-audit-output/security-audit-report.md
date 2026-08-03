# Security Audit Report

**Project:** open-source-gallery (hazzaattemptscoding/open-source-gallery)
**Audit ID:** osg-2026-08-03-focused
**Skill version:** 2.5.0
**Generated:** 2026-08-03
**Scope:** focused. The code added in this session: advance credit, the SSRF-guarded
remote fetcher, consent and unsubscribe, the entrant discovery and review
endpoints, detection sidecar ingest, and share-image generation.
**Branch:** `claude/planning-context-handoff-vh17vl`

## Executive Summary

**AUDIT GATE: PASSED.** No unresolved governance failures (R4, L1-L3). A baseline
was written.

- **Total findings:** 8 — CRITICAL 1 · HIGH 2 · MEDIUM 5 · LOW 0 · INFO 0
- **Fixed and test-verified since this run:** 3 (OSG-2026-001, -002, -003) in
  commit `fbd97e9`. **Open: 5, all MEDIUM.**
- **Confidence mix:** CONFIRMED 8 · LIKELY 0 · POSSIBLE 0
- **Partitions audited:** 8 at full depth, 0 inventory-only
- **Attack surface:** 14 entry points, 6 sensitive egress sinks, 6 credential
  mint/consume pairs, 6 collection queries
- **Unique-to-skill findings:** 8 of 8. No external scanner ran (see Degraded
  mode below), so every finding here is manual review.
- **CWE Top 25 (2025) hits:** 2 — highest-ranked #18 (CWE-20), plus #22 (CWE-918)
- **Exploit-likely (EPSS / CISA-KEV):** not computed, grype not installed
- **Severity escalated by attack-path arithmetic (§7.15):** 0
- **Unscoped collections (§6.20):** 0 upheld, 13 raw flags all retired with evidence
- **Oldest unremediated CONFIRMED HIGH+:** 0 days, for findings raised by this
  run. The prior run's baseline predates the v2.5 lifecycle fields, so L1 had
  nothing to age against. Do not read the zero as a clean triage record.
- **Carried forward from the 2026-07-30 run:** 8 findings (3 fixed, 5 still open).
  This focused run did not re-audit their files. See the carry-forward section.

### Read the severity numbers with this in hand

The 1 CRITICAL / 2 HIGH / 5 MEDIUM above is the **post-calibration** count.
The deep-dive rubric asserted **1 HIGH / 2 MEDIUM / 5 LOW**. Every finding moved
up exactly one rung, because §7.4's context arithmetic gives +1 for
`confidence == CONFIRMED` and all eight are confirmed, and the cap is ±1 rung.

Both numbers are in the SARIF (`severity_rubric_initial` and
`severity_computed`) so nothing is hidden. The material facts a reader needs:

- **No path from an unprivileged persona reaches a crown jewel.** Both the
  `anonymous` and `external_link_holder` personas reach five findings, all of
  which are the retired collection flags; chain severity MEDIUM; crown jewels
  reached: none.
- **Every one of the eight requires either an operator action or an
  environmental precondition an attacker does not control** (a hostile DNS
  server the operator's URL points at, a currency reconfiguration, a
  TLS-terminating proxy that strips `X-Forwarded-Proto`, access to server logs).
  Those preconditions appear as orphans in the gate output, which is correct:
  they are prerequisites, not capabilities any finding grants.
- **Nothing here is remotely exploitable by an anonymous visitor.**

The one worth fixing before release was OSG-2026-001. It, and the two HIGHs, are
now fixed in `fbd97e9`, each with a denial test that fails if the fix is reverted
(L3). What is still open is the five MEDIUMs, listed below.

### Top Risks (all three now fixed, see the Remediation Roadmap)

1. **OSG-2026-001 (CRITICAL, CWE-918)** — FIXED in `fbd97e9`. The SSRF guard in `remote_fetch.php`
   validates a resolved address and then lets curl resolve the name again. A DNS
   answer that changes between the two lookups defeats every guard in the file.
   This is the classic rebinding bypass and the file's own docblock claims to be
   proof against exactly this class.
2. **OSG-2026-002 (HIGH, CWE-20)** — FIXED in `fbd97e9`. A remote entry list truncated at the 2 MB cap
   is returned as `ok: true` with a partial body. Drivers past the cut-off
   silently do not exist in a product whose whole value is find-me, and nothing
   tells the photographer.
3. **OSG-2026-003 (HIGH, CWE-841)** — FIXED in `fbd97e9`. `credits.currency` was written at purchase
   and never read again. Credit sold in one currency spends one-for-one in
   another after a config change.

## Partition Risk Ranking

| Partition | Area | Risk | Findings | Notes |
|---|---|---|---|---|
| P1 | advance-credit | CRITICAL | 3 | The money module. Atomic conditional UPDATE verified; currency is not |
| P2 | checkout | CRITICAL | 1 | Stripe Checkout hosted, no card data touches this app |
| P3 | remote-fetch | HIGH | 2 | Both HIGH+ findings live here |
| P4 | webhook | HIGH | 0 | HMAC verified before any action; activation idempotent on `status='pending'` |
| P5 | entrant-discovery | HIGH | 0 | Bearer-capability scoping, verified by reading each call site |
| P6 | consent | MEDIUM | 0 | `can_send_marketing()` fails closed; soft opt-in cannot resurrect an unsubscriber |
| P7 | detections | MEDIUM | 1 | Never downgrades a human decision; refuses to guess between classes |
| P8 | share-image | MEDIUM | 1 | Public by design; carries number and class, never a name |

## Findings

### CRITICAL

#### OSG-2026-001 (FIXED in `fbd97e9`) — SSRF guard defeated by DNS rebinding: validated address is not pinned

- **Confidence:** CONFIRMED · **Partition:** P3 · **Category:** injection
- **Location:** `app/lib/remote_fetch.php:113`
- **CWE:** CWE-918 (Top 25 2025 rank #22) · **ASVS:** V12.6.1
- **Severity:** asserted HIGH, calibrated CRITICAL (§7.4, +1 for CONFIRMED)
- **Capabilities:** requires `role:admin` + `env:attacker_controlled_dns`;
  grants `reads:any_internal_http_content`

`remote_fetch_validate_url()` resolves the host with `gethostbynamel()` at line
113 and checks every returned address, which is the right check. `remote_fetch()`
then calls `curl_init($url)` at line 164, and curl resolves the same name again,
independently. Nothing carries the validated address into the connection.

**Failure scenario.** An admin pastes an entry-list URL on a host the attacker
controls. Its DNS answers with a public address (TTL 0) for the validation
lookup, then with `127.0.0.1` or `169.254.169.254` for curl's lookup moments
later. Every guard in the file passes and the request is made to the internal
target anyway, returning its body into the CSV importer where the admin reads it.

**Fix.** Pin the address that was validated: set `CURLOPT_RESOLVE` to
`host:port:validated_ip` (or `CURLOPT_CONNECT_TO`) so curl connects to the
checked address rather than re-resolving. Re-pin on every redirect hop, since
each hop revalidates and each hop is separately rebindable.

### HIGH

#### OSG-2026-002 (FIXED in `fbd97e9`) — Truncated remote response is imported as a complete file

- **Confidence:** CONFIRMED · **Partition:** P3 · **Category:** injection
- **Location:** `app/lib/remote_fetch.php:176`
- **CWE:** CWE-20 (Top 25 2025 rank #18) · **ASVS:** V5.1.3
- **Severity:** asserted MEDIUM, calibrated HIGH (§7.4, +1)
- **Capabilities:** requires `role:admin`; grants `bypasses:import_completeness`

The `CURLOPT_WRITEFUNCTION` callback returns 0 to abort once
`REMOTE_FETCH_MAX_BYTES` is passed, without echoing the chunk that crossed the
line. `curl_exec()` then returns false, but `$body` holds everything received so
far. The only failure guard is `($ok === false && $body === '')`, so a non-empty
partial body falls through. `CURLINFO_RESPONSE_CODE` is 200 because the headers
arrived. `strlen($body) > MAX` is false because the over-limit chunk was never
appended. The function returns `ok: true` with a truncated body.

**Failure scenario.** An entry list over 2 MB is imported as if complete. Drivers
past the cut-off cannot be found by number, and detections naming them are
reported as unknown entrants. The photographer is told the import succeeded.

**Fix.** Have the callback record that it aborted (a flag by reference, or a
small class holding the buffer) and fail explicitly when it did. Do not infer
completeness from body length: the length is exactly the thing the truncation
made wrong.

#### OSG-2026-003 (FIXED in `fbd97e9`) — Credit currency is recorded at purchase but never checked at redemption

- **Confidence:** CONFIRMED · **Partition:** P1 · **Category:** auth
- **Location:** `app/lib/credit.php:150`
- **CWE:** CWE-841 · **ASVS:** V5.1.4
- **Severity:** asserted MEDIUM, calibrated HIGH (§7.4, +3 net: public zone,
  CONFIRMED, write op; capped at +1 rung)
- **Capabilities:** requires `env:currency_reconfigured`; grants
  `writes:any_credit_balance`

`credits.currency` is written by `create_pending_credit()` at line 78 and never
read again. `find_spendable_credit()` selects on code, status and balance only.
`spend_credit()` is not passed a currency at all. The checkout path applies the
credit against `$priced['total_pence']` without comparing the two.

**Failure scenario.** An operator changes `currency.code` in config after selling
credit, or an install's currency changes. Credit sold as 2000 GBP pence is spent
as 2000 units of the new currency, one for one. The customer gains or loses real
value depending on direction, and the books do not balance.

**Fix.** Pass the order currency into `find_spendable_credit()` and
`spend_credit()`, and refuse a credit whose currency differs. Refusing is the
correct behaviour: converting would require an exchange rate this application has
no business holding.

### MEDIUM

#### OSG-2026-004 — Uploaded sidecar is read fully into memory before its size is checked

- **Confidence:** CONFIRMED · **Partition:** P7 · **Category:** injection
- **Location:** `app/controllers/admin/detections.php:88` · **CWE:** CWE-400 · **ASVS:** V12.1.1
- **Severity:** asserted LOW, calibrated MEDIUM
- **Capabilities:** requires `role:admin`; grants `bypasses:upload_size_limit`

`file_get_contents()` reads the uploaded temp file in full; `DETECTION_MAX_BYTES`
is only enforced afterwards by `strlen()` inside `parse_detection_sidecar()`.
PHP's own `upload_max_filesize` bounds this in practice, so it is defence in
depth rather than a live hole. **Fix:** check
`filesize($_FILES['sidecar']['tmp_name'])` before reading.

#### OSG-2026-005 — Credit code travels in the URL path on the success page

- **Confidence:** CONFIRMED · **Partition:** P1 · **Category:** token_scope
- **Location:** `public/index.php:481` · **CWE:** CWE-598 · **ASVS:** V3.5.3
- **Severity:** asserted LOW, calibrated MEDIUM
- **Capabilities:** requires `env:log_or_history_access`; grants `knows:any_credit_code`

`GET /credit/success/{code}` puts a bearer instrument in the request path, so it
lands in browser history, any intermediary access log, and the server's own
access log. Partly mitigated already: `Referrer-Policy` is
`strict-origin-when-cross-origin` and the success page loads no third-party
resources, so the code does not leak via `Referer`. Residual exposure is logs and
shared-device history. **Fix:** show the code after a POST/redirect keyed on a
short-lived session value rather than keying the page on the code itself.

#### OSG-2026-006 — Share card labels a multi-driver photo with an arbitrary entrant

- **Confidence:** CONFIRMED · **Partition:** P8 · **Category:** collection_scope
- **Location:** `app/lib/share_image.php:246` · **CWE:** CWE-1076 · **ASVS:** V5.1.4
- **Severity:** asserted LOW, calibrated MEDIUM
- **Capabilities:** requires `env:multi_entrant_photo`; grants
  `writes:any_photo_public_attribution`

`fetch_share_image_meta()` LEFT JOINs `photo_entrants` and takes `LIMIT 1` with
no `ORDER BY`. A photo attributed to more than one entrant, a battle shot with
two karts in frame, yields whichever row the planner returns. The result is a
public, deliberately shareable card stamped with the wrong number and class.
**Fix:** order deterministically (highest confidence, then lowest entrant id), or
omit the identity line when a photo has more than one confident entrant.

#### OSG-2026-007 — Cookie Secure flag depends on direct HTTPS detection

- **Confidence:** CONFIRMED · **Partition:** P2 · **Category:** crypto
- **Location:** `app/lib/cart.php:74` (and `app/lib/wishlist.php:34`) · **CWE:** CWE-614 · **ASVS:** V3.4.1
- **Severity:** asserted LOW, calibrated MEDIUM
- **Capabilities:** requires `env:proxied_tls_no_forwarded_proto` +
  `env:network_position`; grants `knows:any_cart_token`

Both cookies compute `secure` from `($_SERVER['HTTPS'] !== '') || SERVER_PORT ===
'443'`. Behind a TLS-terminating proxy that sets neither, the flag is omitted.
HSTS is already sent in production mode, which bounds the exposure to the request
before the pin. **Fix:** also honour `X-Forwarded-Proto` when the proxy is
trusted, or make the flag configurable, which suits the self-hosting audience
better than guessing.

#### OSG-2026-008 — `fetch_credit_redemptions()` is unreachable dead code in the money module

- **Confidence:** CONFIRMED · **Partition:** P1 · **Category:** supply_chain
- **Location:** `app/lib/credit.php:453` · **CWE:** CWE-561 · **ASVS:** V1.1.4
- **Severity:** asserted LOW, calibrated MEDIUM
- **Capabilities:** requires `env:future_code_change`; grants
  `bypasses:money_module_review`

Grep across `app/` and `public/` finds no caller. It was written for an admin
support view that was not built. Not exploitable while unreachable; the hazard is
an unreviewed, untested query in a money module sitting ready for a future caller
to wire up without anyone re-examining it. **Fix:** wire it into the admin credit
view it was written for, or delete it. Do not leave it dormant.

## Attack Surface Summary

14 entry points inventoried across the session's new code.

| Route | Gate |
|---|---|
| `POST /credit/buy` | none (public, rate-limited, price checked against the configured ladder) |
| `POST /credit/check` | none (public, rate-limited, uniform failure message) |
| `GET /credit/success/{code}` | bearer code in URL — see OSG-2026-005 |
| `POST /checkout` | none (public, CSRF, cart cookie HMAC) |
| `POST /webhook/stripe` | Stripe HMAC signature, verified before any action |
| `POST /entrant/review` | bearer `share_token`, CSRF (reusable), rate-limited |
| `GET /e/{slug}/d/{token}` | bearer `share_token` |
| `GET /driver/{token}` | bearer `share_token` |
| `POST /unsubscribe` | bearer `unsubscribe_token` |
| `GET /media/share/{token}.jpg` | none by design; watermarked, public product |
| `GET /cart/summary` | cookie cart |
| `POST /admin/detections` | admin session |
| `POST /admin/review` | admin session |
| `POST /admin/events/{id}/entries` | admin session |

Note on route order: `public/index.php` is a switch plus a regex chain, and the
order of that chain is a security boundary. It was read in order; no later
pattern shadows an earlier gate.

## Collection Scoping (row-level access control) — §6.20

**Inventory:** 6 collection queries. `caller_bound` 4, `visibility_filtered` 2.
**Coverage gate:** PASS (fail-closed; no candidate was left neither inventoried
nor dismissed).

`validate-collection-scoping.py` emitted **13 findings. All 13 were retired** by
the §6.20 adversarial pass, each with a check rather than an assertion. The
record is `phase-06-collections-adjudication.json`.

**Why the tool over-flagged.** Rule C5's `predicate_binds_caller()` recognises
session-identity vocabulary: `user_id`, `session`, `owner_id`, `tenant_id`. This
application performs no session-identity scoping on those paths, by design. It
has no customer accounts at all. It scopes by unguessable bearer capability, an
un-modelled mechanism, and the skill documents that case as producing
conservative over-flagging to be retired by this pass. Over-flagging is the right
direction for the tool to fail in.

Two retirements carry the weight and both were re-verified during synthesis:

- **`lib/entrants.php:162, 214, 250`** — `entrant_id` is never taken from request
  input on any route. Every call site derives it from `find_entrant_by_token()`,
  which requires the 64-bit capability. A caller cannot name another entrant.
  Residual: anyone holding a token sees that entrant's photos, which is the
  intended semantic of a shareable personal-page link, recorded in
  `docs/PRIVACY-DESIGN.md`.
- **`lib/campaigns.php:216`** — reachable only from `scan_gallery_live_campaign`,
  itself reachable only from `run_campaign_scans`, called only from
  `run_cron_drain`, on a cron route gated by a secret compared with
  `hash_equals`. The C2 permission-shaped-field flag names `marketing_consent`,
  which **is** filtered: `WHERE marketing_consent = 1 AND unsubscribed_at IS
  NULL` at `campaigns.php:221`, read directly, not inferred.

**Caveat, stated plainly:** a clean reconciliation means every *known* list-query
candidate was accounted for and scoped. It is not proof that no unscoped path
exists.

## Severity Gate (computed severity and lifecycle) — §7.15

- **Findings composed:** 21 (the 8 upheld plus the 13 retired collection rows;
  retired rows were left in the input deliberately, since including a retired
  finding can only over-flag, never hide a chain)
- **Capability-tagged:** 21 of 21
- **Escalations applied (R1/R2/R3):** 0
- **Governance failures (R4, L1-L3):** 0. Gate exit 0.

| Persona | Reachable | Chain severity | Crown jewels reached | Unprivileged |
|---|---|---|---|---|
| anonymous | 5 | MEDIUM | none | yes |
| external_link_holder | 5 | MEDIUM | none | yes |
| operator | 15 | MEDIUM | none | no |
| stripe_webhook | 5 | MEDIUM | none | no |

The five findings reachable by the two unprivileged personas are all retired
collection flags. R3 did not fire because no crown jewel is reached.

### Orphan capabilities, and why this list is not drift

Every orphan **precondition** in this run carries an `env:` prefix:
`env:attacker_controlled_dns`, `env:currency_reconfigured`,
`env:future_code_change`, `env:log_or_history_access`, `env:multi_entrant_photo`,
`env:network_position`, `env:proxied_tls_no_forwarded_proto`.

That prefix is deliberate. The lexicon's grammar is `verb:scope_object`, and it
has no verb for a prerequisite that is a property of the deployment rather than a
capability an attacker holds or a finding grants. Tagging these `env:` keeps the
drift alarm meaningful: **any orphan precondition here that is not `env:`,
`role:` or `external:` is real vocabulary drift and must be chased.** There are
none.

Orphan **postconditions** are of two kinds: terminal outcomes that feed nothing
further (`bypasses:import_completeness`, `knows:any_cart_token`) and the
postconditions of the 13 retired collection rows (`knows:any_photo_id`,
`reads:any_contact_metadata` and siblings). Neither indicates a broken chain.

## Authorized-Egress (cross-layer access control) — §6.19

**Coverage failures: 0.** `=== PASS — every known egress path accounted for and
gated (NOT a proof of absence) ===`

6 sensitive egress sinks inventoried, each with the gate that actually runs on
the byte-serving path:

| Sink | Gate |
|---|---|
| `controllers/public/entrant.php:106` | `share_token` exact match via `find_entrant_by_token()` |
| `controllers/public/entrant.php:140` | `share_token` exact match |
| `controllers/public/share.php:87` | `photo.status='live'` in `fetch_share_image_meta()` |
| `controllers/public/cart.php:142` | HMAC-signed cart cookie |
| `controllers/public/credit.php:206` | knowledge of the 16-hex code, uniform failure message |
| `lib/campaigns.php:196` | `can_send_marketing()`, fails closed |

Credential mint/consume ledger, 6 pairs, every one with both a writer and a
reader: `entrants.share_token` (1 writer / 3 readers), `credits.code` (3/2),
`contacts.unsubscribe_token` (1/2), `csrf_token` (1/2), `pm_cart` cookie (2/1),
favourites `customer_token` cookie (1/1). No credential is minted with no
consumer, and no consumer reads a credential nothing mints.

**Caveats:** this reconciliation catches the control-with-no-enforcer and
capability-URL classes and raises recall across the egress family. It is not a
proof of absence. Detection depends on Phase 2 having recorded each byte-serving
branch and its gate. There is no CDN in this deployment, so the CDN-edge caveat
does not apply here.

## Methodology Coverage

| Methodology | Coverage | Findings tagged |
|---|---|---|
| OWASP ASVS 5.0 L2 | Partial. V3 (session/cookies), V5 (validation), V12 (files/SSRF) exercised directly; V2, V6, V7, V13, V14 not re-audited this run (out of the focused scope) | 8 |
| OWASP Top 10:2025 (web) | 3/10 fired: A01 Broken Access Control, A03 Injection (via SSRF), A05 Security Misconfiguration | 8 |
| API Top 10 (2023) | N/A. No REST API surface in the audited code | 0 |
| LLM Top 10 (2025) | N/A. No model calls anywhere in this application | 0 |
| OWASP Agentic (2026) | N/A. Gate did not fire | 0 |
| LINDDUN | Linkability and Identifiability reviewed for the entrant and share-card surfaces; recorded in `docs/PRIVACY-DESIGN.md` rather than as findings | 0 |
| STRIDE | 8 partitions covered | see per-partition table above |
| CWE | 8 unique CWEs | 8 |
| CWE Top 25 (2025) | 2/25 hit; highest-ranked #18 (CWE-20), also #22 (CWE-918) | 2 |
| Authorized-Egress (§6.19) | 6/6 sinks reconciled; coverage gate PASS | 0 |
| Collection scoping (§6.20) | 6 collections by row_scope; coverage gate PASS; 13 raw flags, 13 retired with evidence | 0 |
| Severity gate (§7.15) | 0 escalations, 0 governance failures, 21/21 capability-tagged | 0 |

## Audit Coverage

| Phase | Status | Note |
|---|---|---|
| 0 Discovery | complete | `capability_lexicon` was missing on the first pass and was derived during Phase 7 (§0.14 should have written it) |
| 1 Partition | complete | 8 partitions, coverage assertion passed |
| 2 Surface | complete | 14 handlers, 6 sinks, 6 credentials, 6 collections |
| 3 Keystone | complete | |
| 4 Scanners | **DEGRADED** | **None of semgrep, osv-scanner, gitleaks, trufflehog, trivy or hadolint is installed in this environment. A CLEAN result here is NOT evidence of absence.** Recorded in `phase-04-scanners/DEGRADED.json` |
| 5 Deep dives | complete, **with deviation** | Ran inline in the orchestrator rather than fanned out to sub-agents: this session's operating instructions forbid spawning agents unless the user asks. Coverage is unchanged; the independence of review is reduced, which matters because the same reader wrote and reviewed the code |
| 6 Config + reconciliation | complete | Egress PASS, collections adjudicated with evidence |
| 7 Synthesis | complete | |
| 8 Baseline | complete | First baseline, so no delta and no R4 ratchet was possible this run |

Two limits worth stating outright:

- **No dependency or secret scanning ran.** The application vendors no Composer
  runtime dependencies, so the SCA gap is narrow, but "no secrets found" is not a
  claim this run can make.
- **The reviewer wrote the code.** Phase 5's sub-agent fan-out exists partly to
  break that. It did not happen here. Treat the absence of findings in P4, P5 and
  P6 as weaker evidence than the presence of findings in P1 and P3.

## Remediation Roadmap

### Done

`fbd97e9` fixes OSG-2026-001, -002 and -003, with 16 tests in
`tests/integration/SecurityFixesTest.php`. Each is written to fail if its fix is
reverted, which is what L3 asks for and what the previous run's three "fixed"
findings still lack.

The truncation fix was measured rather than assumed: with the cap lowered to 1024
bytes and `CURLOPT_MAXFILESIZE` removed so the write callback is the only bound,
the pre-fix code returned `ok: true` with a **zero-length** body. An entry list
could import as empty and be reported as a success.

Fixing the currency check turned up the reason it was reachable at all: seven
call sites read `$config['currency']['code'] ?? 'GBP'`, which is an offset of a
string, so PHP yields null, `??` swallows it, and the site behaves as GBP no
matter what the operator configured. That is now one accessor,
`config_currency_code()`, with a test that fails if any call site drifts back.

### Still open

**Trivial (under 30 minutes each)**

- OSG-2026-004 — `filesize()` before `file_get_contents()`.
- OSG-2026-006 — add `ORDER BY confidence DESC, entrant_id ASC` to
  `fetch_share_image_meta()`.
- OSG-2026-008 — delete `fetch_credit_redemptions()` or wire it up.

**Small (an hour or two each)**

- OSG-2026-007 — honour `X-Forwarded-Proto` behind a trusted proxy, or make the
  flag configurable.

**Medium**

- OSG-2026-005 — POST/redirect the credit success page off a short-lived session
  key instead of keying it on the code.

## Carried forward from the 2026-07-30 run

This run was focused. It did not re-audit the files the previous full audit
covered, so **none of that run's findings were re-tested here**. They are carried
into the new baseline and into `findings.sarif` as a second run
(`security-audit-skill-carryover`) with their original `first_seen_at`.

Their absence from a focused run is not evidence they were fixed. A SARIF upload
that omitted them would have marked all five as resolved.

| Id | Severity | State | Location |
|---|---|---|---|
| SEC-01 | HIGH | **fixed** in `ada7753` | `app/lib/api.php:89` — `api_get_photos()` did not check `events.is_published` |
| SEC-02 | HIGH | **fixed** in `ada7753` | 9 admin controllers — CSRF verify result discarded |
| SEC-03 | INFO | **fixed** in `ada7753` | `app/lib/api.php:108` — selected a nonexistent column, failing closed |
| SEC-04 | MEDIUM | open | `app/lib/rate_limit.php:16` — TOCTOU race lets concurrent requests exceed the cap |
| SEC-05 | LOW | open | `app/lib/orders.php:110` — duplicate receipt/zip jobs on overlapping webhook delivery |
| SEC-06 | INFO | accepted | `app/lib/auth.php:44` — TOTP secret plaintext at rest; inherent to TOTP |
| SEC-07 | INFO | open | `app/lib/stripe.php:89` — outbound Stripe calls rely on curl TLS defaults |
| SEC-08 | LOW | open | `composer.json` — `composer.lock` gitignored, optional deps unpinned |

Two notes on the lifecycle data, since L1 and L3 will act on it next run:

- The three fixed findings carry a `fix_commit` but **no `verified_by_test`**.
  L3 reports that: "fixed" was never mechanically pinned, so nothing stops the
  class recurring on a sibling endpoint. Adding denial tests for SEC-01 and
  SEC-02 is the cheapest durable win on this list.
- SEC-06 is `accepted` with a rationale but **no owner and no expiry**. It is
  INFO, so L2 does not fire, but the same shape at HIGH would fail the run.

## Unique-to-Skill Findings

All 8 of this run's findings. No scanner and no built-in review ran, so there is
nothing to compare against. This is a statement about the environment, not about
the skill.

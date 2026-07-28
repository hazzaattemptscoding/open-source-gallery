# [PROJECT NAME]

Open source, self-hosted client photo gallery and sales platform for sports photographers. Motorsport-first, generalised to other sports later. Built and proven on the maintainer's own PowerMedia gallery before public release.

## Hard constraints

- Hosting: shared hosting (target: IONOS-style, PHP 8.2+, MySQL, Apache, cron every 5 min, domain plus ~200GB storage).
- No Node, no Docker, no build step, no daemons required to run the app.
- Stack: plain PHP, MySQL, vanilla HTML/CSS/JS only.
- Everything specific to the maintainer's business (name, brand colours, Stripe keys, currency) lives in one config file. Nothing business-specific hardcoded into core code, this is an open source project other people will self-host.
- Dev environment: Docker on the maintainer's homelab, mirrors PHP/MySQL versions. Not the target host.

## Design

Default theme is "Podium Ink": ink black, signature purple #6D28D9, deep purple, gold. Fully swappable, document how.

## Product rules

- No customer accounts. Guest checkout via Stripe Checkout, delivery via signed emailed download links.
- Every photo is watermarked in the gallery (applied at 800px and up). The file delivered after purchase is the clean, unwatermarked original, sent via signed download link plus a receipt.
- Cart/favourites is a single tap, no separate confirm-to-add step, and stays visible while scrolling.
- Videos live in their own section, never mixed into the photo grid's load order.
- Gallery pages open with a full-screen hero photo, title, and light details before the grid.

## Build order (do not skip ahead)

1. Architecture + schema — done, see docs/architecture.md
2. Admin auth, CRUD, upload, tagging, derivative generation
3. Public gallery (hero, grid, cart, video separation)
4. Cart, Stripe, delivery (clean-file delivery, receipt on every order)
5. Stats + hardening
6. Open source release prep: config-driven branding, commenting standard applied throughout, INSTALL.md completed and tested end to end, LICENSE and TRADEMARK.md finalised

## Reference docs

- docs/architecture.md — approved schema, request flows, cron design, and security requirements

## Code style

Comment for a reader who has never seen this file, human or AI. Explain why a decision was made, not just what the code does. Docblocks on every function that isn't trivially self-explanatory.

## License

AGPL-3.0. Any code you write here inherits that license. Don't add dependencies under incompatible licenses.

## Installed skills

These should be installed as Claude Code plugins/skills for this project. If a session doesn't have them active, say so rather than working without them:

- **superpowers** (obra/superpowers) — TDD discipline, brainstorming before code, git worktrees. Use RED-GREEN-REFACTOR especially for cart/Stripe/delivery code.
- **stop-slop** (hardikpandya/stop-slop) — run on all user-facing copy and documentation.
- **emil-design-eng** and related (emilkowalski/skills) — animation and interaction rules for the lightbox, cart, and any motion in the UI.
- **context-engineering** (muratcankoylan/agent-skills-for-context-engineering) — use the PRP pattern when scoping each build stage.

## Writing style

No em dashes. No "I hear you" or similar filler. Plain and direct in commit messages, comments, and any user-facing copy.

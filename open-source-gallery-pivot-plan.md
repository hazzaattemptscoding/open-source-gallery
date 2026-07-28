# Open Source Motorsport Gallery: The Pivot Plan

This sits on top of the existing architecture plan (approved schema, IONOS constraints, Podium Ink design, 5-stage build order). Nothing there gets thrown out. This document covers what changes and what gets added now that the target is open source.

## 1. What this actually is

A self-hosted, open source gallery and sales platform for sports photographers, proven on your own PowerMedia instance first. Motorsport-specific now (kart numbers, classes, championships). Generalised to other sports later, once your instance is live and you're happy with it.

No competitor in this exact space exists. Every open source self-hosted gallery (Lychee, Piwigo, Photoview, PhotoPrism, Immich) is personal photo management, none of them do checkout, licensing, or watermarking. Every platform that does (Pixieset, PhotoDeck, ShootProof, Zenfolio, GotPhoto) is closed and commercial. You'd be first.

## 2. Decisions locked

| Decision | Choice | What it means |
|---|---|---|
| Sport scope | Motorsport-first | Schema stays kart_number/class as named fields for now, not abstracted. Generalising is a later phase, not upfront design tax. |
| Hosting bar | Plain PHP/MySQL, no Docker | Same constraint as your own build. A written setup tutorial in the repo walks a user through shared hosting like IONOS: domain, ~200GB storage, MySQL database, cron job. |
| License | AGPL-3.0 + trademark policy | Two different tools for two different problems, detailed below. |

### On the license, properly explained

**AGPL-3.0** is the strict copyleft license. The thing it adds over plain GPL: if someone takes your code and runs it as a hosted service (their own paid gallery SaaS built on your code), they must publish their modified source to their users. Plain GPL only triggers on distributing the software itself, not on running it as a web service, which is the loophole a lot of companies use to build closed SaaS on top of open code. AGPL closes that.

**What AGPL does not do:** stop someone renaming your project and selling it. They can fork it, call it something else, host it, charge for it, as long as they publish their source. That's still "open source" working as intended, but it's not what you meant by "can't claim it as their own."

**That's a trademark problem, not a license problem.** The fix, used by WordPress, Ghost, Mastodon, and Discourse: the code is open under AGPL, but the project name and logo are a protected trademark, reserved in a TRADEMARK.md in the repo (and registered properly later if it takes off). Anyone can fork and modify the code freely. Nobody can call their fork by your project's name or brand it as the official version. That combination gets you "customise it, don't claim it as your own."

**Action needed:** pick a project name that isn't "PowerMedia." That's your business. The open source project needs its own identity to protect. Worth deciding before the repo goes public.

## 3. The UX fixes, turned into features

Four things you described, mapped to build decisions.

**The cart problem.** Right now: select a photo, click buy, then separately confirm adding it to the package, then scroll back through hundreds of photos to check what's in there. Fix: a single tap adds to a persistent selection tray that stays visible (sticky bar, bottom of screen on mobile) as you scroll. No second confirmation step. Tap the tray any time to see and remove selections without leaving the grid.

**Videos pinned at the top.** Large video files load first and shove photos down, slowing the whole page. Fix: videos get their own tab or a separate section below the hero, never mixed into the photo grid's load order.

**The gallery landing page.** Full-bleed hero photo (one you pick per gallery), title, light details, then scroll into the grid. This is already close to what Pixieset does well; keep it.

**Watermarking and delivery.** Every photo in the gallery is watermarked (your existing plan already has this at 800px and up). What changes: make explicit that the delivered file after purchase is the clean, unwatermarked original, sent via the signed download link alongside a receipt or order confirmation. This was implicit in the original architecture; now it's a stated requirement, worth confirming it's actually wired that way in the delivery stage.

## 4. What this adds to the existing build order

Stages 1 to 5 stay as approved. This adds a new stage and folds a few things into existing ones.

**Folds into Stage 3 (public gallery):** hero landing, video separation, selection tray.

**Folds into Stage 4 (cart/Stripe/delivery):** confirm clean-file delivery, receipt on every order.

**New Stage 6: open source release prep.** Not urgent, but plan for it now so you don't have to retrofit:
- Business-specific values (name, brand colours, Stripe keys, currency) move into one config file, nothing hardcoded to PowerMedia in the core code
- Podium Ink ships as the default theme, clearly documented as swappable
- A commenting standard for the codebase: docblocks that explain *why*, not just *what*, so both you and an AI coding tool can pick up any file cold
- INSTALL.md: step by step for a non-technical user on shared hosting, domain purchase through first admin login
- ARCHITECTURE.md: the schema and structure, written for a reader who's never seen the codebase
- LICENSE (AGPL-3.0) and TRADEMARK.md

## 5. Roadmap after your own instance is live

In order, not urgent, don't build ahead of need:

1. Get your own PowerMedia instance through stages 1 to 5, live, and you're happy with it day to day
2. Stage 6 release prep, repo goes public
3. Generalise beyond motorsport: kart_number/class become configurable field types instead of fixed columns
4. Lightroom plugin for direct export to the gallery, this is genuinely separate work (Lightroom SDK, a different codebase) and should not block anything above it

## 6. Still open

- Project name, needed before the repo goes public and before TRADEMARK.md means anything
- Whether the setup tutorial is written docs only, or a guided install script that asks for DB credentials and writes config.php for the user. Written docs is less work and matches "easy setup" without adding a wizard to build and maintain. Worth deciding once you're closer to Stage 6.

## Next

Once you've reviewed this, we turn it into the actual repo structure: README.md, ARCHITECTURE.md, INSTALL.md, CONTRIBUTING.md, LICENSE, TRADEMARK.md, and the CLAUDE.md for Claude Code. Say go and I'll draft them.

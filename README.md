# [PROJECT NAME]

Open source, self-hosted gallery and sales platform for sports photographers. Motorsport-first. Built and proven on the maintainer's own PowerMedia gallery before wider release.

Runs on plain PHP and MySQL. No Node, no Docker, no build step required. Designed to work on ordinary shared hosting (the kind that comes with a domain and storage from providers like IONOS).

## Why this exists

Every existing self-hosted gallery project (Lychee, Piwigo, Photoview, and similar) is built for personal photo management. None of them handle client sales, checkout, licensing, or watermarking. Every platform that does that (Pixieset, PhotoDeck, ShootProof, and similar) is closed and commercial. This fills that gap.

## Status

Early build. Not ready for production use by anyone other than the maintainer yet. Follow along, but don't deploy this for paying clients until a tagged release says otherwise.

## What it does

- Browse by championship, then event, then gallery
- One-tap select to a persistent cart, no double-confirmation step
- Watermarked previews, clean files delivered after purchase via a signed download link
- Guest checkout, no customer accounts required
- Videos kept separate from the photo grid so large files don't slow browsing
- Fully custom branding, colours, and payment setup, no fixed presets

## Docs

- [ARCHITECTURE.md](docs/architecture.md) — database schema, system design, request flows, and security requirements
- [INSTALL.md](INSTALL.md) — setup guide for shared hosting
- [CONTRIBUTING.md](CONTRIBUTING.md) — how to contribute
- [CLAUDE.md](CLAUDE.md) — project instructions for Claude Code

## License

AGPL-3.0. See [LICENSE](LICENSE). If you run a modified version of this as a hosted service, you're required to make your modified source available to your users.

The project name and logo are protected separately under [TRADEMARK.md](TRADEMARK.md). You can fork and modify the code freely; you can't call your fork by this project's name.

## Author

Built by Harry, [PowerMedia](https://powerrmediaa.com).

# Contributing

Thanks for taking an interest. A few ground rules while this project is young.

## Before you open a PR

- Keep PRs to one change. Unrelated changes bundled together are harder to review and more likely to get rejected.
- Match the existing code style: plain PHP, no framework, no build step. If your change needs a dependency, explain why in the PR description.
- Everything you contribute is licensed under this project's AGPL-3.0 license.

## Commenting standard

This one matters more here than on most projects. The people maintaining a
self-hosted install are often not full-time programmers, and many will be
working through the code with an AI assistant. Write for that reader.

- **Every file gets a header block** saying what the file does and how it fits
  into the rest of the app. Not a restatement of the filename.
- **Every function that is not trivially self-explanatory gets a docblock** in
  plain English, including what it returns and anything surprising about it.
- **Explain why, not what.** `$i++` does not need a comment. A magic number, a
  workaround for a host's behaviour, an ordering dependency, or a decision that
  looks wrong until you know the reason, all do.
- **Record the reasoning behind non-obvious decisions in the comment itself**, so
  the next person does not "fix" it back. If you removed something deliberately,
  say that it was deliberate and why.

## Database rules

- **Prepared statements for every query.** No string interpolation into SQL, ever.
- **Migrations are additive only.** Never edit a migration that has shipped;
  someone has already run it. Add a new one.
- **Write both versions of every migration.** MySQL is the production target and
  SQLite is used for local development, so `migrations/` carries a matching
  `.sql` and `.sqlite.sql` pair. A migration with only one of the two will break
  half the contributors.

## Security rules

- Use `e()` on every value interpolated into HTML output.
- Add an audit-log entry for anything sensitive: logins, exports, refunds,
  permission changes, deletions.
- Never log or commit secrets. Stripe keys and the SMTP password belong in
  `config/config.php`, which is gitignored, and never in the database, because
  the backup job archives the database.

## Privacy rules

Read [docs/PRIVACY-DESIGN.md](docs/PRIVACY-DESIGN.md) before touching anything
that handles driver data. Two rules follow from it:

- **No facial recognition or selfie search.** Not as an option, not behind a
  flag. The reasoning is in that document.
- **Driver names never reach a public surface.** That includes the ones that are
  easy to forget because they are not visible on the page: alt text, meta tags,
  JSON-LD, `data-` attributes, filenames, and query parameters the public routes
  accept. Public identity is kart number plus class.

## What this project won't accept

- Anything that requires Node, or a build step, to run the core app. Shared
  hosting compatibility is the whole point. Docker is fine for local development
  and ships in the repo for that, but the app must run without it.
- Dependencies under licenses incompatible with AGPL-3.0.
- Features that add customer accounts or remove the guest-checkout model, unless
  discussed first in an issue.
- Hardcoded prices, currencies, domains or brand strings. Everything
  business-specific goes through `config/config.php`, because other people
  self-host this.

## Reporting bugs

Open an issue with what you expected, what happened instead, and your PHP/MySQL versions.

## Trademark note

The project name and logo are protected separately, see TRADEMARK.md. You're free to fork and modify the code under AGPL-3.0; forks distributed under the original project name are not permitted.

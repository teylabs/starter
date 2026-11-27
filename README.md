# Starter

> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

The new-app template for tenant-aware Laravel apps, built on [tey/kit](https://github.com/teylabs/kit) with the kit's default features added: workspace membership and invitations, versioned settings shared by session pages and MCP tools, Fortify sessions, Inertia/Vue pages, and Passport OAuth with PKCE. Notes is opt-in: list it in `config/kit.php`'s `features` and run `php artisan kit:scaffold notes` ([lifecycle](docs/lifecycle.md#adding-notes)). Maintained packages own their jobs; no production application or database is copied.

PHP 8.4 or 8.5 and Node 22 are required.

## First run

```sh
composer setup
```

From a fresh clone (or `laravel new app --using=tey/starter` once the template is on Packagist), one command gets the app running locally, on [Laravel Herd](https://herd.laravel.com) when it is installed. It installs the dependencies, copies `.env.example` to `.env` and generates the key. When PostgreSQL answers on 127.0.0.1:5432 (Herd's PostgreSQL service; the login is `.env`'s, else `DB_USERNAME`/`DB_PASSWORD` from the environment, else Herd's `root`), it creates the app's database, migrates as the owner and creates the row-level-security runtime role (`DB_RUNTIME_*`), as production runs; otherwise it uses `database/database.sqlite`. It writes Passport's keys, sends mail to Herd's mail catcher when it runs (else the log), links and secures the Herd site as `https://<folder>.test`, builds the frontend and creates the first local owner and workspace (`php artisan starter:first-owner`, an operator). It prompts for the owner's password; with `--no-interaction` it generates one and prints it once. Every step is skipped when it is already done, so it is safe to re-run, and it ends with what is left for a person. Nothing is stored or seeded outside the local environment, and there are no default credentials.

Then open the site (or `php artisan serve` without Herd) and sign in at `/login`, with the password or, locally, with Concierge's one-click "Sign in as owner@example.com" (`CONCIERGE_LOCAL_LOGIN`; Concierge never offers it outside the local environment). Settings → Setup shows what is set up. `composer dev` runs the scheduler and Vite's hot reload (and a queue worker when `QUEUE_CONNECTION` is not `sync`); `composer setup -- --sqlite` keeps SQLite.

`laravel/tinker` is a dev dependency (`php artisan tinker`).

## Check

```sh
vendor/bin/pint --test
php artisan standards:check
vendor/bin/pest --tia      # the tests your change touches
composer fixture:check
composer test:browser
npm run typecheck && npm run build && npm test
sh bin/privacy-check.sh
```

The PHP suites run on PostgreSQL, as production does, and in parallel with a database per worker (`{DB_DATABASE}_w{token}`, created on first use). The connection comes from the committed `.env.testing` (Herd's PostgreSQL on `127.0.0.1:5432`, user `root`, database `starter_testing`), and any `DB_*` variable set for the run overrides it; `.env` is never read. Each run wipes its database, so name a disposable one. `pest` and `composer fixture:check` print the database in use:

```sh
vendor/bin/pest --tia --parallel     # or composer test
DB_DATABASE=my_branch_testing composer fixture:check
```

SQLite stays only for the few checks that read a SQLite dump (`TEST_DB_CONNECTION=sqlite vendor/bin/pest --no-tia tests/FeatureSeamsTest.php`) and for pure unit tests that touch no database.

`composer test:local` runs the whole suite on a laptop when you mean to: the PHP suite on 8 processes (without the `nightly` group), then the browser suite on 4. Ctrl-C stops both and every worker. CI is the gate, so a push never waits for it.

`php artisan standards:check` runs tey/standards' conformance rules (configured in `tests/Arch/configuration.php`) and must exit 0. The gate is strict: every optional and advisory rule is on (`optionalRules`), rule 51 (CI shape) is enforced (`ciEnforce`, and `PREFLIGHT_CI_ENFORCE=true` in CI), and the allowlist is empty. A finding is fixed in the code, never allowlisted. Before building a page, action, MCP tool or setting, find its pattern with `bakery.find` (offline: `php artisan standards:pattern "<intent>"`). `standards:install` also adds Claude Code hooks in `.claude/settings.json` (a pre-commit and Stop check, a pattern lookup for new files, and a check after each edit) and a git pre-push hook running `standards:check --changed`. Laravel Boost (`boost.json`, `.ai/guidelines`) publishes the guidelines and skills to Claude Code and Codex; refresh them with `php artisan boost:update`.

CI checks Pint and standards, runs the PHP suites in parallel on PostgreSQL, checks the template's kit files against `kit:scaffold --diff`, and runs `composer setup` and the browser test on a fresh clone. See [testing](docs/testing.md).

## Status

v0.3 rebuilds the template on tey/kit 0.1. Not yet available: `laravel new --using=tey/starter` (not on Packagist yet) and the deploy smoke command (#32). Password reset by emailed link is on; it needs a mailer that can send the link. Not yet proven: PostgreSQL concurrency, jobs and files. See [ROADMAP.md](ROADMAP.md).

## License

MIT, see [LICENSE](LICENSE). The UI primitives and sign-in page come from Laravel's Vue starter kit (MIT, see `resources/js/components/ui/LICENSE.md`). Provided as is, with no support.

## Docs

- [Architecture](docs/architecture.md): tey/kit, the packages and the job each owns
- [Operations](docs/operations.md): settings, versions, intent keys, audit
- [Transports](docs/transports.md): session/Inertia and MCP/OAuth, scopes, consent
- [Lifecycle](docs/lifecycle.md): install, kit features, migrations from 0.2 and updates
- [Deploy](docs/deploy.md): the Laravel Cloud deploy commands, in order
- [Testing](docs/testing.md): gates and focused checks
- [llms.txt](llms.txt): index for coding agents
- [AGENTS.md](AGENTS.md): contributor and agent conventions

Machine-readable maps live in `docs/*.json`: `mcp-capabilities.json` (the MCP capability declaration, validated against the vendored `schemas/mcp-capabilities.schema.json`), `package-map.json`, `conformance.json`, `feature-transports.json` and `generation-manifest.json`.

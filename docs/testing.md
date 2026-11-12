# Testing

## Gates

CI (`.github/workflows/ci.yml`) runs on pull requests (opened, synchronize, reopened and ready for review) and on pushes to `main`. A draft pull request runs the lint and static jobs only; the test jobs wait until it is ready. A newer push cancels an older run of the same pull request, never a run on `main`. Every job has a timeout, and nothing runs with `--bail`.

Pint (`pint.json` excludes `database/migrations/kit`, which must stay byte-identical to `kit:add`):

```sh
vendor/bin/pint --test
```

Standards, through the reusable `teylabs/standards` workflow, strict: every optional and advisory rule on, rule 51 enforced, no allowlist entries. It runs `standards:check --changed` against the pull request's base, or the full check on `main`, with the JSON report kept as the `standards-report` artifact. Locally, the git pre-push hook and the Claude Code hooks in `.claude/settings.json` run the same check:

```sh
php artisan standards:check
```

PHP on 8.4 for a pull request, 8.4 and 8.5 on `main`, against a PostgreSQL 17 service (the job's `DB_*`), in parallel with a database per worker. The released-schema comparison in `FeatureSeamsTest.php` reads a SQLite dump, so that file runs once more on SQLite memory:

```sh
composer install --no-interaction
vendor/bin/pest --parallel
composer fixture:check
TEST_DB_CONNECTION=sqlite vendor/bin/pest tests/FeatureSeamsTest.php
```

Node 22:

```sh
npm ci
npm run typecheck
npm run build
npm test
```

### The test database

The tests run on PostgreSQL, as production does (Jasper's ruling, 2026-11-07): `phpunit.xml` and the committed `.env.testing` set `DB_CONNECTION=pgsql`, and `tests/environment.php` takes `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` from the process environment, falling back to `.env.testing` (Herd's PostgreSQL: `127.0.0.1:5432`, user `root`, no password, database `starter_testing`). `.env` is never read, because each app boot wipes the database. The database is created on first use. A parallel run (`--parallel`) gives each worker its own, `{DB_DATABASE}_w{token}`. Each run prints the database in use (`Test database: ...` on stderr, and `database` in the focused checks' JSON). DBngin or Postgres.app may need `DB_USERNAME` and `DB_PASSWORD` set for the run. Two checkouts running at once should each set their own `DB_DATABASE`:

```sh
composer test                                       # pest --tia --parallel
DB_DATABASE=feature_x_testing vendor/bin/pest --no-tia --parallel
composer fixture:check
```

`TEST_DB_CONNECTION=sqlite` puts a run on SQLite memory instead. It is kept for the released-schema comparison (a SQLite dump) and pure unit tests that touch no database features; nothing else should need it.

Kit parity: `sh bin/kit-parity.sh <clean-app>` runs `kit:install` and `kit:add` of the template's features (`config/kit.php`) on a clean `laravel/laravel` app and fails unless every feature-owned path equals the template's. A file the template marks as a deliberate tweak is listed as a graduation candidate instead of failing. Put `// kit: tweaked <reason>` (or `<!-- kit: tweaked <reason> -->` in a Vue template) in its first five lines; this follows the house rule: tweak in the app, graduate into the kit once proven. Unmarked differences, and files on only one side, still fail.

Fresh clone: `composer setup --no-interaction`, the sign-in page returns 200 over HTTP and over HTTPS behind a local TLS-terminating proxy (`bin/tls-proxy.mjs`, with its assets linked over `https://`), then `composer test:browser` (Pest's browser plugin with Playwright Chromium) starts from zero (a local boot with `.env.example`'s local sign-in settings and `starter:first-owner`'s defaults), signs the owner in with Concierge's one-click button, sees their workspace listed in the shell nav and their name only in the user menu, then opens the workspace, saving screenshots to `tests/Browser/Screenshots` (an artifact in CI).

The whole suite locally, when you mean to: `composer test:local` (`bin/test-local.sh`) runs `pest --parallel --processes=8 --exclude-group=nightly`, then the browser suite with `PEST_BROWSER=1` on 4 processes. Ctrl-C stops both suites and every worker (the script traps it and signals its whole process group). `composer test:browser` runs the browser suite in parallel, in CI too.

Accessibility: `assertAccessible($page, 'what it shows')` (`tests/Pest.php`) is called by every browser test file (standards' `browser.accessible` rule flags a file that calls `visit()` without it). Most files call it on each page they settle on; `tests/Browser/SignIn.php` scans only the sign-in page, and `tests/Browser/Accessible.php` the dashboard, `/settings/people` and an unknown invitation link. axe's WCAG 2.2 AA rules fail the test; its best-practice rules (`landmark-one-main`, `region`, `heading-order` and the rest) only warn, printing `AXE WARNING <rule> (<impact>) <selector> @ <url>` to STDERR. A parallel run (`composer test:browser`, `composer test:local`, or any `--parallel`) swallows its workers' STDERR, so the warnings never show there, in CI either. To see them, run the accessibility test on its own without `--parallel`, for example `PEST_BROWSER=1 vendor/bin/pest --no-tia --configuration=tests/Browser/phpunit.xml tests/Browser/SignIn.php`. The scan waits for the Inertia root (`#app`) to render before it runs: a full load fires `load` before the page component has loaded (slowest on the Vite dev server), and a scan of the empty root reports `landmark-one-main` and `page-has-heading-one` on pages that have both.

These are CI's gates: run them by hand only to reproduce a red. Before a push, run the tests your change touches (`composer test`, or one file) and let PR CI run the rest. `composer types:generate` regenerates the TypeScript types; it is not a CI gate.

## Focused checks

Run one file while working:

```sh
vendor/bin/pest tests/TransportTest.php
```

| File | Covers |
| --- | --- |
| `TransportTest.php` | Session requests through the HTTP kernel: Fortify login, cookie reuse, foreign tenant refusal, settings retry/stale/audit, CSRF, logout |
| `InertiaTransportTest.php` | Inertia components and props, navigation, redirects, validation and error outcomes |
| `McpTransportTest.php` | OAuth consent, code and token exchange, MCP JSON-RPC for settings, scope, origin, expiry and revocation limits |
| `LifecycleTest.php` | `kit:add` of the template's features through the released tey/kit and Mod in temp checkouts: the written files equal the template's, a no-op rerun, adopting the template's own files by reviewed claim, an edited file refused, the fail-closed operation check, the console command |
| `FeatureSeamsTest.php` | The 0.2.1 schema with what the kit has added since, the route seams each route declares, the registry wiring, and databases migrated by 0.2 moving onto the kit migrations with their data |
| `FeatureGraphTest.php` | The kit library's feature graph, and pages importing only their own folder |
| `LocalLoginOfferTest.php` | The sign-in page offers Concierge's one-click local sign-in (and it signs in) exactly when `LocalLogin::isAvailable()` says so, and not otherwise |
| `TrustedHostTest.php` | Only `APP_URL`'s host is trusted behind the proxy: forged and lookalike hosts get a 400 |
| `McpCapabilitiesTest.php`, `StorageTest.php`, `NightwatchConfigTest.php`, `CacheGuardTest.php`, `TimezoneTest.php` | The MCP capability declaration (validated against `docs/schemas/mcp-capabilities.schema.json`, every path and symbol it names exists), the private s3 disk and media disk, Nightwatch sampling, the cache holding only arrays and scalars, UTC |
| `MigrationTest.php` | Migrating an empty database, ordering, no seeded data, repeat runs |

`composer fixture:check` runs `bin/check.php` for `FoundationCheck`, `BoundaryCheck`, `IntentCheck`, `OwnershipCheck` and `ConciergeCheck`.

## Rules for tests

- Focused checks run on PostgreSQL (a disposable database per parallel worker) by loading `tests/application.php`, which sets a synthetic key and wipes the database as each app boots. Public requests never load it.
- Public requests must not seed fixtures, bypass auth or use default credentials or keys.
- No external services, production data or provider side effects.
- Keep the tenant, ownership, grant and stale-write regression tests.
- Restore tenant, permission and settings context in `finally`.
- PHP tests disable Vite asset loading, so `npm run typecheck` and `npm run build` are the only client checks.
- Styling is Tailwind utilities only. No hand-written CSS rules or `<style>` blocks.

## Not covered

These checks use the HTTP kernel with synthetic data, plus one browser smoke test (desktop Chromium, sign-in and the app shell). No responsive, keyboard, accessibility, visual-diff or cookie-policy acceptance; HTTPS is checked only through a local TLS proxy, not a real certificate or platform. The released-schema comparison runs only in the SQLite step (the fixture is a SQLite dump); there is no PostgreSQL concurrency check. No jobs, files, providers, production install or rollback. Lifecycle checks run in disposable checkouts, not a `laravel new` install or a published template upgrade. Say so rather than implying coverage.

Local runs need `--tia` or `--no-tia`; a non-empty `CI`, `PARATEST` or `PEST_TIA` bypasses that guard (an empty `CI=` does not). TIA diffs against `main`, or the branch in `TIA_DEFAULT_BRANCH`. Files that runtime coverage cannot link re-run tests by the watch map in `tests/Pest.php`: config, routes, front-end assets and `database/**` (factories and seeders included) re-run the suite; `app/**` and `.github/workflows/**` re-run the source-reading arch tests; `tests/**` re-runs the application-free checks.

`vendor/bin/pest --parallel` gives each worker its own disposable database, `{DB_DATABASE}_w{token}`, created on first use, and its own storage root. `vendor/bin/pest`, `vendor/bin/phpunit` and `vendor/bin/pest --tia` read the root `phpunit.xml`.

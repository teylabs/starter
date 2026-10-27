# Testing

## Gates

CI (`.github/workflows/ci.yml`) runs on pull requests and on pushes to `main`.

Pint (`pint.json` excludes `database/migrations/kit`, which must stay byte-identical to `kit:add`):

```sh
vendor/bin/pint --test
```

Standards, through the reusable `teylabs/standards` workflow: `standards:check --changed` against the pull request's base, or the full check on `main`, with the JSON report kept as the `standards-report` artifact. Locally, the git pre-push hook and the Claude Code hooks in `.claude/settings.json` run the same check:

```sh
php artisan standards:check
```

PHP, on 8.4 and 8.5 (SQLite memory), and on 8.4 against a PostgreSQL 17 service (`TEST_DB_CONNECTION=pgsql` with the job's `DB_*`):

```sh
composer install --no-interaction
vendor/bin/pest
composer fixture:check
```

Node 22:

```sh
npm ci
npm run typecheck
npm run build
npm test
```

### PostgreSQL locally

`TEST_DB_CONNECTION` selects the test database for both `pest` and `composer fixture:check`: `sqlite` (the default, in memory) or `pgsql`. With `pgsql`, the connection comes from `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`. `DB_DATABASE` must be set for the run, never taken from `.env`, because each app boot wipes it. Each run prints the database in use (`Test database: ...` on stderr, and `database` in the focused checks' JSON). `DB_CONNECTION` alone does not switch the tests. With Herd's PostgreSQL:

```sh
createdb starter_test
export TEST_DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=starter_test DB_USERNAME=root DB_PASSWORD=
vendor/bin/pest
composer fixture:check
```

Kit parity: `sh bin/kit-parity.sh <clean-app>` runs `kit:install` and `kit:add` of every feature on a clean `laravel/laravel` app and fails unless every feature-owned path equals the template's.

Fresh clone: `composer setup --no-interaction`, the sign-in page returns 200 over HTTP and over HTTPS behind a local TLS-terminating proxy (`bin/tls-proxy.mjs`, with its assets linked over `https://`), then `composer test:browser` (Pest's browser plugin with Playwright Chromium) starts from zero (a local boot with `.env.example`'s local sign-in settings and `starter:first-owner`'s defaults), signs the owner in with Concierge's one-click button, sees their workspace listed in the shell nav and their name only in the user menu, then opens the workspace, saving screenshots to `tests/Browser/Screenshots` (an artifact in CI).

Run all of these before opening a pull request. `composer types:generate` regenerates the TypeScript types; it is not a CI gate.

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
| `LifecycleTest.php` | `kit:add` of every feature through released tey/kit 0.1.5 and Mod 0.9 in temp checkouts: the written files equal the template's, a no-op rerun, adopting the template's own files by reviewed claim, an edited file refused, the fail-closed operation check, the console command |
| `FeatureSeamsTest.php` | The 0.2.1 schema, routes and registry wiring, and databases migrated by 0.2 moving onto the kit migrations with their data |
| `FeatureGraphTest.php` | The kit library's feature graph, and pages importing only their own folder |
| `LocalLoginOfferTest.php` | The sign-in page offers Concierge's one-click local sign-in (and it signs in) exactly when `LocalLogin::isAvailable()` says so, and not otherwise |
| `TrustedHostTest.php` | Only `APP_URL`'s host is trusted behind the proxy: forged and lookalike hosts get a 400 |
| `McpCapabilitiesTest.php`, `StorageTest.php`, `NightwatchConfigTest.php`, `CacheGuardTest.php`, `TimezoneTest.php` | The MCP capability declaration (validated against `docs/schemas/mcp-capabilities.schema.json`, every path and symbol it names exists), the private s3 disk and media disk, Nightwatch sampling, the cache holding only arrays and scalars, UTC |
| `MigrationTest.php` | Migrating an empty database, ordering, no seeded data, repeat runs |

`composer fixture:check` runs `bin/check.php` for `FoundationCheck`, `BoundaryCheck`, `IntentCheck`, `OwnershipCheck` and `ConciergeCheck`.

## Rules for tests

- Focused checks opt into synthetic SQLite memory (or PostgreSQL through `TEST_DB_CONNECTION=pgsql`) by loading `tests/application.php`, which sets a synthetic key and wipes a PostgreSQL database as each app boots. Public requests never load it.
- Public requests must not seed fixtures, bypass auth or use default credentials or keys.
- No external services, production data or provider side effects.
- Keep the tenant, ownership, grant and stale-write regression tests.
- Restore tenant, permission and settings context in `finally`.
- PHP tests disable Vite asset loading, so `npm run typecheck` and `npm run build` are the only client checks.
- Styling is Tailwind utilities only. No hand-written CSS rules or `<style>` blocks.

## Not covered

These checks use the HTTP kernel with synthetic data, plus one browser smoke test (desktop Chromium, sign-in and the app shell). No responsive, keyboard, accessibility, visual-diff or cookie-policy acceptance; HTTPS is checked only through a local TLS proxy, not a real certificate or platform. On PostgreSQL the released-schema comparison is skipped (the fixture is a SQLite dump); there is no concurrency check. No jobs, files, providers, production install or rollback. Lifecycle checks run in disposable checkouts, not a `laravel new` install or a published template upgrade. Say so rather than implying coverage.

Local runs need `--tia` or `--no-tia`; a non-empty `CI`, `PARATEST` or `PEST_TIA` bypasses that guard (an empty `CI=` does not). TIA diffs against `main`, or the branch in `TIA_DEFAULT_BRANCH`. Files that runtime coverage cannot link re-run tests by the watch map in `tests/Pest.php`: config, routes, front-end assets and `database/**` (factories and seeders included) re-run the suite; `app/**` and `.github/workflows/**` re-run the source-reading arch tests; `tests/**` re-runs the application-free checks.

`vendor/bin/pest --parallel` also works: on PostgreSQL each worker gets its own disposable database, `{DB_DATABASE}_w{token}`, created on first use, and its own storage root. `vendor/bin/pest`, `vendor/bin/phpunit` and `vendor/bin/pest --tia` read the root `phpunit.xml`.

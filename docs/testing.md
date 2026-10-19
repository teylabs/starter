# Testing

## Gates

CI (`.github/workflows/ci.yml`) runs on pull requests and on pushes to `main`.

PHP, on 8.4 and 8.5 (SQLite memory), and on 8.4 against a PostgreSQL 17 service (`TEST_DB_CONNECTION=pgsql` with the job's `DB_*`):

```sh
composer install --no-interaction
vendor/bin/phpunit --bootstrap vendor/autoload.php tests
composer fixture:check
```

Node 22:

```sh
npm ci
npm run typecheck
npm run build
npm test
```

Kit parity: `sh bin/kit-parity.sh <clean-app>` runs `kit:install` and `kit:add` of every feature on a clean `laravel/laravel` app and fails unless every feature-owned path equals the template's.

Fresh clone: `composer setup --no-interaction`, the sign-in page returns 200, then `composer test:browser` (Pest's browser plugin with Playwright Chromium) signs the owner in and sees the app shell, saving screenshots to `tests/Browser/Screenshots` (an artifact in CI).

Run all of these before opening a pull request. `composer types:generate` regenerates the TypeScript types; it is not a CI gate.

## Focused checks

Run one file while working:

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php tests/TransportTest.php
```

| File | Covers |
| --- | --- |
| `TransportTest.php` | Session requests through the HTTP kernel: Fortify login, cookie reuse, foreign tenant refusal, settings retry/stale/audit, CSRF, logout |
| `InertiaTransportTest.php` | Inertia components and props, navigation, redirects, validation and error outcomes |
| `NotesTransportTest.php` | Notes CRUD over session HTTP: tenant refusal, retries, stale versions, viewer and capability refusal, validation, audit rollback |
| `McpTransportTest.php` | OAuth consent, code and token exchange, MCP JSON-RPC for settings and Notes, scope, origin, expiry and revocation limits |
| `LifecycleTest.php` | `kit:add` of every feature through released tey/kit 0.1.2 and Mod 0.9 in temp checkouts: the written files equal the template's, a no-op rerun, adopting the template's own files by reviewed claim, an edited file refused, the fail-closed operation check, the console command |
| `FeatureSeamsTest.php` | The 0.2.1 schema, routes and registry wiring, and databases migrated by 0.2 moving onto the kit migrations with their data |
| `FeatureGraphTest.php` | The kit library's feature graph, and pages importing only their own folder |
| `McpCapabilitiesTest.php`, `StorageTest.php`, `NightwatchConfigTest.php`, `CacheGuardTest.php`, `TimezoneTest.php` | The committed MCP inventory, the private s3 disk and media disk, Nightwatch sampling, the cache holding only arrays and scalars, UTC |
| `MigrationTest.php` | Migrating an empty database, ordering, no seeded data, repeat runs |

`composer fixture:check` runs `bin/check.php` for `FoundationCheck`, `BoundaryCheck`, `IntentCheck`, `OwnershipCheck` and `ConciergeCheck`.

## Rules for tests

- Focused checks opt into synthetic SQLite memory (or the CI PostgreSQL service) by loading `tests/application.php`, which sets a synthetic key and wipes a PostgreSQL database as each app boots. Public requests never load it.
- Public requests must not seed fixtures, bypass auth or use default credentials or keys.
- No external services, production data or provider side effects.
- Keep the tenant, ownership, grant and stale-write regression tests.
- Restore tenant, permission and settings context in `finally`.
- PHP tests disable Vite asset loading, so `npm run typecheck` and `npm run build` are the only client checks.
- Styling is Tailwind utilities only. No hand-written CSS rules or `<style>` blocks.

## Not covered

These checks use the HTTP kernel with synthetic data, plus one browser smoke test (desktop Chromium, sign-in and the app shell). No responsive, keyboard, accessibility, visual-diff or cookie-policy acceptance, and no HTTPS. On PostgreSQL the released-schema comparison is skipped (the fixture is a SQLite dump); there is no concurrency check. No jobs, files, providers, production install or rollback. Lifecycle checks run in disposable checkouts, not a `laravel new` install or a published template upgrade. Say so rather than implying coverage.

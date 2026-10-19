# Testing

## Gates

CI (`.github/workflows/ci.yml`) runs on pull requests and on pushes to `main`.

PHP, on 8.4 and 8.5 (SQLite):

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
```

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
| `MigrationTest.php` | Migrating an empty database, ordering, no seeded data, repeat runs |

`composer fixture:check` runs `bin/check.php` for `FoundationCheck`, `BoundaryCheck`, `IntentCheck`, `OwnershipCheck` and `ConciergeCheck`.

## Rules for tests

- Focused checks opt into synthetic SQLite memory by loading `tests/environment.php`, which sets a synthetic key. Public requests never load it.
- Public requests must not seed fixtures, bypass auth or use default credentials or keys.
- No external services, production data or provider side effects.
- Keep the tenant, ownership, grant and stale-write regression tests.
- Restore tenant, permission and settings context in `finally`.
- PHP tests disable Vite asset loading, so `npm run typecheck` and `npm run build` are the only client checks.
- Styling is Tailwind utilities only. No hand-written CSS rules or `<style>` blocks.

## Not covered

These checks use the HTTP kernel with synthetic data. No browser, responsive, keyboard, visual or cookie-policy acceptance. No running network server or client. No PostgreSQL concurrency, jobs, files, providers, production install or rollback. Say so rather than implying coverage.

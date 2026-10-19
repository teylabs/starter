# Starter

> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

A tenant-aware Laravel starter with two synthetic workspaces for local checks. It provides workspace membership and invitations, versioned settings and Notes shared by session pages and MCP tools, Fortify sessions, Inertia/Vue pages, and Passport OAuth with PKCE. Maintained packages own their jobs; no production application or database is copied.

PHP 8.4 or 8.5 and Node 22 are required.

## Install and check

```sh
composer install --no-interaction
npm ci
vendor/bin/phpunit --bootstrap vendor/autoload.php tests
composer fixture:check
npm run typecheck
npm run build
```

To run the app, copy `.env.example` to `.env`, set a key and database, then run `php artisan migrate --no-interaction`. Nothing is seeded, registration is closed and there are no default credentials. See [lifecycle](docs/lifecycle.md).

## Status

The first release (v0.2) is not tagged, and install and upgrade tooling is planned, not available. Not yet proven: production first run, browser acceptance, PostgreSQL concurrency, jobs and files. See [ROADMAP.md](ROADMAP.md).

## Docs

- [Architecture](docs/architecture.md): packages and the job each owns
- [Operations](docs/operations.md): settings and Notes, versions, intent keys, audit
- [Transports](docs/transports.md): session/Inertia and MCP/OAuth, scopes, consent
- [Lifecycle](docs/lifecycle.md): clean-schema install today, upgrades planned
- [Testing](docs/testing.md): gates and focused checks
- [llms.txt](llms.txt): index for coding agents
- [AGENTS.md](AGENTS.md): contributor and agent conventions

Machine-readable maps live in `docs/*.json`: `package-map.json`, `conformance.json`, `feature-transports.json` and `generation-manifest.json`.

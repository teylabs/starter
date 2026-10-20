# Starter

> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

The new-app template for tenant-aware Laravel apps, built on [tey/kit](https://github.com/teylabs/kit) with every kit feature added: workspace membership and invitations, versioned settings and Notes shared by session pages and MCP tools, Fortify sessions, Inertia/Vue pages, and Passport OAuth with PKCE. Maintained packages own their jobs; no production application or database is copied.

PHP 8.4 or 8.5 and Node 22 are required.

## First run

```sh
composer setup
```

From a fresh clone (or `laravel new app --using=tey/starter` once the template is on Packagist), one command installs the dependencies, copies `.env.example` to `.env`, generates the key, creates `database/database.sqlite`, migrates, builds the frontend and creates the first local owner and workspace (`php artisan starter:first-owner`). It prompts for the owner's password; with `--no-interaction` it generates one and prints it once. Nothing is stored or seeded outside the local environment, and there are no default credentials. Then `php artisan serve` and sign in at `/login`.

`laravel/tinker` is a dev dependency (`php artisan tinker`).

## Check

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php tests
composer fixture:check
composer test:browser
npm run typecheck && npm run build && npm test
sh bin/privacy-check.sh
```

CI runs the PHP suites on SQLite memory and on PostgreSQL, checks the template's feature files against `kit:add` on a clean Laravel app, and runs `composer setup` and the browser test on a fresh clone. See [testing](docs/testing.md).

## Status

v0.3 rebuilds the template on tey/kit 0.1. Not yet available: `laravel new --using=tey/starter` (not on Packagist yet), password reset (it needs mail, #24) and the deploy smoke command (#32). Not yet proven: PostgreSQL concurrency, jobs and files. See [ROADMAP.md](ROADMAP.md).

## License

MIT, see [LICENSE](LICENSE). The UI primitives and sign-in page come from Laravel's Vue starter kit (MIT, see `resources/js/components/ui/LICENSE.md`). Provided as is, with no support.

## Docs

- [Architecture](docs/architecture.md): tey/kit, the packages and the job each owns
- [Operations](docs/operations.md): settings and Notes, versions, intent keys, audit
- [Transports](docs/transports.md): session/Inertia and MCP/OAuth, scopes, consent
- [Lifecycle](docs/lifecycle.md): install, kit features, migrations from 0.2 and updates
- [Deploy](docs/deploy.md): the Laravel Cloud deploy commands, in order
- [Testing](docs/testing.md): gates and focused checks
- [llms.txt](llms.txt): index for coding agents
- [AGENTS.md](AGENTS.md): contributor and agent conventions

Machine-readable maps live in `docs/*.json`: `mcp-capabilities.json` (the MCP capability inventory, version 1 of the cookbook schema), `package-map.json`, `conformance.json`, `feature-transports.json` and `generation-manifest.json`.

# Starter

Guidance for contributors and coding agents working in this repository.

- Preserve the tenant, ownership, grant and stale-write regression tests.
- Focused checks explicitly opt into synthetic SQLite memory. Public requests must never seed fixtures, bypass auth, or use default credentials or keys.
- No external services, production data or provider side effects in tests.
- Fail closed, and restore tenant, permission and settings context in `finally` blocks.
- Keep capabilities, entitlements, membership and admission distinct.
- State missing Inertia, protocol, lifecycle or platform coverage explicitly rather than implying it.
- Never edit a file a kit feature owns (`database/migrations/kit/**`, the kit's pages): CI checks them against `kit:add`.

## Gates

```sh
composer install --no-interaction
vendor/bin/pint --test
vendor/bin/phpunit --bootstrap vendor/autoload.php tests
composer fixture:check
npm ci && npm run typecheck && npm run build && npm test
composer test:browser
sh bin/privacy-check.sh
```

The PHP suites run on SQLite memory. `TEST_DB_CONNECTION=pgsql` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` set for the run puts `phpunit` and `composer fixture:check` on PostgreSQL, as CI does; the database is wiped. See `docs/testing.md` for a local Herd example.

## Conventions

- Styling is Tailwind utilities only, with tokens in `@theme`. No hand-written CSS rules, `<style>` blocks or appearance `style=` bindings; a genuine exception goes in the guard's allow-list with a reason.
- Commit as Jasper Tey <jasper@teylabs.com>. Commits and PRs carry no attribution lines or trailers.
- Work on a branch and open a draft pull request. Never push to `main`, merge, tag or release.
- Every PR names its issue with `Closes #N`.
- Never commit private project names, internal tooling references or local paths. `bin/privacy-check.sh` runs in CI.

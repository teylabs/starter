# Starter

Guidance for contributors and coding agents working in this repository.

- Preserve the tenant, ownership, grant and stale-write regression tests.
- Focused checks explicitly opt into synthetic SQLite memory. Public requests must never seed fixtures, bypass auth, or use default credentials or keys.
- No external services, production data or provider side effects in tests.
- Fail closed, and restore tenant, permission and settings context in `finally` blocks.
- Keep capabilities, entitlements, membership and admission distinct.
- State missing Inertia, protocol, lifecycle or platform coverage explicitly rather than implying it.

## Gates

```sh
composer install --no-interaction
vendor/bin/phpunit --bootstrap vendor/autoload.php tests
npm ci && npm run typecheck && npm run build
```

## Conventions

- Styling is Tailwind utilities only, with tokens in `@theme`. No hand-written CSS rules, `<style>` blocks or appearance `style=` bindings; a genuine exception goes in the guard's allow-list with a reason.
- Commit as Jasper Tey <jasper@teylabs.com>. Commits and PRs carry no attribution lines or trailers.
- Work on a branch and open a draft pull request. Never push to `main`, merge, tag or release.
- Every PR names its issue with `Closes #N`.

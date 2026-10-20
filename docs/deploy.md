# Deploy

Laravel Cloud builds the app (`composer install --no-dev`, then `npm ci && npm run build`), then runs its deploy commands in order, and stops at the first that fails. Use this order:

```sh
set -e
php artisan standards:preflight --skip=install.tables
php artisan migrate --force
php artisan standards:preflight
php artisan concierge:owners --workspace=<key> --no-interaction
php artisan optimize
php artisan starter:smoke
```

- **`standards:preflight --skip=install.tables` gates the deploy**, after the asset build and with the database reachable, before anything migrates. tey/standards' blocking checks (the app key, debug off in production, the database config and the `install.*` group: every configured mail transport, disk, queue, cache and session driver, PHP extension and secret is actually installed, and the Vite manifest exists) exit 1 and stop the deploy. `install.tables` is skipped here because the tables it checks don't exist before the first migrate; the skip is printed and logged.
- **`migrate --force` comes next**, before anything caches. A cached config or route built before the schema is current can boot code that expects tables or columns that do not exist yet.
- **`standards:preflight`, in full, follows the migration**, so `install.tables` now checks that the session, cache, queue and failed-job tables the config uses exist, and a passing run clears the skip. Warning-level checks (mail sender, favicon and the like) are reported and logged as `PREFLIGHT SUMMARY` but never block.
- **`concierge:owners --workspace=<key>`** keeps the owner named in `CONCIERGE_OWNERS` on that workspace. It is safe on every deploy: it never removes a user or an ownership, and it refuses (non-zero) a missing or unknown workspace, several owners for one workspace, or a different existing owner. It never creates the workspace; your app does. Preview with `--dry-run`, or read `--json`. A new owner gets a set-password link through the password broker, which needs mail and a password-reset route: neither is in the template yet (#24), so until then create the owner with your own reviewed process.
- **`optimize`**, and any other cache commands (`config:cache`, `route:cache`, `view:cache`, `event:cache`), come after migrations.
- **`starter:smoke`** runs last, against the deployed, cached app. The command is tracked in #32 and does not exist yet; leave the line out until it ships.

## TLS

TLS ends at the platform's proxy. `bootstrap/app.php` trusts the forwarded scheme from any proxy, so URLs and secure cookies follow `https`; the app must only be reachable through that proxy. The host is pinned to `APP_URL`'s (`trustHosts`, anchored and escaped), so a forged `Host` or `X-Forwarded-Host` gets a 400 and can never become the host of a reset or invitation link. Set `APP_URL` to the domain users reach; any other domain, the platform's default one included, is refused outside the local environment. CI proves it behind a local TLS proxy (`bin/tls-proxy.mjs`).

## CI

The **Deploy order (PostgreSQL)** job runs this order on every pull request: a `--no-dev` install in production mode with throwaway secrets, the asset build, the gate preflight, `migrate --force` against a fresh PostgreSQL 17, the full preflight and `optimize`. It leaves out `concierge:owners`, which needs an existing workspace, and `starter:smoke`, which doesn't exist yet.

## Environment

Set these per environment (see `.env.example`):

- `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`: the OAuth signing keys for MCP clients, as PEM with real newlines (`php artisan passport:keys` makes a pair). The preflight's `install.secrets` check fails without them.
- `CONCIERGE_OWNERS`: the email of the workspace owner `concierge:owners` keeps. One email for one workspace; leave it empty locally, where `composer setup` creates the owner.
- `NIGHTWATCH_TOKEN`: Laravel Nightwatch. Requests are sampled at most 0.1; exceptions, commands and scheduled tasks always (`config/nightwatch.php`).
- The bucket variables Laravel Cloud injects for an attached bucket (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT`) feed the private `s3` disk, where media is stored.

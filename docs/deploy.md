# Deploy

Laravel Cloud runs the app's deploy commands in order, and stops at the first that fails. Use this order:

```sh
set -e
php artisan migrate --force
php artisan concierge:owners --workspace=<key> --no-interaction
php artisan optimize
php artisan starter:smoke
```

- **`migrate --force` comes first**, before anything caches. A cached config or route built before the schema is current can boot code that expects tables or columns that do not exist yet.
- **`concierge:owners --workspace=<key>`** keeps the owner named in `CONCIERGE_OWNERS` on that workspace. It is safe on every deploy: it never removes a user or an ownership, and it refuses (non-zero) a missing or unknown workspace, several owners for one workspace, or a different existing owner. It never creates the workspace; your app does. Preview with `--dry-run`, or read `--json`. A new owner gets a set-password link through the password broker, which needs mail and a password-reset route: neither is in the template yet (#24), so until then create the owner with your own reviewed process.
- **`optimize`**, and any other cache commands (`config:cache`, `route:cache`, `view:cache`, `event:cache`), come after migrations.
- **`starter:smoke`** runs last, against the deployed, cached app. The command is tracked in #32 and does not exist yet; leave the line out until it ships.

## TLS

TLS ends at the platform's proxy. `bootstrap/app.php` trusts the forwarded scheme from any proxy, so URLs and secure cookies follow `https`; the app must only be reachable through that proxy. The host is pinned to `APP_URL`'s (`trustHosts`, anchored and escaped), so a forged `Host` or `X-Forwarded-Host` gets a 400 and can never become the host of a reset or invitation link. Set `APP_URL` to the domain users reach; any other domain, the platform's default one included, is refused outside the local environment. CI proves it behind a local TLS proxy (`bin/tls-proxy.mjs`).

## Environment

Set these per environment (see `.env.example`):

- `CONCIERGE_OWNERS`: the email of the workspace owner `concierge:owners` keeps. One email for one workspace; leave it empty locally, where `composer setup` creates the owner.
- `NIGHTWATCH_TOKEN`: Laravel Nightwatch. Requests are sampled at most 0.1; exceptions, commands and scheduled tasks always (`config/nightwatch.php`).
- The bucket variables Laravel Cloud injects for an attached bucket (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT`) feed the private `s3` disk, where media is stored.

# Deploy

Laravel Cloud builds the app (`composer install --no-dev`, then `npm ci && npm run build`), then runs its deploy commands in order, and stops at the first that fails. A deploy is two lines:

```sh
php artisan migrate --force
php artisan optimize
```

That is all. Roles and permissions follow the migration by themselves, so there is no sync line to remember and nothing to run per release.

- **`php artisan migrate --force`** migrates, and when the migrations end (or when there was nothing to migrate) the kit syncs the roles, then the permissions the features declare. The sync is gated by a fingerprint of the catalog (the configured roles and every registered permission with its default roles) kept in `kit_meta`: an unchanged catalog writes nothing, a changed one (a release that adds a permission and no migration) is picked up by the deploy's own migrate, and a fresh database always syncs. A rollback does not sync, and a failing sync fails the migrate, so the deploy stops. Permissions are created once with their default roles: a default someone removed is not granted again.
- **`php artisan optimize`** comes after the migration and builds every cache: config, routes, views, events, glhd/gretel's breadcrumbs, laravel-data's and the discovered structures, laravel-settings' and tey/mod's discovery. Each registers itself with `optimize` (and `optimize:clear`), so there are no cache lines of their own.
- **`/up` is the health check**, for monitoring only. Besides Laravel's own, it fails (500) on a missing `APP_KEY`, a missing table the kit needs (and `sessions` with `SESSION_DRIVER=database`) and, in production, `APP_DEBUG` on or unresolved brand placeholders. A pass is remembered for thirty seconds. Nothing deploys on it; a failing `/up` is an alert, not a gate.
- **`php artisan kit:preflight`** is optional. It is tey/standards' full preflight, with a non-zero exit on a misconfigured production, for a pipeline that wants a blocking gate (run it before or after the two lines). The standard deploy does not need it.
- **`concierge:owners --workspace=<key>`** keeps the owner named in `CONCIERGE_OWNERS` on that workspace, if the app uses it. It is safe on every deploy: it never removes a user or an ownership, and it refuses (non-zero) a missing or unknown workspace.

## Before the first deploy: rehearse it

The first deploy is where hand-set configuration meets the platform: a cache store with no table, a mailer whose package is not installed, a key pasted with the two characters backslash and n instead of newlines. Rehearse it locally against the values you will set:

1. Copy `.env.production.example` to a local file that is not committed (`.env.*` is ignored), and fill in every value as you would in the platform's dashboard. The file mirrors what Laravel Cloud injects: PostgreSQL, `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION` as `database` or `sync`, `MAIL_MAILER=resend` with `RESEND_KEY`, and the brand an app must fill.
2. Run `php artisan kit:preflight --env-file=path/to/that/file`. It runs the full production preflight in a child process that reads that file as its whole environment (your `.env` is not read, and neither is anything your shell has exported: it warns by name when your shell sets a variable the file also sets), and prints no value from it: it only says what is wrong, and which form the Passport keys arrived in. Migrate the database it points at first, or the table checks will say so.
3. Fix what it names, run it again, then deploy.

`php bin/rehearsal-env.php > rehearsal.env` builds a throwaway version of that file (a real `APP_KEY`, real RSA keys, a stand-in `RESEND_KEY`), which is what CI's **Production rehearsal** job uses.

## Provisioning checklist

Everything the platform does not inject is set by hand. Each line says how to check it; `php artisan kit:preflight --env-file=...` checks all of them.

| Setting | What it must be | How to verify |
|---|---|---|
| `APP_KEY` | `base64:` and 32 random bytes (`php artisan key:generate --show`) | preflight `install.secrets` |
| `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` | An RSA PEM pair (`php artisan passport:keys --force`, then copy the files' contents). Real newlines are best. If the dashboard cannot hold them, the kit also accepts the form with `\n` for each newline, or the whole PEM base64-encoded, and turns either into the PEM at boot. | preflight `install.secrets`, and the line `Passport keys: ... as pem / escaped-newlines / base64` (never the key) |
| `MAIL_MAILER=resend`, `RESEND_KEY` | The Resend API key. The starter requires `resend/resend-php`; any other mailer needs its own package, and preflight names it when it is missing. | preflight `install.mail`, `install.secrets` |
| `MEDIA_DISK=s3` and the `AWS_*` bucket variables | The one private bucket (Cloud injects them for an attached bucket) | preflight `install.filesystems` |
| `KIT_BRAND_*` and `APP_NAME` | Your own name, logomark, accent and the four mail addresses (updates@, support@, hello@, privacy@) | `/up` answers 200 (it refuses the template's stand-ins in production) |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | `database` for the first two (the tables are migrations); `sync`, or `database` with a worker | preflight `install.tables`, `install.drivers`. With Passport installed, `install.sessions` blocks the deploy on `cookie` or an unset `SESSION_DRIVER`: set `SESSION_DRIVER=database` explicitly, since OAuth consent outgrows a 4KB cookie |
| `CONCIERGE_OWNERS` | The workspace owner's email, if the app uses `concierge:owners` | `php artisan concierge:owners --workspace=<key>` |

## TLS

TLS ends at the platform's proxy. `bootstrap/app.php` trusts the forwarded scheme from any proxy, so URLs and secure cookies follow `https`; the app must only be reachable through that proxy. The host is pinned to `APP_URL`'s (`trustHosts`, anchored and escaped), so a forged `Host` or `X-Forwarded-Host` gets a 400 and can never become the host of a reset or invitation link. Set `APP_URL` to the domain users reach; any other domain, the platform's default one included, is refused outside the local environment. CI proves it behind a local TLS proxy (`bin/tls-proxy.mjs`).

## CI

The **Deploy order (PostgreSQL)** job runs the two lines on every pull request, once with `QUEUE_CONNECTION=sync` and once with `database`, both with `CACHE_STORE=database` and `SESSION_DRIVER=database` as a platform with a database attached sets them (the cache, session and queue tables are migrations, so the migration has to come before `optimize`: laravel-data keeps its structure cache in the cache store), on a fresh PostgreSQL 17 in production mode with throwaway secrets: `php artisan migrate --force && php artisan optimize`. It then asserts what the deploy did: the roles and the default grants exist, the kit's caches are built, the configured cache store takes a put, a get and a lock, and `/up` answers 200. The cache key prefix follows `APP_NAME` (set `CACHE_PREFIX` to pin it).

The **Production rehearsal** job goes one step further: it fills `.env.production.example` with throwaway values (`bin/rehearsal-env.php`), migrates PostgreSQL with them, and runs `kit:preflight --env-file=` with the production-only checks on, with `QUEUE_CONNECTION` as `sync` and `database` and the Passport keys as real newlines and as escaped newlines. A cache store with no table, a mailer without its package or a malformed key fails a pull request here instead of the first deploy.

## Avatars and media

Profile photos use spatie/laravel-medialibrary, wired by the kit with no app code, on the **private media disk**: `MEDIA_DISK=local` (storage/app/private) in development, and in production the one private bucket (`MEDIA_DISK=s3` plus the bucket's `AWS_*` variables; with Laravel Cloud, attach a bucket to the environment). Nothing is served from a public path and `storage:link` is not needed for avatars. The app serves them at `GET /avatars/{token}` (and `/avatars/{token}/2x`): public, but the token is a random 40-character string kept in the media item's custom properties, so the URL carries no user or media id and cannot be guessed; the response is streamed through Media Library from the disk with a year-long `Cache-Control` and an ETag, and a replaced or removed avatar's old token answers 404. The upload is validated (JPEG, PNG or WebP by content, 2048 KB by default, 16 megapixels at most), centre-cropped to a square, re-encoded without EXIF or GPS, and the 96px and 192px thumbnails are made at upload, not queued.

## Environment

Set these per environment (see `.env.example`):

- `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`: the OAuth signing keys for MCP clients, as PEM with real newlines (`php artisan passport:keys` makes a pair). The preflight's `install.secrets` check fails without them.
- `CONCIERGE_OWNERS`: the email of the workspace owner `concierge:owners` keeps. One email for one workspace; leave it empty locally, where `composer setup` creates the owner.
- `NIGHTWATCH_TOKEN`: Laravel Nightwatch. Requests are sampled at most 0.1; exceptions, commands and scheduled tasks always (`config/nightwatch.php`).
- The bucket variables Laravel Cloud injects for an attached bucket (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT`) feed the private `s3` disk, where media is stored.

# Lifecycle

This page describes what exists today.

## Today: clean-schema install

A fresh install starts from an empty database.

```sh
composer install --no-interaction --no-scripts
cp .env.example .env
php artisan key:generate
# configure the database in .env
php artisan migrate --no-interaction
npm ci
npm run build
```

Boot never migrates, seeds, creates accounts, verifies email, grants operator access or provisions workspaces. You run `php artisan migrate` yourself. Registration is closed and there are no default credentials, so a first account and workspace must be created by your own setup. Production first-operator and email-verification policy is not implemented. Do not use test principals as a bootstrap.

`.env.example` holds no key or credentials. Secure session cookies default on; a local HTTP-only host must choose its cookie policy explicitly.

### Schema

- Host migrations create `users` and `workspaces` first, then `notes`, `connections`, `entitlements`, `operators`, `complimentary_grants`, `settings_intents`, `note_intents` and `audit_events` after Concierge's tables.
- Permission and Settings tables come from the locked packages' published migrations.
- Passport's and Concierge's own migrations are loaded from the packages (`InstallProvider`) and run once. Package internals are not copied into host tables.
- Migration ids belong to Laravel's migration repository. Re-running `migrate` is a no-op.

### Generated files

Generated files are listed in [generation-manifest.json](generation-manifest.json). `composer types:generate` rewrites `resources/js/types.generated.d.ts`; review the diff before keeping changes.

## Not available yet

Planned and not shipped:

- v0.2: install with `laravel new --using=tey/starter` and upgrade with `starter:maintain`, using Mod's lifecycle and the starter blueprint.
- v0.3: `starter:adopt` for existing apps, with a feature catalog.

Until then there is no upgrade path, no data conversion or rollback, and existing users are not migrated. `tey/mod` is installed but no feature uses it. See [ROADMAP.md](../ROADMAP.md).

# Lifecycle

This page describes what exists today: a clean-schema install, and blueprint maintenance through `starter:maintain`.

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

## Blueprint maintenance with `starter:maintain`

`tey/starter-blueprint` 0.1 runs on released `tey/mod` 0.8 and adds the `starter:maintain {plan|apply|resume}` command. Its generated files are `app/Support/Starter/BlueprintPolicy.php` (feature `foundation`) and `app/Support/Starter/Defaults/BlueprintDefaults.php` (feature `defaults`, which requires `foundation`). The whole app is both features.

The starter binds the blueprint's `LifecycleHost` to `App\Support\StarterLifecycle` in `InstallProvider`:

- App id and frontend binding (`inertia-vue`) come from `config/lifecycle.php`.
- Seven named inputs are fingerprinted into every plan: `config/lifecycle.php` (policy), `app/Support/StarterLifecycle.php` (code), `composer.lock`, `config/foundation.php`, `app/Support/InstallProvider.php` (binding), `app/Support/lifecycle-operations.php` (operation check) and `config/lifecycle-executor.json` (executor provenance). Changing any of them makes a reviewed plan stale.
- `config/lifecycle-executor.json` records the Mod release that runs maintenance. A Mod upgrade refuses every plan until this file is reviewed and updated to match.
- The operation check in `app/Support/lifecycle-operations.php` is read-only and fails closed. It passes only when no tenant is current, the host and Concierge tables exist, `TenantBoundary` refuses an unknown workspace, every configured capability has a Pennant definition and the operator gate is defined. A false or throwing check leaves the installed version unchanged.

```sh
php artisan starter:maintain plan --features=foundation,defaults
php artisan starter:maintain apply --features=foundation,defaults --intent=<stable-intent> --expected-plan=<plan identity> --exclusive-checkout
# add features later: name exactly what is installed
php artisan starter:maintain plan --original-features=foundation --features=foundation,defaults
# retry an interrupted intent with the same arguments
php artisan starter:maintain resume --features=foundation,defaults --intent=<stable-intent> --exclusive-checkout
```

Mod's installed record lives in `.mod-maintenance/installed.json` and is written last. Behaviour proven by `tests/LifecycleTest.php`:

- First install has no baseline; an existing file at a generated path is refused as unowned.
- Re-running a completed intent is a no-op from the receipt, without rerunning the host check.
- An upgrade keeps host-owned code (for example `app/Domain`, `config/branding.php`) and replaces generated files.
- Any local edit to a generated file is a conflict: the upgrade refuses and keeps both the edit and the installed version. There is no force or merge.
- `--original` / `--original-features` must rebuild the installed artifact exactly (features and bindings included), or the command refuses before Mod runs. Removing a feature is refused.

There are no data migrations, rollback or orphan deletion. Upgrades only change the blueprint's generated files.

## Not available yet

- `laravel new --using=tey/starter`: the starter and `tey/starter-blueprint` are not yet served by Packagist. `composer.json` carries a temporary VCS repository for the blueprint until it is.
- The A to D release fixtures in the blueprint are synthetic regression evidence, not starter versions. No published starter version has yet been upgraded to the next one.
- v0.3: `starter:adopt` for existing apps, with a feature catalog.

`tests/LifecycleTest.php` runs in disposable checkouts holding copies of the starter's lifecycle inputs against synthetic SQLite memory. It is not proof of a production install, a running server or a real consumer upgrade. See [ROADMAP.md](../ROADMAP.md).

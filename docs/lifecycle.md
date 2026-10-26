# Lifecycle

## Install

`composer setup` takes a fresh clone to a working local sign-in: dependencies, `.env`, the key, `database/database.sqlite`, `migrate`, the frontend build and the first local owner and workspace (`php artisan starter:first-owner`, local environment only, through Concierge's tenant invitation and the kit's acceptance). The password is prompted for, or generated and printed once with `--no-interaction`; it is never stored in plain text and never defaulted.

Boot never migrates, seeds, creates accounts or provisions workspaces. Registration is closed. In production, `concierge:owners` keeps the owner (see [deploy.md](deploy.md)).

### Schema

- The host's own migrations create `users` and `media` (medialibrary).
- Each kit feature's migrations live in `database/migrations/kit/<feature>` (`0001_01_02_000NXX_*`) and load because the feature is adopted. Permission and Settings tables come from the locked packages' own migrations; Passport's and Concierge's migrations load from the packages.
- tey/kit 0.1.5 still orders core's migration before the workspaces table it references. PostgreSQL refuses that, so `App\Support\KitMigrator` runs core right after the workspaces table until the kit fixes its order.
- Re-running `migrate` is a no-op.

### Databases migrated by 0.2

0.2 recorded the template's own migration ids (`0001_01_01_000000_create_starter_identity_tables`, `create_starter_<feature>_tables`, or the older `create_starter_domain_tables`). The kit migrations create the same tables under new ids. Before the migrator reads what has run, `App\Support\KitMigrationRepository` records each kit migration whose tables an old id already created, in the latest batch, so `migrate` creates nothing twice and keeps the data. `tests/FeatureSeamsTest.php` migrates real 0.2.1 migrations (`tests/Fixtures/v0.2.1-migrations`) and the pre-split schema, then checks the schema, data and recorded ids. The old ids stay in the migrations table; rolling back across 0.3 is not supported.

## Kit features

`config/kit.php` adopts every feature (`features`). The kit's installed records (`.mod-maintenance/`) name the checkout's absolute path, so the template ships none and gitignores them. To let `kit:update` manage the files later, record them once in your own checkout, claiming each file after review:

```sh
php artisan kit:add core workspaces access settings session-ui mcp --claim=<path> ...      # preview
php artisan kit:add core workspaces access settings session-ui mcp --claim=<path> ... --apply --expected-plan=<plan>
```

then set `features` to `null` so the installed records decide. The preview lists every file it can claim; an edited file is a conflict and is never claimed. `tests/LifecycleTest.php` proves the add, the claim, the refusals and the fail-closed operation check in disposable checkouts.

Never edit a generated feature file (`database/migrations/kit/**`, the kit's pages): copy a page to your own name, or add a migration of your own. CI's parity job runs `kit:add` of every feature on a clean Laravel app and fails when the template's files differ.

## Not available yet

- `laravel new --using=tey/starter`: the template is not on Packagist yet.
- tey/mod 0.10: tey/kit 0.1.5 requires tey/mod ^0.9, so the template stays on Mod 0.9 until the kit moves.
- `kit:add --all`: the command takes the feature list; `kit:list --json` prints it.

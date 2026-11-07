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

`config/kit.php` adopts every catalog feature except the opt-in `notes`, `finalize` and `deliver` (`features`). The template ships tey/mod's installed records for them (`.mod-maintenance/installed/*.json`, relative to the checkout, so a clone carries them; the journals, lock and owner file stay local and gitignored). `php artisan kit:status --check` reads them and fails on an unmarked edit to a kit-owned file, and `php artisan kit:update plan --lineage=<feature>` / `apply` updates each feature in place. To add an opt-in feature, run `php artisan kit:add <feature>` and list it in `features`. tey/standards reads the same records, so a kit-owned file is the kit's, not the app's, to the frontend rules. `tests/LifecycleTest.php` proves the add, the claim, the refusals and the fail-closed operation check in disposable checkouts.

### Adding notes

New apps do not ship the kit's `notes` feature (the notes wall, its dashboard card, its MCP tools and the `kit_notes` table); apps that already have it keep it. To add it:

1. `php artisan kit:add notes` previews the plan and prints its identity; review it.
2. `php artisan kit:add notes --apply --expected-plan=<plan>` writes the pages, widget and migration and records the lineage.
3. Add `'notes'` to `features` and `'workspace.notes' => true` to `capabilities` in `config/kit.php`, then `php artisan migrate`.
4. Add the notes rows back to `app/Support/McpCapabilities.php` (the notes tools, the `session_notes` test and the page and controller inventory; see this file's history) and run `php bin/mcp-capabilities.php`.

The notes tests stay in the template and switch on with the feature: `tests/NotesTest.php` and `tests/Browser/Notes.php` skip until `notes` is adopted, and the shared pins (`notesAdopted()` in `tests/Pest.php`: dashboard widgets, navigation, breadcrumbs, migrations, MCP scopes and tools, schema) expect the notes contributions once it is.

Never edit a generated feature file (`database/migrations/kit/**`, the kit's pages): copy a page to your own name, or add a migration of your own. CI's parity job runs `kit:add` of the template's features on a clean Laravel app and fails when the template's files differ.

## Not available yet

- `laravel new --using=tey/starter`: the template is not on Packagist yet.
- `kit:add --all`: the command takes the feature list; `kit:list --json` prints it.

# Lifecycle

## Install

`composer setup` takes a fresh clone to a working local sign-in: dependencies, `.env`, the key, `database/database.sqlite`, `migrate`, the frontend build and the first local owner and workspace (`php artisan starter:first-owner`, local environment only, through Concierge's tenant invitation and the kit's acceptance). The password is prompted for, or generated and printed once with `--no-interaction`; it is never stored in plain text and never defaulted.

The tests never use that local database. They run on PostgreSQL (`.env.testing`, database `starter_testing` on Herd's server by default), in parallel with a database per worker; see [testing](testing.md#the-test-database).

Boot never migrates, seeds, creates accounts or provisions workspaces. Registration is closed. In production, `concierge:owners` keeps the owner (see [deploy.md](deploy.md)).

### Schema

- The host's own migrations create `users` and `media` (medialibrary).
- Each adopted kit feature's migrations load from the package (`vendor/tey/kit/database/migrations/<feature>`, since kit 0.18), under the names the template's copies had, so a database migrated before keeps its rows and runs only the newer ones (the 0.18 renames that drop the `kit_` prefix from the infrastructure tables, and 0.24's `signed_in_devices`). A copy under `database/migrations/kit/<feature>` would win by name; the template ships none. Permission and Settings tables come from the locked packages' own migrations; Passport's and Concierge's migrations load from the packages.
- Re-running `migrate` is a no-op.

### Databases migrated by 0.2

0.2 recorded the template's own migration ids (`0001_01_01_000000_create_starter_identity_tables`, `create_starter_<feature>_tables`, or the older `create_starter_domain_tables`). The kit migrations create the same tables under new ids. Before the migrator reads what has run, `App\Support\KitMigrationRepository` records each kit migration whose tables an old id already created, in the latest batch, so `migrate` creates nothing twice and keeps the data. `tests/FeatureSeamsTest.php` migrates real 0.2.1 migrations (`tests/Fixtures/v0.2.1-migrations`) and the pre-split schema, then checks the schema, data and recorded ids. The old ids stay in the migrations table; rolling back across 0.3 is not supported.

## Kit features

`config/kit.php` adopts every catalog feature except the opt-in `notes`, `finalize` and `deliver` (`features`); the kit registers exactly the features it lists. Since kit 0.18 there is no install or update tooling and no installed record: a feature's pages are written once by `php artisan kit:scaffold <feature>` and are the app's own from then on, and the shared components (`@kit/ui`, `@/components/Kit`) load from `vendor/tey/kit` through the Vite and TypeScript aliases. The template keeps every scaffolded file at the bytes the locked kit writes, so an app that forks it starts from the kit's pages. `php artisan kit:scaffold <feature> --diff` shows a kit release's changes to them and writes nothing; `--force=<path>` takes the kit's file. tey/standards counts a scaffolded file as the kit's, not the app's, to the frontend rules while its bytes equal the kit's stub. `tests/LifecycleTest.php` proves the template's files equal `kit:scaffold` of every adopted feature, that scaffolding an empty checkout writes them and a second run writes nothing, and that an edited file is reported and kept until forced.

### Adding notes

New apps do not ship the kit's `notes` feature (the notes wall, its dashboard card, its MCP tools and the `kit_notes` table); apps that already have it keep it. To add it:

1. Add `'notes'` to `features` and `'workspace.notes' => true` to `capabilities` in `config/kit.php`.
2. `php artisan kit:scaffold notes` writes its pages and widget; `php artisan migrate` creates its table from the package.
3. Optionally make its table the app's: `php artisan kit:scaffold notes --adopt` (tey/kit docs/domain-tables.md).
4. Add the notes rows back to `app/Support/McpCapabilities.php` (the notes tools, the `session_notes` test and the page and controller inventory; see this file's history) and run `php bin/mcp-capabilities.php`.

The notes tests stay in the template and switch on with the feature: `tests/NotesTest.php` and `tests/Browser/Notes.php` skip until `notes` is adopted, and the shared pins (`notesAdopted()` in `tests/Pest.php`: dashboard widgets, navigation, breadcrumbs, migrations, MCP scopes and tools, schema) expect the notes contributions once it is.

In the template, never edit a scaffolded kit file (the kit's pages, `resources/css/kit.css`): copy a page to your own name, or add a migration of your own. CI's parity job (`sh bin/kit-parity.sh`) runs `kit:scaffold --diff` of the template's features and fails when a file differs, unless it is marked `kit: tweaked <reason>`. An app made from the template owns its copies and may edit them.

## Not available yet

- `laravel new --using=tey/starter`: the template is not on Packagist yet.

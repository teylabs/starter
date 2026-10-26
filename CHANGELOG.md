# Changelog

## Unreleased

### Changed

- The starter pins `tey/standards` ^0.10 (was ^0.7). The non-blocking preflight level is `advisory` (JSON `level`, was `warning`), a non-interactive preflight prints only failed and advisory rows plus a summary, and `install.sessions` blocks a deployed Passport app on a cookie or unset `SESSION_DRIVER`. `.env.example`, `.env.production.example` and the production rehearsal CI jobs already set `SESSION_DRIVER=database`, and `docs/deploy.md` now names the check.
- The starter pins `tey/kit` ^0.3.2. The kit-owned `DataTable.vue` is resynced from `kit:add`: a table's row count shows on a single page and only the page links wait for a second one. The dashboard greeting's workspace-timezone fix ships in the package, so there is no file to resync for it.
- The starter pins the released kit: `tey/kit` ^0.3.1 (with tey/mod 0.11.1, recorded in `config/kit-executor.json`, and tey/concierge ^0.8.2) instead of a kit main commit. The kit-owned files are resynced from `kit:add`: LockedForm, EditOnly and useLocked, the native-fieldset FormFieldset, SplitPane and the Pane parts, BulkDrop, and the updated ActionBar and inputs. The feature graph guard lets a feature use the kit's library code outside Features (`Tey\Kit\Mail\…`, `Tey\Kit\Brand`), which the invitation email needs for `FluentMailMessage`. The cursor guard lets a LockedForm's read-only Select trigger use the default cursor.
- The starter follows kit main (5619adf): tey/kit is required as `dev-main` at that commit instead of a local path repository, with tey/mod 0.11 and tey/concierge 0.8.2. The kit-owned files are regenerated from `kit:add` of every feature, which now includes `shell` (in `config/kit.php`). That brings the page-action, menu and InputSelect components, the shell's GuestShell, SubNav and Wordmark partials, `resources/js/types/kit-shell.d.ts` and the entitlements migration. Vite and `tsconfig.json` alias `@kit/shell` to the shell's Partials barrel, `tsconfig.json` adds `DOM.Iterable`, and reka-ui is 2.11.0, the version InputSelect is verified on. `config/kit-executor.json` records Mod v0.11.0. The tests follow: the feature graph has Shell, seven lineages, the entitlements schema and migration, the `kitUi` and `environment` shared props, and the mark checked through the Wordmark partial.
- The kit's form inputs take their 0.3 names: `NumberInput` is `InputNumber` and `Checkbox` is `InputCheckbox` (`resources/js/components/Kit/Form/`, exported from `@kit/ui`). The Login and Connections pages use `InputCheckbox`. The old names stay as deprecated aliases until kit 0.4, and `Select` is deprecated in favour of `InputSelect`. Upgrading: rename your own `NumberInput`, `NumberInputProps` and `Checkbox` imports before kit 0.4.

### Fixed

- An empty `CI=` no longer bypasses the local TIA guard: `CI`, `PARATEST` and `PEST_TIA` count only when non-empty.
- TIA's base branch is the host's: `TIA_DEFAULT_BRANCH` (default `main`) instead of a hard-coded `main`.
- The TIA watch map re-runs the source-reading arch tests on `app/**` and `.github/workflows/**`, and the application-free checks on `tests/**`. Factories and seeders were already covered by `database/**`.
- The browser test reads the expected fonts from the active kit set, so `composer test:browser` passes whichever `KIT_FONTS` an app uses. CI also runs it with `instrument-inter` (#54)

## v0.3.2

### Added

- Deploys run the tey/standards 0.7 preflight: `standards:preflight --skip=install.tables` after the build and before `migrate --force`, then the full preflight after it. CI runs the same order on a `--no-dev` production install against PostgreSQL (#47)
- One-click local sign-in: `.env.example` turns on Concierge's `local_login` (`CONCIERGE_LOCAL_LOGIN=true`, signing in as `CONCIERGE_LOCAL_LOGIN_EMAIL`, by default `owner@example.com`), and the sign-in page shows "Sign in as …" whenever Concierge's `LocalLogin::isAvailable()` says so. Concierge keeps it to the local environment (#49)

### Changed

- Fonts come from tey/kit 0.1.5's font sets: `<x-kit::fonts />` loads the set `KIT_FONTS` names (`instrument` by default, `instrument-inter` or `hanken`), and the Tailwind theme no longer overrides the kit's `--font-*` variables. The page title takes the set's display weight (#51)
- The sign-in card heading is Laravel's Vue starter kit's: `text-xl font-medium` in the text face, over a small muted description. `npm test` guards the class (#51)

### Fixed

- A `--no-dev` install boots: the dev-only TypeScript transformer's provider is registered only when it is installed (#47)
- The sync queue keeps no failed-job table, so `failed` is now the `null` driver instead of a `failed_jobs` table that never existed (#47)

## v0.3.1

### Fixed

- The template is Pint-clean, and CI runs `pint --test`. `pint.json` leaves the kit's generated migrations alone (#45)
- Apps can run `phpunit` and `composer fixture:check` on PostgreSQL: `TEST_DB_CONNECTION=pgsql` with the `DB_*` variables, documented with a local Herd example. Each run prints the database in use, and refuses `pgsql` without `DB_DATABASE` set for the run (#46)

## v0.3.0

The template is now built on tey/kit, and a fresh clone reaches a designed sign-in with one command.

### Changed

- The template is built on tey/kit 0.1 with every feature added. `app/Starter`, `routes/starter` and `resources/js/starter` give way to the kit library and to the migrations and pages that `kit:add` writes. CI checks these against `kit:add` on a clean Laravel app. `tey/starter-blueprint` and `starter:maintain` are removed in favour of `kit:add` and `kit:update`. Databases migrated by 0.2 keep their schema and data (#30, #15)
- `composer setup` takes a fresh clone to a working local sign-in. `starter:first-owner` creates the first local owner, locally only and with no default password (#30)
- The sign-in page and app shell are designed. They are built from Laravel's MIT Vue starter kit, use Tailwind CSS 4 and Instrument fonts, and the nav lists your workspaces (#30, #36)
- New apps start conformant:
  - an MCP capabilities inventory, validated against a vendored schema;
  - Nightwatch sampling;
  - a private `s3` disk with medialibrary on it;
  - `cache.serializable_classes` off;
  - UTC;
  - the Cloud deploy order in `docs/deploy.md`, with `concierge:owners` (#30, #34, #38, #40, #42)
- The suites also run on PostgreSQL in CI, and a Pest browser test signs in on a fresh clone over HTTPS (#30)

### Security

- Trusted hosts are pinned to `APP_URL`, so a forged forwarded host can't reach reset or invite links (#35)

### Known gaps

- The template runs Mod 0.9 until tey/kit moves to Mod 0.10.
- Password reset and owner set-password links need mail (#24).

## v0.2.1

#### Fixed

- A fresh install now migrates with SQLite out of the box: `.env.example` no longer sets an empty `DB_DATABASE`, which overrode the default `database/database.sqlite` path (#25)

## v0.2.0

### Installs and upgrades from released packages

The first tagged release. The starter now builds entirely on released packages from Packagist (Mod 0.8, starter-blueprint 0.1 and Concierge 0.7.2), and `starter:maintain` plans, applies and resumes blueprint upgrades without overwriting your own files.

```sh
composer create-project tey/starter app
php artisan starter:maintain plan --target=<release> --features=foundation,defaults
```

### Added

- `starter:maintain` lifecycle through a fail-closed `LifecycleHost`, with tests for install, no-op re-run, upgrades that keep host edits, conflict refusal and stale-plan refusal (#1, #21)
- `create-project` scripts that copy `.env.example` and generate an application key; nothing is migrated or seeded (#22)
- CI for PHPUnit, focused tenant checks, type checks, the client build and a privacy check (#5, #19)
- `llms.txt` and agent docs for architecture, operations, transports, lifecycle and testing (#3, #20)
- MIT license (#22)

### Changed

- Requires `tey/mod` ^0.8, `tey/starter-blueprint` ^0.1 and `tey/concierge` 0.7.2 (#21)

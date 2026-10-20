# Changelog

## Unreleased

#### Changed

- The template is built on tey/kit 0.1 with every feature added: `app/Starter`, `routes/starter` and `resources/js/starter` give way to the kit library and the migrations and pages `kit:add` writes, which CI checks against `kit:add` on a clean Laravel app. Generated types move to `Tey.Kit.Features.*`. `tey/starter-blueprint` and `starter:maintain` are removed in favour of `kit:add` and `kit:update`; Mod 0.9 and Concierge ^0.7.4 come with it. Databases migrated by 0.2 keep their schema and data (#30)
- `composer setup` takes a fresh clone to a working local sign-in, with the first local owner made by `starter:first-owner` (#30)
- A designed sign-in page and app shell, from Laravel's MIT Vue starter kit and Tailwind CSS 4 (#30)
- New apps start conformant: `docs/mcp-capabilities.json`, Nightwatch sampling, a private `s3` disk with medialibrary on it, `cache.serializable_classes` off, UTC, and the Cloud deploy order in `docs/deploy.md` (#30)
- The suites also run on PostgreSQL in CI, and a Pest browser test signs in on a fresh clone (#30)
- Each starter feature now plugs into the shared seams through a `StarterRegistry`: route files under `routes/starter/`, MCP tools, workspace navigation, token scopes, capabilities and error codes. Behaviour, routes and schema are unchanged (#15)
- The single `create_starter_domain_tables` migration is split into one migration per feature. Fresh installs get the same schema; databases that already ran the old migration skip the new ones (#15)
- `User` takes workspace membership and roles from a `HasWorkspaces` trait (#15)
- Starter code moves into feature namespaces under `app/Starter/<Feature>` (Core, Workspaces, Access, Settings, Mcp, Notes, SessionUi), and its pages into `resources/js/starter/<feature>`. A test enforces that each feature uses only the features it requires. Generated TypeScript types are now namespaced by feature, for example `App.Starter.Notes.Data.NoteRecord` (#15)

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

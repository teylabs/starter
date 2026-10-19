# Changelog

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

# Changelog

## Unreleased

### Added

- **One command to a running local app, on Herd.** `composer setup` now uses the local PostgreSQL when it answers (Herd's), creating the database and the row-level-security runtime role, writes Passport's keys, sends mail to Herd's mail catcher or the log, links and secures the Herd site, and ends with what is left for a person; it is safe to re-run and changes nothing the second time. `composer dev` runs the scheduler and Vite. CI runs setup twice on a fresh clone on PostgreSQL.
- **Settings → Setup (tey/kit 0.51).** Each workspace's setup in one place: mail, media, row-level security, the queue and Telescope, each ok, n/a locally or with its fix, plus the steps only a person can do; operators see the app's own rows. MCP reads it as `workspace_setup_read` (settings.write). A fresh local install shows nothing red.
- **Telescope, for operators only.** laravel/telescope with a gate on `Operators::allows()` (never an email list), the noisy watchers off, cache and Telescope SQL dropped, and in production only exceptions, 4xx/5xx requests, failed jobs, scheduled tasks, slow queries, warnings and monitored tags kept (`TELESCOPE_LOG_EVERYTHING=true` keeps all). Entries are pruned after 48 hours, daily.
- **Amp reaches Laravel Boost.** `.amp/settings.json` registers Boost's MCP server (`php artisan boost:mcp`) for Amp, which reads it instead of `.mcp.json`. An orb asks once to allow it.

### Changed

- **tey/kit 0.51 and tey/concierge 0.12: plain table names.** Concierge's tables become memberships, membership_scopes, ownerships, invitations, account_statuses and owner_invitations; connections becomes mcp_connections and entitlements workspace_entitlements. The packages' own migrations rename them, rows and keys intact. The kit's re-pinned pages are re-taken (heavier auth headings, designed 404, search, no-workspace, security, invitation and People states), and the guest card heading follows them. The build needs `micromark`, `micromark-extension-gfm` and `dompurify` (added).
- **Lazy loading throws outside production.** `Model::preventLazyLoading()` finds an N+1 in development and the tests; Concierge 0.12.1 eager-loads the memberships it transfers.

### Fixed

- **`composer setup` runs on a fresh orb.** `.agents/setup` gives the orb's `root` role CREATEROLE (still not a superuser), so setup can create the row-level-security runtime role, as Herd's superuser already can.
- **Nightwatch no longer bills for the cache's own SQL.** Queries on the `cache`, `cache_locks` and `telescope_*` tables are dropped and cache events are ignored by default (`NIGHTWATCH_IGNORE_CACHE_EVENTS=false` to keep them); other queries, jobs, requests and exceptions are kept.
- **Queues are set correctly from day one.** `DB_QUEUE_RETRY_AFTER` defaults to 180 (was 90, too close to a job's runtime); failed jobs are pruned after 7 days and batches after 2, daily; `docs/deploy.md` has a Queues section with the one worker command (`--timeout=85 --tries=3 --backoff=10,60,300 --max-jobs=500 --max-time=3600 --memory`), Cloud's visibility and shutdown timeouts, and the iterating and production profiles.
- **`.agents/setup` runs on an orb that already lists the PHP source under another keyring.** apt refused two Signed-By values for one source; setup now drops the other entries for that repository before writing its own.
- **Media on Laravel Cloud needs no `MEDIA_DISK`.** With it unset, the `media` disk scopes over the attached default bucket from Cloud's disk config (else the first bucket); `MEDIA_DISK` still wins.
- **R2 uploads and reads work on AWS SDK 3.337+.** Every R2 disk, including one Cloud adds at boot, checksums requests and validates responses only when required.
- **Security updates:** symfony/http-kernel, mailer, routing and serializer 8.1.12, web-auth/webauthn-lib 5.3.17 (13 advisories).

### Changed

- **The default font set is `instrument-inter`.** `.env.example` sets `KIT_FONTS=instrument-inter`: Inter for text, Instrument Sans 600 for headings and IBM Plex Mono, all OFL and loaded from fonts.bunny.net (or self-hosted with `KIT_FONTS_SOURCE=self`). Apps that already set `KIT_FONTS` don't change.
- **laravel/boost ^2.10.4, with Amp as an agent.** `boost.json` lists `amp`, `composer update` runs `boost:update` afterwards, and the guidelines and skills are refreshed with `boost:update --discover`.

### Changed

- **Cloudflare R2 is the standard for production media.** `docs/deploy.md` and `.env.example` say production media lives in a private R2 bucket per app, never on the server's disk, and give the R2 variables. The config already defaults the `s3` disk to R2's region `auto` and path-style endpoint, so nothing else changes.

## v0.6.6

### Changed

- **tey/kit ^0.49.6.** Tables scroll with the page, the header sticking under the app bar, and a wide table scrolls sideways only while it overflows (v0.49.5). On phones the dashboard shows its aside before the feed, and `Registry::dashboardActions()` gives the dashboard heading its actions (v0.49.6). The dashboard page and WidgetGrid are re-taken from `kit:scaffold workspace-ui`, and the types are regenerated for the dashboard's `actions`.

## v0.6.5

### Changed

- **tey/kit ^0.49.3 and tey/standards ^0.12.11.** Kit caps People's identity cell at 288px from `sm`, so the table no longer scrolls inside its card at 1280, and adds `Registry::moduleVariant()` for a top-level module drawn as pills (NavGroup gains `variant`; run `php artisan types:generate`). Standards' `media.disk` rule now checks the scoped-over-`env('MEDIA_DISK')` shape the starter uses, and `ui-page.page-tabs` names `Registry::moduleVariant()`; the regenerated agent guidance and skills are included.

## v0.6.4

### Fixed

- **`MEDIA_DISK` names the bucket's disk, so media works on Laravel Cloud** (#155). v0.6.3 scoped the `media` disk over `s3`, but Laravel Cloud injects an attached bucket as the disk name chosen when attaching it (`LARAVEL_CLOUD_DISK_CONFIG`), and doesn't fill the `s3` disk, so `s3` and anything scoped over it had a null bucket. Any `MEDIA_DISK` other than `local` now scopes `media/` over the disk it names: set it to the Cloud disk name (`MEDIA_DISK=s3` with `AWS_*` still works elsewhere). `docs/deploy.md` and `.env.example` say so.

## v0.6.3

### Fixed

- **The `media` disk works on Laravel Cloud** (#151). Cloud injects an attached bucket into the `s3` disk only, so a `media` disk copied from `s3` when the config loaded had a null bucket. With `MEDIA_DISK=s3` the `media` disk is now `scoped` over `s3` with prefix `media`, so it uses the bucket and credentials `s3` holds when it is built. Adds `league/flysystem-path-prefixing`, which Laravel's scoped driver needs. `tests/StorageTest.php` sets a bucket on `s3` at runtime and checks the media disk's URLs use it.

## v0.6.2

### Changed

- **The bar's search field takes the bar's ink** (#147). `config/brand.php` sets `'header' => 'ink'`, the one brand key the template sets, because its bar is near-black in light and dark. The kit then prints the bar's own colours for what sits on it. In light mode the search field was the card's white on the bar (19.9:1), the brightest thing on every signed-in page; it is now #27272a (1.34:1 against the bar) with a 4.12:1 edge and a 5.81:1 placeholder, and on a phone the search button is the same wash instead of a light chip. The current page's underline stays #fafafa, the bar's focus outline is #d4d4d8 (13.46:1 on the bar, 10.08:1 on the field), and primary buttons and the page's own focus ring are unchanged. The `--shell-bar` tokens stay in `resources/css/app.css`: they paint the bar, and the kit colours what is on it. An app with a light bar removes the key and sets `--shell-bar` to match.
- **tey/kit is upgraded to v0.49.2** (#147; from v0.49.1; `composer require tey/kit:^0.49.2`, constraint `^0.49.2`). Only tey/kit moved, and no scaffolded file changed (`sh bin/kit-parity.sh`: 71 files equal). 0.49.2 is what makes the ink header usable on the neutral accent: before it, the bar's marker and focus ring were printed as #52525b, 2.57:1 on the bar.

## v0.6.1

### Changed

- **tey/kit is upgraded to v0.49.1** (#144; from v0.48.0; `composer require tey/kit:^0.49.1`, constraint `^0.49.1`). Only tey/kit moved. The steps the kit lists for 0.48.1 to 0.49.1 that apply to the template's features:
  - **The scaffolded files.** Three changed files are taken with `kit:scaffold <feature> --force=<path>` (all were unedited): `resources/css/kit.css` (core), `resources/js/pages/Auth/Login/Page.vue` (session-ui) and `resources/js/pages/Workspace/Settings/People/Page.vue` (workspace-ui).
  - **0.49.1: the field edge and dark fields.** The template sets no `--input` of its own, so it takes the kit's new defaults: `--input` is #8f8f8c in light and #686866 in dark, and a dark field fills with the card (#171717) instead of a wash of `--input`.
  - **0.49.1: the sign-in page's reset link comes from its view model.** The kit's Login page draws "Forgot your password?" from the `forgot` prop. The template builds that view model itself (`AppServiceProvider`, for the local sign-in offer), so it now passes `forgot` while Fortify's `resetPasswords` feature is on, as the kit's own `loginView` does; without it the link would have gone. `resources/js/types.generated.d.ts` is regenerated (`composer types:generate`), and `tests/PasswordResetTest.php` asserts the prop instead of reading the page's source.
  - **0.49.1: People on a phone.** A long email no longer hides a member's role and join date, and a date never wraps. At 1280 a very long email still widens the table, which scrolls inside its card; that is the kit's to fix.
  - **Checks.** `sh bin/kit-parity.sh` reports the template's 71 kit files equal to `kit:scaffold`, with no tweaks.
  - **Not taken:** `'header' => 'ink'` (0.49.1 fixes its primary buttons), because on the neutral accent the bar block still sets the bar's ring and marker to #52525b: the current page's underline and the search field's focus outline come out at 2.57:1 on the bar (#142, waiting on the kit). The 0.48.2 migration-order notes (the template has no stamped copies of the workspaces baselines and no adopt migration), `Composer`'s `rows` and the sibling-pill rule (0.49.0: the template has no Composer and no module pills).

## v0.6.0

### Added

- **Password reset by emailed link is on** (#137). `config/fortify.php` lists `Features::resetPasswords()`, so "Forgot your password?" on the sign-in page, which the kit draws whatever the feature list says, leads to the kit's Auth/ForgotPassword page instead of a 404, and the emailed link opens Auth/ResetPassword. The answer never tells whether an address has an account. The `password_reset_tokens` table already shipped; the app's mailer must be able to send the link (locally it is written to the log). `tests/PasswordResetTest.php` covers the pages, the neutral answer, the single-use link and the password policy.
- **`/terms` and `/privacy` answer with stand-in pages** (#137). Every footer links to them (the brand's `terms_url` and `privacy_url` defaults) and both were a 404 in a new app. `routes/web.php` serves `resources/legal/terms.md` and `resources/legal/privacy.md` through the kit's public Markdown page; each says to replace it before deploying and how. `kit:preflight` still refuses the stand-in URLs in production, so an app serves its own documents at its own URLs and removes these.

### Changed

- **Guest and focus pages sit on the page ground, on the kit's card** (#137). `GuestLayout` and `FocusLayout` drew a `bg-muted/40` ground under a card of their own recipe (`rounded-lg`, `shadow-xs`, a ring); in dark mode that card was 1.01:1 against its ground. Both now use `bg-background` and the kit's `Surface`: the radius, border and lifted shadow of every card after sign-in (white on `#fafaf9` in light, `#171717` on `#0a0a0a` in dark), and what is inside knows it sits on a card.
- **The focus header shows the mark alone on a phone** (#137). Under 640px the product's name was cut to "Work…" beside the account cluster. With a logomark, the name is now kept for screen readers only at that width; an app with no logomark keeps the name.
- **tey/kit is upgraded to v0.48.0** (#136; from v0.25.0; `composer require tey/kit:^0.48.0`, constraint `^0.48.0`). Only tey/kit moved; spatie/laravel-tags 4.12 arrives with it (and its eloquent-sortable and laravel-translatable), which 0.39 requires. Every upgrade step the kit lists for 0.25.1 to 0.48 that applies to the starter's features was taken.
  - **0.26 to 0.48: the scaffolded files.** Each feature's changed files are taken with `kit:scaffold <feature> --force=<path>` (all 24 were unedited): `resources/css/kit.css` (the ten tones, `--shell-top` scroll margins, the avatar ring, the card and field tokens, the elevation scale and the near-white ground), the shell's Layout, GuestShell, FocusShell, ShellDrawer, SubNav, UserAvatar and UserMenu, the Login, Register, ResetPassword and Invitation pages (a real `autocomplete="username"` input), the Welcome photo and Profile pages (FileInput's dropzone), the dashboard's WidgetGrid and YouWidget, the section landing, People with Invite and Change role (InputSelect), and the MCP Connections and Authorize pages ("Agent Integration"). The generated types follow (`composer types:generate`).
  - **0.47 and 0.48: the template takes the kit's theme.** `resources/css/app.css` no longer restates the semantic colour tokens (`--background` to `--ring`, light and dark): they were copies of the kit's old defaults, and a `:root` copy outranks the kit's `:where(:root)`, so it would have kept the white ground, same-as-page dark cards and a 1.3:1 field edge. A new app now gets the kit's near-white ground (`#fafaf9`) with lifted white cards, dark cards lighter than the page, the elevation scale and 3:1 field edges, and overrides a token in its own `:root` or `.dark`. Dark `--destructive` becomes the kit's `hsl(0 72% 51%)` (was `hsl(0 84% 60%)`). The starter's own tokens stay: the charts, the sidebar, the radius, the shell bar and the layers.
  - **0.33.7: three npm packages.** `micromark`, `micromark-extension-gfm` and `dompurify`, which core's TextMarkdown imports, are in `package.json`.
  - **0.29: `applyFormDefaults()`.** `resources/js/app.ts` calls it once, so a saved Inertia `<Form>` is no longer unsaved and the leave prompt stops.
  - **0.36 and 0.37.4: one sign-in rule.** `App\Support\LoginResponse` and its binding are gone: the kit's `SignedInResponse` sends a password, two-factor or passkey sign-in straight to `HomeDestination` (one workspace: its dashboard; else the last-used workspace the person may open; else the one they can reach; else the list). `tests/HomeLandingTest.php` passes unchanged. `fortify.home` stays `/me/workspaces`, which the kit treats as its own.
  - **0.38: queues are tenant-aware by opt-in.** `config/multitenancy.php` sets `queues_are_tenant_aware_by_default` to `false` and lists Concierge's `DeliverInvitation` in `tenant_aware_jobs`, as the kit's defaults do. The audit found no queued class in `app/`. `kit:scaffold workspaces` wrote the nine make stubs into `stubs/`, so `make:job`, `make:listener --queued`, `make:notification` and `make:mail` start with `TenantAware` declared.
  - **0.39 and 0.43: migrations.** `php artisan migrate` adds the flag and tag tables (`tags`, `taggables`, `flag_dismissals`) and renames `kit_meta` to `app_meta`; a database migrated on 0.25 took all three. `bin/ci-assert-deploy.php`, `docs/deploy.md` and `tests/HandsFreeDeployTest.php` read `app_meta`; `database/fixture.php`, `tests/MigrationTest.php` and `tests/FeatureSeamsTest.php` (with a new `tests/Fixtures/schema-tags.json`) carry the three migrations; and the avatar's stored morph type is `user-avatar`.
  - **Tests follow the kit.** The invitation mail's button is "Accept invitation" with the workspace named in the heading and paragraph (0.42.1), the focus shell's user carries `avatar_color` (0.31), a NavigationHeader serialises `trail` (0.45), and on the Welcome photo step the focus on arrival is the field's dropzone, which holds its file input (0.28; `tests/Browser/FirstRun.php`).
  - **Checks.** `sh bin/kit-parity.sh` reports the template's 71 kit files equal to `kit:scaffold` (62 before: the nine stubs are new), with no tweaks.
  - **Not taken:** `kit.shell.settings_link` (0.44.1), `Registry::modulePage()` groups, icons and widths (0.45 to 0.46.1), card variants (0.45), `kit.breakout` `'with-trail'` and `Widget::headless()`, all opt-in with the default unchanged and nothing in the template to use them; flags and `TagList` on a record (0.39: the template has no record of its own, so only the tables arrive); `PasswordInput secret` (0.39.1: no pasted-secret field in the template); `--kit-ground: white`, the opt-out that keeps the white ground; and `kit:scaffold <feature> --adopt`, `kit.devices.store` and `kit:devices:backfill`, as before. Notes, finalize and deliver are not adopted, so their changes (NoteCard, the seamless Composer, DeliverPanel) don't apply.

## v0.5.0

### Changed

- **Media Library files live under `media/` in the app's one bucket** (#129, #130). A new `media` disk shares the bucket with `root` `media` when `MEDIA_DISK=s3`, and uses `storage/app/private/media` locally; Media Library's `disk_name` is `media`, and `s3` stays the whole bucket for the app's own files. A disk rather than Media Library's `prefix`, because a prefix recomputes every existing item's path and orphans its files; existing rows keep resolving on the disk they name. `tests/StorageTest.php` checks both disk shapes and sets `MEDIA_DISK` in `$_ENV` and `$_SERVER` as well as `putenv()`, restored in `finally`.
- **tey/standards is upgraded to v0.12.3** (from v0.11.103; constraint `^0.12.3`) (#132). v0.12 enforces the first-party-packages rule, under which the starter has no findings. The regenerated patterns table names the Bakery recipes for `settings.form` and `ui-page.show`, and a leaf whose packages the app doesn't require (such as `ui-page.modal-run`) is no longer installed.
- **Pest 5.3.6** (#123), dev dependencies only; pest-plugin-browser stays at 5.1.2.
- **`.agents/setup` runs on Debian 13 orbs** (#127): the PHP 8.5 source is a pinned signed-by keyring plus one sources entry instead of `add-apt-repository`. Development tooling only.

### Fixed

- **Web requests no longer bootstrap the console kernel** (#121). `bootstrap/app.php` ended by bootstrapping the console kernel, which Laravel's skeleton never does; dropping it saves its deferred providers and command registration on each request.

### Security

- **phpseclib/phpseclib is updated** (#124); `composer audit --locked` reports no advisories.

### Changed

- **tey/kit is upgraded to v0.25.0** (from v0.17.0, through v0.17.8 for `kit:detach`; `composer update tey/kit -W`, constraint `^0.25`). Every upgrade step the kit lists for 0.18 to 0.25 that applies to the starter's features was taken, and pest, phpunit and paratest did not move.
  - **0.18: no install records.** `kit:detach` on 0.17.8 reported every kit file unchanged and `config/kit.php` already listing the features. Its checklist is deleted: `.mod-maintenance/`, the `kit:install` host binding (`app/Kit`, `config/kit-lifecycle.php`, `config/kit-executor.json`, `KitHostServiceProvider`) and the identical copies under `database/migrations/kit`, which now load from the package with the 0.18 renames (`kit_user_state` → `user_state` and the rest). `App\Support\KitMigrator` is gone: core's migration no longer references `workspaces`, and its binding replaced the kit's `PackageMigrator`.
  - **0.18: the shared components load from the package.** `resources/js/components/Kit` is deleted; Vite and `tsconfig.json` alias `@/components/Kit` and `@kit/ui` to `vendor/tey/kit`, `kitImports` and the warmup come from there, and `app.css` names the package in `@source`.
  - **0.20 to 0.24: the scaffolded pages.** Each feature's changed pages are taken with `kit:scaffold <feature> --force` (all were unedited): the Security page's per-device Sign out, the switcher, drawer and workspace list loading `/me/switcher` (`workspaceLandings.ts` is new), the auth pages' `ActionGroup` and `Stack`, the dashboard's `WidgetCard` and the settings pages. The generated types follow, through the kit's `AttributedDataTransformer`, which keeps docblock literal unions.
  - **0.23: no `choice.url`.** After sign-in, `LoginResponse` reads `PageShell::landings()` to land a person with one reachable workspace in it. The URL, home-landing and Inertia tests read each choice's slug and the links from `GET /me/switcher`.
  - **0.24: signed-in devices.** The base `TestCase` calls `withCredentials()`, so a JSON request after a sign-in keeps the session (without it the device row moves to a fresh id and the next request is signed out). The devices tests adopt a `sessions` row with `SignedInDevices::adopt()` before expecting it listed, as the list reads `signed_in_devices`; the browser test runs on the database driver and signs that device out from the page. `model:prune` also prunes `SignedInDevice`.
  - **0.22 and 0.25: Alert actions** change look with the package alone; every Alert with actions in the starter is in a kit page.
  - **Checks.** `bin/kit-parity.sh` and the Kit parity job compare the template with `kit:scaffold --diff`, and `tests/LifecycleTest.php` proves the same plus scaffolding an empty checkout and keeping an edited file. tey/standards is updated to v0.11.103 (within `^0.11.94`), which counts scaffolded files as the kit's while they equal its stubs.
  - **Not taken:** 0.19's optional `kit:scaffold <feature> --adopt` (the domain tables stay the kit's), `kit.devices.store` (the database cache store holds the revocation marks: one query per signed-in page), and `kit:devices:backfill`, a one-off for an app upgrading a database with live sessions.

### Fixed

- **`.agents/setup` never creates a hooks folder outside the checkout.** A configured `core.hooksPath` is resolved (the nearest existing parent, realpath-ed) and checked before anything is created; one outside the checkout or git dir is left alone. Development tooling only.
  - **Tests.** `tests/AgentsSetupHookTest.php` runs the block in throwaway repositories (`tests/scripts/agents-setup-hook-check.php`): no hooksPath, an inside path created, an absolute, `../` and `missing/../..` outside path and a dangling symlink each falling back with nothing created, an existing hook chained, and the trailer filter (bot lines gone, people kept, idempotent).

### Added

- **`.agents/setup` strips AI attribution trailers.** It installs a repo-local commit-msg hook that removes Amp-Thread-ID, Co-authored-by for Amp or Claude, Claude-Session and "Generated with" lines. Squash-merging stays the backstop.
- **`.agents/setup` for cloud agents.** A fresh checkout gets PHP 8.5, Composer, PostgreSQL (the `root` role and `starter_testing` database that `.env.testing` names) and Node, then `composer setup` and Playwright Chromium. Development tooling only; the app is unchanged.

### Fixed

- **The mail-sender tests prove where the address comes from.** `tests/MailSenderTest.php` gave its child process `.env.example`'s `MAIL_FROM_ADDRESS`, which equals the kit's default brand sender, so it passed whatever the source. The child now gets an address of its own (`laravel-env@example.test`) and no `KIT_BRAND_*`, and the test asserts that the sender is Laravel's (the template's brand is all placeholders, so the kit leaves it alone) and that the brand's address is the kit's default, not env. A new `tests/BrandTest.php` case asserts that Laravel loads `config/brand.php` as `brand`, and that a sender set there wins over both `KIT_BRAND_MAIL_FROM_ADDRESS` and `MAIL_FROM_ADDRESS` (source `config`). Config is restored in `finally`.

- **The dashboard and People settings pass axe's WCAG 2.2 AA scan.** `tests/Browser/Accessible.php` calls `assertAccessible` on both. axe flagged `color-contrast` on the search field's `⌘K` hint (`text-muted-foreground` on `bg-muted`, about 4.4:1); the light `--muted-foreground` token in `resources/css/app.css` goes from `hsl(0 0% 45.1%)` to `hsl(0 0% 42%)`, which clears 4.5:1 on `--muted` and darkens muted text everywhere slightly. Dark mode is unchanged and not scanned.

- **An invitation link the route cannot match has an h1.** A guest who opens `/invitations/<token>` with a token the route rejects (one with a dot, say) gets the 404 error page outside the shell, where the Page draws no heading; since tey/kit 0.16.16 the kit's `Workspace/Error/Page.vue` draws the title as its own h1 whenever no shell draws it (see the tey/kit 0.16.16 entry). An unknown but well-formed token already showed "This link is not valid" as its h1. Both are asserted in `tests/Browser/Accessible.php`.

### Changed

- **The flagged browser tests run the axe check.** The twelve files standards' `browser.accessible` rule flagged (`tests/Browser/Account.php`, `Alert.php`, `AuthCards.php`, `AxeSettle.php`, `FirstRun.php`, `Focus.php`, `FormActions.php`, `Modals.php`, `Notes.php`, `People.php`, `Placeholder.php` and `Shell.php`) now call `assertAccessible($page, '…')` (`tests/Pest.php`: WCAG 2.2 AA, every impact level) on each page they land on, once the test's own assertions show it has settled, in light mode before any theme loop. `php artisan standards:check` reports no `browser.accessible` advisories. `SignIn.php` and `Accessible.php`, which already called it, are unchanged: they still navigate to pages they do not scan (`SignIn.php` to `/settings`, `/settings/general` and `/me/profile`; `Accessible.php` to the signed-in 404), and `docs/testing.md` says which pages they cover. One page failed: on `/me/security` with two-factor on, the destructive buttons (Regenerate recovery codes, Turn off) failed `color-contrast`, white on the light `--destructive` that `resources/css/app.css` set to shadcn's `hsl(0 84.2% 60.2%)` (#ef4444, 3.76:1), overriding the kit's. It now uses the kit's `hsl(0 74% 42%)` (6.48:1 on white per kit.css), which also darkens `text-destructive` error text. Dark mode is unchanged and not scanned. No visit was skipped.

- **tey/kit is upgraded to v0.17.0** (from v0.16.16; `composer require tey/kit:^0.17.0 -W`, and only tey/kit moved). 0.17 drops the `KIT_BRAND_*` env fallback and the dashboard welcome card. Onboarding, `/welcome`, its steps and the You card's checklist all stay. Only `workspace-ui` had file changes: `kit:update` replaced `resources/js/pages/Workspace/Dashboard/Page.vue`, which the starter hadn't changed, and the card's markup is gone. The other seven lineages planned no changes. There were no migrations, removals or conflicts. `composer types:generate` drops `WelcomeCardData` and the dashboard's `welcome` prop. `php artisan kit:status` lists no ignored `KIT_BRAND_*` and no 0.17 attention step: #113 had already moved the starter's brand to `config/brand.php`, and `tests/BrandTest.php` and `tests/MailSenderTest.php` no longer name a `KIT_BRAND_` key, which kit:status flagged even though they set none. `tests/FirstRunTest.php` and `tests/Browser/FirstRun.php` already read the checklist and assert that the card is absent. `tests/MailSenderTest.php` now hands its child process a leftover brand env sender and asserts that it is ignored. `config/brand.php`'s guidance now says the file is the only brand source, and that a key left out uses the kit's default. The kit's new dashboard page has no `props` const, so `app/Support/McpCapabilities.php` anchors `workspace.dashboard.read` on its controller (`DashboardController::show`) alone, and drops the page from the UI inventory (standards' MCP parity check requires every inventory path to be a declared UI path);  `docs/mcp-capabilities.json` is regenerated.

- **tey/kit is upgraded to v0.16.16** (from v0.16.14; constraint `^0.16.16`). `kit:update` was applied for `core` (`Form/directUpload.ts`: when the complete call fails, the upload is aborted, not left open), `shell` (`GuestShell.vue` tells the page when it draws the title) and `workspace-ui` (`Workspace/Error/Page.vue` draws its own h1 outside the app shell). Each plan replaced one file the starter hadn't changed, with no migrations, removals or conflicts. `composer types:generate` changed nothing. The starter's stopgap from #109 is gone: `resources/js/layouts/ShellLayout.vue` no longer passes the error page's title to the guest card. `tests/Browser/Accessible.php` still finds exactly one h1, "Page not found", on the 404 page both signed out and signed in.

- **Nothing in the starter sets `KIT_BRAND_*` env any more; throwaway brands live in a generated `config/brand.php`.** tey/kit 0.17 removes the env fallback. The Deploy order and Production rehearsal jobs run `bin/ci-brand-config.php`, which writes a throwaway `config/brand.php` for the run (it refuses outside CI unless `--force` or `--path=`; never committed). `bin/rehearsal-env.php` emits no `KIT_BRAND_*` lines at all (the `--no-brand-env` flag is gone), and the local `tests/ProductionRehearsalTest.php` cases write their brand to the throwaway app root's `config/brand.php` with `--path=`. `bin/fake-brand-env.sh` is replaced by `bin/fake-brand-config.sh` (`composer test:brand-config`, was `test:brand-env`), which runs the suite with an app brand in a generated `config/brand.php` and puts the committed file back afterwards. `/up` and `kit:preflight` pass as before. `tests/ProductionRehearsalTest.php` asserts the rehearsal file sets no `KIT_BRAND_*`, that the rehearsal brand (now with throwaway Terms and Privacy URLs) leaves no key at its placeholder, and that the generator refuses to overwrite the committed file outside CI. The only `KIT_BRAND_*` left are tests asserting env is ignored, docs and comments.

- **`kit:status` is clean, `composer audit` too, and the muted text matches the kit.** `symfony/serializer` goes from v8.1.9 to v8.1.11 (`composer update symfony/serializer --with-dependencies`; nothing else moved), which fixes its three advisories (the DataUriNormalizer file read among them). `.env.example` sets `PASSPORT_PRIVATE_KEY=` and `PASSPORT_PUBLIC_KEY=`, empty: locally Passport falls back to the `passport:keys` files, and production sets them as `.env.production.example` shows. `resources/js/app.ts` imports the kit's PublicShell and puts the kit's public pages (`Public/*`) in it with the sign-in and workspace URLs (docs/public-shell.md); a guest's error page stays in the GuestShell, which draws its title as the h1. The light `--muted-foreground` in `resources/css/app.css` goes from `hsl(0 0% 42%)` to the kit's default `hsl(0 0% 41%)` (5.07:1 on `--muted`).

- **The brand lives in `config/brand.php`, not env.** The `KIT_BRAND_*` lines are gone from `.env.example` and `.env.production.example`, which clears standards' 21 `brand.no-env-colours` advisories. `config/brand.php` follows the kit's stub (`vendor/tey/kit/resources/stubs/brand.php`): every key the template used to set is there, commented out at the kit's default, with the guidance the env files carried. Every value the template set was already the kit's default (`KIT_BRAND_ACCENT=#52525b` also loaded empty, since an unquoted `#` starts a comment), so nothing resolves differently. Unset keys are left out, as kit 0.16.8 omits them. An app uncomments and fills the keys it needs and commits the file. `KIT_BRAND_*` from the platform still works (deprecated until kit 0.17); the starter itself no longer sets it anywhere (see the entry above). `docs/deploy.md` says to set the brand in `config/brand.php`. `tests/BrandTest.php` checks that neither env example sets a brand key, and `tests/MailSenderTest.php` no longer reads the sender address from `.env.example`.

- **tey/kit is upgraded to v0.16.14** (from v0.15.30; constraint `^0.16.14`; 0.16.14 re-pins `core` for the ModalPanel focus-trap fix: Overlay/Modal and ModalPanel). `kit:update` applied for every installed lineage; files changed in `core`, `settings`, `shell` and `workspace-ui`, and `access`, `mcp`, `session-ui` and `workspaces` re-recorded with no file changes. No conflicts. The v0.16.0 card rename (`ActionCard` is the panel, `SummaryCard` the header band) needed no starter change: only kit-owned files use the cards, and `kit:status` reports no pre-0.16 card names. `resources/js/types.generated.d.ts` is regenerated (`composer types:generate`) for the kit's new view-model fields. The lock also moves laravel/framework to v13.42.0 with its dependencies. Tests follow the kit's behaviour since 0.15.30: `tests/DashboardTest.php` expects the owner's new **Set up this workspace** card (`kit.setup`, always last; not the viewer's), and `tests/Browser/FirstRun.php` expects the You card's "Finish setting up" checklist in place of the removed welcome card, and a person with no workspace to land on their empty `/me/workspaces` (it no longer redirects; `/no-workspace` is still checked in the FocusShell). `tests/FirstRunTest.php` follows the same changes without a browser: the skipped steps are read from the You card's checklist, `/me/workspaces` renders an empty list instead of redirecting, and self-serve offers no Create a workspace while the app registers no `/me/workspaces/create` route.

- **tey/standards is bumped to v0.11.94** (from v0.11.74; constraint `^0.11.94`). `standards:check` passes with 0 findings; its 34 advisories (21 `brand.no-env-colours`, 12 `browser.accessible`, 1 `tenant-aware`) are reported only.

- **`assertAccessible` also runs axe's best-practice rules, as warnings.** The same scan adds the `best-practice` tag (heading-order, landmark-unique, region and the rest). A finding whose rule carries none of the WCAG tags is printed to STDERR once per run as `AXE WARNING <rule> (<impact>) <selector> @ <url>` and never fails the test; WCAG rules fail as before, and `AXE_EXCEPTIONS` silences a warning the same way it covers a violation. `AXE_BEST_PRACTICE=0` skips the best-practice rules. Covered in `tests/AccessibilityTest.php` with stand-in pages, no browser.

- **`intervention/image` is constrained to `^4.2`.** Laravel's image package requires `^4.2`, so the starter's `^4.0` allowed versions it can't install with; the lock already resolves 4.4.1.

### Added

- **Laravel's image API is installed.** `intervention/image:^4.2` backs `Illuminate\Support\Facades\Image`; `config/images.php` (the framework's shape) reads `IMAGE_DRIVER` and defaults to `imagick`, and `.env.example` sets `IMAGE_DRIVER=imagick`. Userland image code uses this API; Media Library keeps spatie/image, and raw Imagick or GD is only for pixel reading, with a why-comment. `tests/ImageConfigTest.php` checks the config loads and that the configured driver's PHP extension is loaded; CI installs `imagick`.

### Fixed

- **`assertAccessible` waits for the Inertia page to render before scanning.** Right after a full load the `@inertia` root can still be empty while the page component loads, and a scan then reports `landmark-one-main` and `page-has-heading-one` on pages that have a `<main>` and an `<h1>`. It reproduced against the Vite dev server (3 of 4 runs of the new test failed with the old helper), not with built assets. The scan now waits (up to 5 s) for `#app` to have content, then for animations as before. `tests/Browser/AxeSettle.php` scans the dashboard and the profile page right after each navigation; `tests/AccessibilityTest.php` checks the wait is in the scan. `docs/testing.md` now says best-practice warnings go to STDERR, that parallel runs (`composer test:browser`, `--parallel`) swallow them, and to run a non-parallel accessibility test to see them.
- **The account-notice browser test waits for the dashboard to finish loading.** `tests/Browser/Alert.php` left the dashboard while its people list's partial reload was still in flight; when that request reached the session after the notice was flashed, it spent the flash and the notice page rendered without it (3 of 30 probe runs locally, and a CI failure). It now waits for the list first, as `SignIn.php` does (0 of 30).

- **The guest layout has a `<main>` landmark.** `resources/js/layouts/GuestLayout.vue` rendered the sign-in card as a plain `<div>`, so axe's best-practice rules reported `landmark-one-main` and eight `region` findings on `/login`. The card is now `<main id="main">` with the same classes, so nothing moves; the wordmark stays in the `<header>`. `tests/Browser/SignIn.php` asserts the card is the page's one `main`.
- **The invite modal's focus check no longer races the focus trap** (#103). Opening a form modal moves focus three times: the panel puts it in the first field, the modal package's trap moves it to Close when the enter animation ends, and the panel takes it back. `tests/Browser/Modals.php` read focus after a fixed 500 ms pause, which on a busy runner landed while focus was on Close. It now uses the browser plugin's retrying `assertScript` and waits until the enter animation has finished and focus is in the email field.

- **The render-site scan skips comments.** `tests/ViewModelStandardTest.php` matched `Inertia::render(` anywhere in a line, so the kit 0.15.30 `ViewModel.php` docblock that mentions it failed "every render site passes a view model". Lines whose trimmed text starts with `*`, `/*`, `//` or `#` are now skipped, with a regression case.
- **The capability manifest's `test_*` names match Pest descriptions with punctuation.** `tests/McpCapabilitiesTest.php` only turned `_` into a space, so `it('a, b')` never matched `test_a_b`. Both sides are now normalised the same way (lowercased, punctuation stripped, whitespace and underscores collapsed), with regression rows including a comma, and a failed match says how names are compared.
- **Regenerated types export every namespace.** A bare `php artisan typescript:transform` wrote `namespace Kit {` inside `declare namespace Tey`, which keeps it private, so `defineProps` couldn't resolve `Tey.Kit.…` and the production build failed; only `composer types:generate` patched it afterwards. The shipped writer, `App\TypeScript\ExportedNamespaceWriter`, now exports every nested namespace, whichever way the file is regenerated. Covered by `tests/TypeScriptNamespacesTest.php`.
- **The FocusShell's account cluster and the LocalModal example group their actions.** Each had adjacent buttons outside an action container, which the standards `ui.action-group` rule (v0.11.78+) reports as advisories in every app made from the starter. The account cluster (Switch account, Sign out, the appearance toggle) and the example's three openers now sit in the kit's `Toolbar`, which sets only the gap and alignment, so neither page looks different.

### Changed

- **tey/kit 0.15.30.** `composer.json` requires `tey/kit` ^0.15.30 (was ^0.15.22). Each adopted lineage was planned with `kit:update plan --lineage=<feature>` and reviewed; core (16 kit components, including `Page`, `PageTabs` and `contract.ts`), shell (`Workspace/Layout.vue`, `PrimaryNav.vue`) and workspace-ui (`WidgetCard.vue`) were applied, every file unchanged from the kit's copy; access, mcp, session-ui, settings and workspaces had no changes. No migration changed and nothing was removed. Breadcrumbs now sit in the bar and module tabs come from `layout()` (0.15.28); every starter page renders a `PageViewModel`, so none logs the 0.15.30 plain-ViewModel warning. `composer types:generate` produced no changes.
- **tey/kit 0.15.15.** `composer.json` requires `tey/kit` ^0.15.15 (was ^0.14.1), and every adopted feature is updated in place with `kit:update plan --lineage=<feature>` / `apply`, each plan reviewed: core, access, workspaces, settings, mcp, shell, session-ui and workspace-ui. Every file the plans touched was unchanged from the kit's copy; no migration changed and nothing was removed or held. The regenerated `types.generated.d.ts` makes a confirm's `title` optional (`title?: string | null`); every reader of it is a kit-owned component, and they show `title ?? "{label}?"`. The kit's 0.15.11 defaults now show: both footers read `© {year} {identity}` (`KIT_BRAND_COPYRIGHT=false` leaves it out), the credit is "Tey Labs Inc." (`.env.example` updated), and the welcome card greets with the workspace's name; `tests/BrandTest.php` and `tests/FirstRunTest.php` assert the new defaults.
- **Tests run on PostgreSQL by default, in parallel.** `phpunit.xml` and a committed `.env.testing` select PostgreSQL (Herd's defaults, database `starter_testing`); any `DB_*` set for the run overrides it, and `.env` is never read. Each parallel worker gets its own database, `{DB_DATABASE}_w{token}`, created on first use. `composer test` runs `pest --tia --parallel`. CI runs the suite on a PostgreSQL service on every PHP version, and the browser tests on one too; the SQLite job is gone, and SQLite memory stays an explicit opt-in (`TEST_DB_CONNECTION=sqlite`) for the released-schema comparison. Jasper's ruling, 2026-11-07.
- **Notes is opt-in.** A new app no longer ships the kit's `notes` feature: no `kit_notes` table, notes pages, dashboard card, navigation link or MCP tools. Add it with `php artisan kit:add notes` (docs/lifecycle.md). Existing apps keep theirs. The notes tests stay and switch on with the feature.

## v0.4.0

### Added

- `composer test:local`: the whole suite on a laptop, the PHP suite on 8 processes (without the `nightly` group), then the browser suite on 4 (`PEST_BROWSER=1`). Ctrl-C stops both and every worker. `composer test:browser` runs in parallel, in CI too.

- **Prettier and ESLint** with the house config (4 spaces, single quotes, 80 columns, `htmlWhitespaceSensitivity: ignore`, the Tailwind class sorter; Vue + TypeScript rules, type-only imports, braces always, blank lines around control statements). Scripts `format`, `format:check`, `lint`, `lint:check`; CI runs both checks. Kit-owned files, shadcn-vue primitives and generated types are ignored. The starter's own files are formatted in this change.

### Fixed

- The vendored `docs/schemas/mcp-capabilities.schema.json` is now tey/standards' copy, and its `$comment` names standards as the source. A minimal uiOnly capability (`id`, a non-blank `uiOnly` reason and `routes`), as rule 55 documents, now validates; a full capability may also carry `routes`. `tests/McpCapabilitiesTest.php` checks that a minimal uiOnly entry passes, and that a blank reason, empty routes or a missing reason fail.

### Changed

- **tey/kit 0.14 and tey/standards 0.11.74** (from kit ^0.4.13 and standards ^0.11.6), with tey/concierge ^0.9 (kit's floor) and the kit's new npm requirements (tiptap, `@internationalized/date`, `vue-draggable-plus`).
  - **Kit files**, resynced from `kit:add`: core's components, the shell, session-ui, settings (the workspace Email page), mcp (the consent page) and notes, plus the pages that moved to session-ui and workspace-ui. `workspace-ui` is adopted: notes now requires it. The opt-in `finalize` and `deliver` are not adopted.
  - **Installed records are committed.** `.mod-maintenance/installed/*.json` (relative to the checkout) ship with the template, so `php artisan kit:status --check` and `kit:update` work on a clone and tey/standards knows which files the kit owns. The journals, lock and owner file stay gitignored.
  - **Host steps:** the tenant tasks (`ConciergeContextTask`, `FlushFeatureCacheTask`) in `config/multitenancy.php`, and the Passport key variables documented in `.env.example`.
  - **Toasts** are the kit's Toaster, which the kit shell mounts and which renders what the server flashed. The app's shadcn-vue Sonner wrapper and its flash bridge in `app.ts` are gone.
  - **Behaviour the kit changed**, reflected in the tests: a member can be suspended; deletion sections are hidden until `kit.lifecycle` turns them on; invitations go out under the workspace's sender name; MCP consent binds the connection to a chosen workspace and makes it, and a narrow connection lists only the tools it covers; a workspace without its dashboard redirects to its first page; brand placeholders fail `/up` only when `kit.brand_mail` is on; accepting an invitation verifies the invited address.
- **The strict standards gate.** `tests/Arch/configuration.php` turns on every optional and advisory rule (`optionalRules`) and enforces rule 51 (`ciEnforce`, and `PREFLIGHT_CI_ENFORCE=true` in CI), with an empty allowlist. The findings are fixed in the code:
  - CI: pull requests run on opened, synchronize, reopened and ready for review; draft-aware concurrency that never cancels `main`; a timeout on every job; the test jobs skip drafts; one PHP version (8.4) on pull requests, 8.4 and 8.5 on `main`. The reusable standards workflow is pinned to v0.11.74.
  - `starter:first-owner` takes `--dry-run` and refuses production explicitly.
  - `AppServiceProvider` prohibits destructive database commands outside the tests (`ALLOW_DESTRUCTIVE_DB=1` for one deliberate local reset).
  - The sign-in response's JSON branch is marked as Fortify's contract (`inertia.pure: package`).
  - `FeatureSeamsTest` reads the route seams each route declares instead of a stored route table (`tests/Fixtures/routes.json` is gone), and a browser check no longer pins a pixel height.
- **Kit parity** checks the template's adopted features (`config/kit.php`), not the whole catalog, and gives the clean app the template's shadcn-vue primitives, which core's `host_ui` step requires.
- **Tests no longer assert kit markup.** Checks that read a kit-owned file's source (FormActions' internals, the kit Select, kit pages' roots and controls) now read the app's own files only; the kit's library checks and parity hold the kit's.

- Global search is on (tey/kit global search, `workspace.search` in `config/kit.php`).
  - **UI.** The header has a search field with a live palette: ⌘K, Ctrl+K or `/`, and a sheet below `lg`. The full results are at `/@{workspace}/search`. The kit's people provider finds the workspace's members, with a People link for those who manage people.
  - **MCP.** The read-only tool `workspace_search` is declared in `docs/mcp-capabilities.json` as `workspace.search.read`, under the new `search.read` scope.
  - **Tests.** `tests/SearchTest.php` covers the session transport:
    - the owner's results, with no other workspace's people;
    - the palette JSON, which is `no-store`;
    - the 422 for a long term;
    - a viewer without links, and with no access to another workspace;
    - the per-minute limit (429);
    - the 404, with no header search, while the capability is off.

    `McpTransportTest` covers the delegated transport: a settings-only connection is refused `workspace_search`.
  - **Kit files**, resynced from `kit:add`, with the TypeScript types regenerated:
    - core: `Layout/StickyBar.vue`, `Layout/PageEnd.vue`, `Search/SearchBox.vue` and `Search/search.ts`, and updates to `contract.ts` and `index.ts`;
    - shell: `Partials/GlobalSearch.vue` and the Workspace Layout;
    - session-ui: `Workspace/Search/Page.vue`.
  - `FeatureSeamsTest` and its route fixture now include the search routes, the `search.read` scope, the `workspace.search` capability and the `rate_limited` (429) code.
  - **Pins.** The starter pins `tey/kit` ^0.4.13 (notes, global search with case-insensitive matching on PostgreSQL, StickyBar and PageEnd) and `tey/standards` ^0.11.6, whose kit-exports snapshot includes `PageEnd`, `SearchBox` and `StickyBar`, so `standards:check` gives no warning. Kit 0.4.11's named `executor_stale` refusal is what `LifecycleTest` now expects. The placeholder browser test checks for no form or status inside `main`, because the header's search box keeps a live region.
- The starter adopts the kit's `notes` feature:
  - a Notes page (composer, colour filter, a wall of cards with edit, pin and delete with a confirm, attachments, and a save toast) in the primary nav, and a `notes.recent` dashboard card;
  - `config/kit.php` turns on `notes` and `workspace.notes`;
  - its files are synced from `kit:add` (`pages/Workspace/Notes`, `widgets/Notes.vue`, `database/migrations/kit/notes`), and Kit parity now covers notes instead of leaving it out;
  - permissions: owner and admin write and moderate, and viewer reads. Changing someone else's note needs the owner or `workspace.notes.moderate`; `notes.write` never gives it.
  - The legacy `notes`/`note_intents` tables an upgraded database may hold are left alone. Copy them with `php artisan kit:notes:import --from=notes` (idempotent; never changes the source).
  - The four MCP tools (`notes_list`, `notes_read`, `notes_write`, `notes_delete`) are declared in `docs/mcp-capabilities.json`.
  - Tests:
    - `tests/NotesTest.php` covers the session transport: writes, intent replay, stale versions, attachments, and moderation for the owner, your own note, someone else's without moderate, and with it.
    - `McpTransportTest` covers the delegated one, including the delete confirmation.
    - `tests/Browser/Notes.php` writes, edits and deletes a note in a browser.
  - The starter's kit pin is in the search entry above.

- The dashboard shows the kit's widgets (tey/kit dashboard widgets): the You card, Recent activity (members who manage people only) and People, in a responsive grid that follows each widget's span. Session-ui's dashboard files are resynced from `kit:add` (`Dashboard/Page.vue` and the new `Dashboard/Partials/`), and the TypeScript types are regenerated. The read-only MCP tool `workspace_dashboard` is declared in `docs/mcp-capabilities.json` as `workspace.dashboard.read` under the new `dashboard.read` scope. `tests/DashboardTest.php` covers it on the session transport, and `McpTransportTest` on the delegated one: a settings-only connection is refused, and a `dashboard.read` connection gets only the widgets its grant covers. `SignIn` (browser) asserts that the grid and the people list render. The starter pins `tey/kit` ^0.4.8. Core's kit files are resynced from `kit:add` for 0.4.8's index table (`Table/BulkBar.vue`, `Table/IndexToolbar.vue`, `Table/selection.ts`, and updates to `DataTable`, `contract.ts` and `index.ts`). `standards:check` reports 0 findings, but warns that tey/standards' kit-exports snapshot lacks `BulkBar` and `IndexToolbar` until tey/standards is updated.

- The starter pins `tey/kit` ^0.4.7 and `tey/standards` ^0.11.2. Core's kit files are resynced from `kit:add`, the files Kit parity compares: `FormActions` carries `data-action`, so a save toast's `->from()` can anchor to the form's Save button, and the `Toaster` dispatches a `kit:toast` event. No shell or session-ui file changed. `standards:check` reports 0 findings with an empty allowlist, and its kit-exports snapshot now includes `Toaster`.

- The starter pins `tey/kit` ^0.4.6 and `tey/standards` ^0.11.1.
  - Kit files resynced from `kit:add` (this checkout has no lineage records for `kit:update` to plan against), the same files Kit parity compares:
    - core: the headless `Feedback/Toaster.vue` and `toastPlacement.ts`, and `Layout/stickyBars.ts`, which `FloatingToolbar` now registers with;
    - shell: the Workspace Layout and GuestShell mount the `Toaster`;
    - session-ui: the Settings › People page.
  - Pages now share `kitToasts`, the toast position.
  - The People-page `descriptors` allowlist entry is gone because kit fixed the page. `standards:check` reports 0 findings with an empty allowlist.
- The local TIA guard is strict again. `tests/Pest.php` checks `CI` as non-empty (`getenv('CI') === false || getenv('CI') === ''`), the shape standards 0.11.1's `tia` rule accepts, and `TiaGuardTest` covers an empty `CI=`. This restores the empty-`CI=` fix below.
- `standards:install` (0.11.1) kept `boost.json`, which already lists `agents` and `packages`.

- The starter pins `tey/standards` ^0.11 and adopts it: `standards:install` wrote `tests/Arch/configuration.php`, the ConformanceTest, the standards skills, the `.claude/settings.json` hooks, the git pre-push hook and the AGENTS.md pattern index. `php artisan standards:check` exits 0 and CI runs it through the reusable `teylabs/standards` workflow (v0.11.0). Find the pattern for new work with `bakery.find` (offline: `php artisan standards:pattern`).
- Laravel Boost is set up for Claude Code and Codex: `boost.json`, `.ai/guidelines/starter.md`, the generated guideline block in AGENTS.md and CLAUDE.md, and the package skills standards expects. Boost 2.10 ships no `pest-testing` skill, so the configuration maps Pest to `testing-best-practices`.
- `@inertiajs/core` is a declared dependency (3.9.1, matching `@inertiajs/vue3`): the kit-owned `splitState.ts` and `useScrollList.ts` type-import it.
- The local TIA guard is inline in `tests/Pest.php`, in the shape standards' `tia` rule reads; `TiaGuard` keeps only the base branch. A set `CI`, `PARATEST` or `PEST_TIA` now bypasses it even when empty, which reverses the earlier empty-`CI=` fix below.
- Stock Laravel placement: `FirstOwnerCommand` moves to `App\Console\Commands` and the TypeScript provider to `App\Providers\TypeScriptServiceProvider`.
- phpunit/phpunit 13.4.5 and pestphp/pest 5.3.4 (GHSA-9vw2-f4vp-x7gf affects phpunit 13.4.0 to 13.4.3); `composer audit` is clean.
- The kit-owned Settings › People page is allowlisted for the `descriptors` rule until the kit re-pin fixes it (it only displays server-provided role labels; expires 2026-11-10).

- The starter pins `tey/kit` ^0.4.3 (kit#117, fixed in 0.4.1). The kit-owned `Layout/splitState.ts` is resynced from `kit:add`: it imports `http` from `@inertiajs/vue3` statically, which clears Vite's INEFFECTIVE_DYNAMIC_IMPORT warning. No other kit-owned file changed, and the mcp feature has no host-owned middleware change.
- `vite build` prints no chunk-size warning: `vite.config.ts` splits the TanStack and UI vendor libraries into their own chunks instead of one 546 kB `Kit` chunk. `chunkSizeWarningLimit` is unchanged.
- The starter pins `tey/standards` ^0.10.1 (was ^0.7). The non-blocking preflight level is `advisory` (JSON `level`, was `warning`), a non-interactive preflight prints only failed and advisory rows plus a summary, and `install.sessions` blocks a deployed Passport app on a cookie or unset `SESSION_DRIVER`. `.env.example`, `.env.production.example` and the production rehearsal CI jobs already set `SESSION_DRIVER=database`, and `docs/deploy.md` now names the check.
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

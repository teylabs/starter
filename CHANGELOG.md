# Changelog

## Unreleased

### Fixed

- **The mail-sender tests prove where the address comes from.** `tests/MailSenderTest.php` gave its child process `.env.example`'s `MAIL_FROM_ADDRESS`, which equals the kit's default brand sender, so it passed whatever the source. The child now gets an address of its own (`laravel-env@example.test`) and no `KIT_BRAND_*`, and the test asserts that the sender is Laravel's (the template's brand is all placeholders, so the kit leaves it alone) and that the brand's address is the kit's default, not env. A new `tests/BrandTest.php` case asserts that Laravel loads `config/brand.php` as `brand`, and that a sender set there wins over both `KIT_BRAND_MAIL_FROM_ADDRESS` and `MAIL_FROM_ADDRESS` (source `config`). Config is restored in `finally`.

- **The dashboard and People settings pass axe's WCAG 2.2 AA scan.** `tests/Browser/Accessible.php` calls `assertAccessible` on both. axe flagged `color-contrast` on the search field's `⌘K` hint (`text-muted-foreground` on `bg-muted`, about 4.4:1); the light `--muted-foreground` token in `resources/css/app.css` goes from `hsl(0 0% 45.1%)` to `hsl(0 0% 42%)`, which clears 4.5:1 on `--muted` and darkens muted text everywhere slightly. Dark mode is unchanged and not scanned.

- **An invitation link the route cannot match has an h1.** A guest who opens `/invitations/<token>` with a token the route rejects (one with a dot, say) gets the 404 error page outside the shell, where the Page draws no heading; `ShellLayout.vue` now hands the guest card that page's title, so it shows it as the h1 (the kit's `Workspace/Error/Page.vue` is unchanged). An unknown but well-formed token already showed "This link is not valid" as its h1. Both are asserted in `tests/Browser/Accessible.php`.

### Changed

- **Nothing in the starter sets `KIT_BRAND_*` env any more; throwaway brands live in a generated `config/brand.php`.** tey/kit 0.17 removes the env fallback. The Deploy order and Production rehearsal jobs run `bin/ci-brand-config.php`, which writes a throwaway `config/brand.php` for the run (it refuses outside CI unless `--force` or `--path=`; never committed). `bin/rehearsal-env.php` emits no `KIT_BRAND_*` lines at all (the `--no-brand-env` flag is gone), and the local `tests/ProductionRehearsalTest.php` cases write their brand to the throwaway app root's `config/brand.php` with `--path=`. `bin/fake-brand-env.sh` is replaced by `bin/fake-brand-config.sh` (`composer test:brand-config`, was `test:brand-env`), which runs the suite with an app brand in a generated `config/brand.php` and puts the committed file back afterwards. `/up` and `kit:preflight` pass as before. `tests/ProductionRehearsalTest.php` asserts the rehearsal file sets no `KIT_BRAND_*` and that the generator refuses to overwrite the committed file outside CI. The only `KIT_BRAND_*` left are tests asserting env is ignored, docs and comments.

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

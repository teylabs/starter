# Architecture

The template is a Laravel host application built on [tey/kit](https://github.com/teylabs/kit) with every kit feature added. The features' classes, routes and config defaults run from the kit library (`Tey\Kit\Features\...`); the files a feature has in the app (its Vue pages and `kit.css`) are exactly what `kit:scaffold` writes, and its migrations and shared components load from the package. Maintained packages own their jobs. Locked versions are recorded in [package-map.json](package-map.json).

## Packages and their jobs

| Package | Job |
| --- | --- |
| `tey/kit` (0.48) | The features (core, workspaces, access, settings, shell, session-ui, workspace-ui, mcp), their migrations and shared components (loaded from the package), and `kit:scaffold`, which writes a feature's pages once. |
| `tey/concierge` (^0.7.4) | Workspace membership, ownership, account state and tenant/member invitations, with its own migrations, and `concierge:owners`. |
| `spatie/laravel-multitenancy` | The current workspace (tenant) context. |
| `spatie/laravel-permission` (via Concierge) | Roles within a workspace. |
| `spatie/laravel-settings` | Typed per-workspace settings (`WorkspaceSettings`). Its cache is off: it caches serialized objects. |
| `spatie/laravel-data` and `spatie/laravel-typescript-transformer` | Data objects, ViewModels and the generated TypeScript types in `resources/js/types.generated.d.ts`. |
| `spatie/laravel-medialibrary` | Media, stored on the private `s3` disk. |
| `league/flysystem-aws-s3-v3` | The private `s3` disk on Laravel Cloud's bucket variables (R2, path-style). |
| `laravel/pennant` | Workspace capabilities (`workspace.dashboard`, `workspace.settings`). |
| `laravel/fortify` | Password login, throttling and logout. Registration is closed. |
| `inertiajs/inertia-laravel`, `@inertiajs/vue3`, Vue 3, Vite, Tailwind CSS 4 | Session pages. |
| `laravel/passport` | OAuth authorization code with PKCE and explicit consent. |
| `laravel/mcp` | The MCP web server and its tools ([mcp-capabilities.json](mcp-capabilities.json)). |
| `laravel/nightwatch` | Monitoring, with committed sampling (`config/nightwatch.php`). |
| `lorisleiva/laravel-actions` | Used by `WorkspaceProfile`. |
| `glhd/gretel`, `spatie/laravel-query-builder`, Pest (with the browser plugin), Larastan, Boost, Pail, Pao, Tinker | Tooling, or installed and not yet adopted by a feature. |

## Host layout

| Path | Owner | What |
|---|---|---|
| `resources/js/pages/{Workspace/*,Auth/*,Welcome/*,Mcp/*}`, `resources/css/kit.css` | a kit feature | Written by `kit:scaffold`; the template keeps them at the kit's bytes (CI checks they equal `kit:scaffold --diff`). |
| `config/kit.php` | host | The adopted features (all of them) and the kit's options. |
| `app/Models/User.php` | host | The user model: Concierge `Member`, Passport, the kit's `HasWorkspaces`. |
| `app/Providers/AppServiceProvider.php`, `app/Support` | host | Passport migrations, the sign-in page, `starter:first-owner`, the 0.2 migration-id map, the kit migration order, the MCP inventory. |
| `config/*.php` | host | Published package config: once published, a file is the whole truth for its key. |
| `resources/js/{app.ts,layouts,components,composables,lib}`, `resources/js/pages/Auth/SignIn` | host | The designed shells, primitives and sign-in page. |

`app.ts` resolves `./pages/<name>.vue` and wraps `Auth/*` pages in `GuestLayout` and every other page in `AppLayout`, both fed by the shared `layout` props (the kit's `ShellData`). `AppLayout` wraps the kit's `pages/Workspace/Layout.vue` (the header, switcher, nav, settings menu, user menu and drawer) with the app's mark, appearance choice, width and footer. The kit library's feature graph is checked in `tests/FeatureGraphTest.php`.

## Principles

- One operation per job. Session routes and MCP tools call the same operation, so options, effects and refusals match.
- Identity comes from the transport (session or token) as a `Principal`. Operations never accept an actor, workspace or connection as an argument.
- `Access::authorize` checks membership, account, verified email, admission, role, connection scope and capability. These stay distinct; entitlement is checked only when an operation asks for it.
- Tenant and permission context are set by `TenantBoundary` and restored in `finally`.
- Writes carry an expected version and a stable intent key. See [operations.md](operations.md).
- The app runs and stores in UTC; `resources/js/lib/time.ts` shows instants in the viewer's zone.
- The cache holds arrays and scalars only (`cache.serializable_classes` is false).

## Not covered

Delegated initial onboarding, password reset and branded mail (#24), jobs and files isolation, PostgreSQL concurrency and the deploy smoke command (#32) are not implemented or not proven. See [ROADMAP.md](../ROADMAP.md).

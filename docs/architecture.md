# Architecture

The starter is a Laravel host application. Maintained packages own their jobs; the host owns the glue and the tenant-scoped operations. Locked versions and what has been executed for each package are recorded in [package-map.json](package-map.json).

## Packages and their jobs

| Package | Job |
| --- | --- |
| `tey/concierge` (pinned to 0.7.2) | Workspace membership, ownership, account state and tenant/member invitations, with its own migrations. |
| `spatie/laravel-multitenancy` | The current workspace (tenant) context. |
| `spatie/laravel-permission` (via Concierge) | Roles within a workspace. |
| `spatie/laravel-settings` | Typed per-workspace settings (`WorkspaceSettings`). |
| `spatie/laravel-data` and `spatie/laravel-typescript-transformer` | Data objects and the generated TypeScript types in `resources/js/types.generated.d.ts`. |
| `laravel/pennant` | Workspace capabilities (`workspace.settings`, `workspace.notes`). |
| `laravel/fortify` | Password login, throttling and logout. Registration is closed. |
| `inertiajs/inertia-laravel`, `@inertiajs/vue3`, Vue 3, Vite | Session pages. |
| `laravel/passport` | OAuth authorization code with PKCE and explicit consent. |
| `laravel/mcp` | The MCP web server and its tools. |
| `lorisleiva/laravel-actions` | Used by `WorkspaceProfile`. |
| `tey/mod` | The lifecycle Maintenance API behind `starter:maintain`. |
| `tey/starter-blueprint` | The starter's versioned blueprint, its features and the `starter:maintain` command; bound to the host by `StarterLifecycle`. |
| `glhd/gretel`, `spatie/laravel-query-builder`, Pest, Larastan, Boost, Pail, Pao | Installed, not yet adopted by a feature. |
| `laravel/pint` | Code style. |

## Host layout

- `app/Operations`: shared operations, one per job. `WorkspaceProfile` (settings), `WorkspaceNotes`, `WorkspaceConnection` (MCP connection approval and revocation), `ComplimentaryAccess`, `ProvisionWorkspace`, `AcceptWorkspaceInvitation`.
- `app/Support`: `Access` (the one authorization check), `Audit`, `Principal`, `TenantBoundary`, `PageShell`, and the service providers.
- `app/Http`: session controllers and middleware. `app/Mcp`: the MCP server and its seven tools. `app/Adapters`: the settings UI and MCP adapters.
- `app/Data`: Data objects exposed to clients. `app/Settings`: `WorkspaceSettings`.
- `resources/js`: Inertia/Vue pages, layout and components. `resources/views`: Blade views for OAuth consent and connection approval.
- `database/migrations`: the clean schema. See [lifecycle.md](lifecycle.md).
- `config/foundation.php`: which workspace capabilities are on.

## Principles

- One operation per job. Session routes and MCP tools call the same operation, so options, effects and refusals match.
- Identity comes from the transport (session or token) as a `Principal`. Operations never accept an actor, workspace or connection as an argument.
- `Access::authorize` checks membership, account, verified email, admission, role, connection scope and capability. These stay distinct; entitlement is checked only when an operation asks for it.
- Tenant and permission context are set by `TenantBoundary` and restored in `finally`.
- Writes carry an expected version and a stable intent key. See [operations.md](operations.md).

## Not covered

Delegated initial onboarding, a production first-operator policy, jobs and files, PostgreSQL concurrency, browser acceptance and the consumer lifecycle are not implemented or not proven. See [ROADMAP.md](../ROADMAP.md).

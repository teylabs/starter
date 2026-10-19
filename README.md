# Starter

> Built for the Tey Labs fleet. Public but unsupported: no support, no stability promise, use at your own risk.

A local Laravel foundation with two synthetic workspaces. Maintained packages own their mapped jobs; no production application or database is copied.

```sh
composer install --no-interaction --no-scripts
composer fixture:check
composer types:generate
```

PHP8.4/8.5 is required by the released Concierge dependency; this fixture was exercised with Herd PHP8.5. Focused tests explicitly select SQLite `:memory:` and a synthetic encryption key. Public requests use host environment configuration and require their own application key; they never load test configuration.

`tey/concierge` is core, pinned to released0.7.1. Native workspace membership, ownership and tenant/member invitations use its released actions and migrations. The host Workspace remains the same Spatie tenant. Both synthetic tenants follow package invitation acceptance through a host callback that creates workspace/settings; no separate membership engine remains. Permission and tenant contexts compose and restore in `finally`. Membership/account/verification and delegated connection state are freshly checked for every operation and retry. Registration stays closed and local login/delivery stay disabled.

Settings use Spatie Settings, a shared operation, server navigation and generated Data DTO types. Fixture UI/MCP adapters exercise the same options, effects and refusals. Settings updates require expected version+intent key. Complimentary periods require explicit operator authority, reason, expiry and stable intent; revoke preserves history and never alters paid rows. Retrying an old grant cannot reactivate it.

The foundation includes Inertia session pages and authenticated Passport/MCP HTTP-kernel integration described below. Delegated initial onboarding and production install/bootstrap acceptance remain incomplete. Existing users are not migrated. Concierge0.7.1 establishes ownership through its supported fresh-workspace path; current main's changes are not assumed. No full suite, hosted CI, browser, Postgres concurrency, worker/file storage, provider or launch acceptance is claimed. Header/frontpage remain unimplemented pilots.

See `docs/package-map.json` for declared/locked/executed distinctions and `docs/conformance.json` for all26 independent acceptance scenario dispositions. Missing surfaces are release blockers, not waived tests. Generated files are identified in `docs/generation-manifest.json`; never regenerate over host changes without reviewing the diff.

## Session transport increment

Fortify now owns password login, throttling and logout. Registration is closed and Concierge local login remains disabled. Authenticated session routes list only active memberships, render/update workspace settings through the existing shared operation, and accept package workspace invitations. HTTP identity comes from the session, never request arguments. Sign-in, workspace selection, settings and Notes now use Inertia/Vue. OAuth consent and connection approval remain explicit Blade human handoffs; browser review and production distribution remain incomplete.

Public requests use host environment configuration and never load synthetic fixtures. Supply an application key and storage directories using the normal Laravel host setup. Secure session cookies default on; a local HTTP-only development host must explicitly choose its cookie policy. No default account/password, migrations or bootstrap data are created by the public entrypoint. The explicit framework migration command is described below; a production first-account/provisioning policy remains pending. The checked-in environment example contains no key or credentials.

Focused checks opt into `tests/environment.php` with an in-memory synthetic database. Run `vendor/bin/phpunit --bootstrap vendor/autoload.php tests/TransportTest.php` for actual HTTP-kernel session requests, including real Fortify credential validation, session cookie reuse, foreign tenant refusal, shared settings retry/stale/audit behaviour, CSRF refusal outside testing mode and logout. This does not start a network server or exercise a browser. Passport now backs the settings MCP route described below; that focused evidence does not establish the remaining distribution, product or lifecycle surfaces.

## Delegated MCP increment

Passport authorization-code with PKCE and explicit consent now authenticates the registered Laravel MCP web server. Dynamic registration and personal-token shortcuts are not enabled. A second, session-authenticated approval binds one active authorized token and a subset of its scopes to one workspace connection. Membership, active/verified account, admission, capability, signed token scopes, fresh stored token/client state and fresh connection scope/revocation/expiry are checked before access. Tools accept no actor/workspace/connection authentication argument. Read/update call the same WorkspaceProfile operation as session routes, including expected version, stable intent, audit and validation. Connection approval/revocation is audited and retries preserve receipts/history.

The token-specific binding intentionally requires renewed workspace approval after OAuth refresh or replacement. Revoke the old connection separately; an old token cannot authenticate the replacement connection. Automatic refresh continuity, delegated initial workspace onboarding and dynamic registration remain unsupported. Client configuration must request `mcp:use` and the required `settings.read` / `settings.write` scopes; package discovery advertises the base MCP scope. No automatic client registration or external callback request occurs in the fixture.

`tests/McpTransportTest.php` runs real package HTTP-kernel consent/code/token exchange and MCP JSON-RPC requests with ephemeral RSA keys, synthetic identities and no external network. It checks PKCE refusal, initialize/list/call, settings retry/stale/audit parity, foreign arguments/connections, capability/account refusal, persisted and signed scope limits, expired/revoked credentials, foreign Origin, audited revocation and renewed token-specific workspace approval. This is HTTP-kernel protocol integration, not a running network client/server or browser. Notes CRUD is covered by the following focused increment. Jobs/files, production bootstrap, complete lifecycle adoption, two consumers, PostgreSQL and hosted/full/browser checks remain incomplete. Clean-schema evidence is described below.


## Workspace Notes example

Notes now has one typed shared operation for list/read/create/update/delete through authenticated session HTTP and five dedicated MCP tools. `notes.read` / `notes.write` scopes and the separate `workspace.notes` capability are explicit. Fresh Concierge owners may write; viewers may read. The operation owns allowed arguments, validation, workspace scoping, transaction, audit, expected versions and stable intent receipts. Deleted-note retries remain stable, but a reused identity now owned by another workspace refuses without touching that row. Note content is excluded from audit details.

Focused HTTP/MCP cases cover tenant refusal, CRUD/retries, stale intent/version, viewer and capability refusal, validation and audit rollback. `docs/feature-transports.json` maps the five operations to their transports; generated Data contracts include NoteRecord and NoteChange. The Inertia pages consume the same operation results; OAuth/connection Blade handoffs remain separate. These checks use synthetic memory data and actual framework/package HTTP-kernel requests; no browser/network/PostgreSQL or production install is certified. There is still no public generic maintenance API consumed by this app; Mod work remains isolated and independently reviewed.

## Inertia client transport

Released Vue3/Inertia3 client peers are locked independently from the PHP adapter's version numbering. `npm install`, `npm run typecheck` and `npm run build` prepare the client. No SSR service is enabled. The root page and actual Inertia middleware share a generated ShellData projection: guest identity is null, workspace choices use fresh package membership, and workspace navigation uses fresh server capability/permission checks. Host branding is `APP_NAME`; the small native-control layout is a light-theme source pilot.

Login, settings and Notes forms use Inertia's form state/validation and existing session endpoints. Versions and stable intent keys come from server props; successful redirects refresh them, while refusals preserve server authority. Browser requests receive a recovery page for known domain conflicts/refusals; machine JSON retains its existing error contract. Permission props control presentation only; the operations still authorise every write. OAuth consent and connection approval remain package-backed human handoffs, with no automatic refresh continuity.

`tests/InertiaTransportTest.php` exercises real component/prop responses, guest/owner/viewer, server-owned navigation, CRUD/settings redirects, validation and stale/error outcomes through the HTTP kernel. It does not run JavaScript in a browser. PHP tests explicitly disable Vite asset loading; the separate typecheck/client build proves compilation only. Responsive, keyboard/focus, visual, cookie-policy and genuine browser-flow acceptance remain pending.


## Explicit clean schema

After installing the locked dependencies and configuring a host-owned key/database, run `php artisan migrate --no-interaction` explicitly. Public boot only registers resources; it never migrates, seeds, creates accounts, verifies email, grants operators or provisions workspaces. Registration remains closed; there are no default credentials. A production first-operator/verification-delivery policy remains unresolved. Do not use synthetic test principals as a production bootstrap.

Native host migrations create users/workspaces before package dependencies and create Notes/settings-intent/connection/grant/audit tables after Concierge. Permission and Settings table creation uses the locked packages' published migration resources. Laravel loads Passport's actual package migrations, while Concierge loads its own native migrations exactly once. Package internals are not copied into host membership/OAuth schemas. Schema migration IDs belong to Laravel's migration repository; file-blueprint migration declarations do not claim to have executed them.

`vendor/bin/phpunit --bootstrap vendor/autoload.php tests/MigrationTest.php` exercises actual framework migration against an explicitly empty SQLite-memory database, verifies ordering, zero seeded application data, and a repeated no-op. It then explicitly creates synthetic verified test principals, syncs package roles and uses normal Concierge invitation/provisioning and shared settings/Notes operations for two tenants. Repeated migration preserves that data. These are targeted local schema/package checks, not PostgreSQL concurrency, production first-account/email delivery, rollback/data conversion, consumer lifecycle, browser or full-suite proof.

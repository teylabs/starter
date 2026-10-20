# Transports

Two transports reach the same operations ([operations.md](operations.md)): authenticated session pages and an OAuth-protected MCP server. Neither accepts an actor, workspace or connection as an operation argument.

## Session and Inertia

Fortify handles password login, throttling and logout. Registration is closed and there are no default credentials. Session routes require `auth:web` and the `concierge.active` middleware.

A workspace's pages live under `/@{workspace}/...`: the `@` is the only tenant marker, so the rest of the root belongs to the app. Personal, cross-workspace pages live under `/me`. Every workspace page also answers at a short path without `/@{workspace}` (`/notes`), which means the session's current workspace, or the only one; with several workspaces and none current it goes to the chooser. The UI links to the short form for a user with one workspace and the full form for several; mail, exports and shared links always use the full form (`Tey\Kit\Features\Workspaces\WorkspaceUrl`). The old `/workspaces` and `/workspaces/{workspace}/...` URLs redirect permanently.

| Route | Purpose |
| --- | --- |
| `GET /me`, `GET /me/workspaces` | The personal landing page, listing the personal sections; the workspaces with an active membership (a user with one is sent into it) |
| `GET /@{workspace}/settings` | The settings landing page, listing the registered sections the user may open |
| `GET`/`PUT /@{workspace}/settings/general` | Read and update the workspace settings |
| `GET`/`POST /@{workspace}/notes`, `GET`/`PUT`/`DELETE .../notes/{note}` | Notes |
| `POST /tenant-invitations/accept` | Accept a workspace invitation |
| `GET`/`POST /@{workspace}/settings/connections`, `DELETE .../connections/{connection}` | List, approve and revoke MCP connections |

Pages are Inertia/Vue (`resources/js/pages/<Resource>/<Action>/Page.vue`, wrapped by `resources/js/layouts`): sign-in, workspaces, settings and Notes. The same routes return JSON when the request expects JSON without an `X-Inertia` header. The shared `shell` prop (`PageShell`, typed as `ShellData`) carries the identity, workspace choices and navigation, each checked server-side. Permission props only control presentation; operations authorize every write. Versions and intent keys arrive as props and refresh after a successful redirect.

OAuth consent and connection approval are Blade views, not Inertia pages. No SSR service is enabled.

## MCP and OAuth

The mcp feature's routes (tey/kit) register Passport OAuth routes without dynamic client registration, and the MCP server at `/mcp/connections/{connection}`.

### Flow

1. The client runs authorization code with PKCE. The signed-in user sees a consent page naming the requested scopes. Consent alone grants no workspace access.
2. The user then approves a connection for one workspace: a token id plus a subset of scopes (`POST /@{workspace}/settings/connections`). `settings.read` is required.
3. The client calls `/mcp/connections/{connection}` with the bearer token.

Tokens expire after one hour. A connection is bound to one token, so refreshing or replacing the token needs a new workspace approval; revoke the old connection separately.

### Scopes

| Scope | Allows |
| --- | --- |
| `mcp:use` | Use the MCP server (required on every token) |
| `settings.read`, `settings.write` | Read and update settings |
| `notes.read`, `notes.write` | Read, and create/update/delete, Notes |

A request is allowed only if the ability is in both the token's scopes and the connection's approved scopes, and the user's role and the workspace capability allow it.

### Checks on every request

Middleware (`BindMcpConnection`) refuses the request unless the token is stored, unrevoked, unexpired and carries `mcp:use`; its client is unrevoked and uses the authorization-code grant; any `Origin` header matches `APP_URL`; and the connection exists, belongs to the user, is bound to this token, and is unrevoked and unexpired. Then the operation re-checks membership, account, admission, capability and connection scope.

### Tools

`workspace_settings_read`, `workspace_settings_update`, `workspace_notes_list`, `workspace_note_read`, `workspace_note_create`, `workspace_note_update`, `workspace_note_delete`. Arguments mirror the operations. Writes need `expected_version` where applicable and an `intent_key`. Results are structured; failures return the operation's error code.

### Client configuration

Request `mcp:use` plus the scopes you need. Package discovery advertises only the base MCP scope. Nothing registers clients or calls external services for you.

## Not covered

Automatic refresh continuity, dynamic client registration, personal access tokens, browser acceptance and a running network server are not part of the checked behaviour.

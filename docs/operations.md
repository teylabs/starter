# Operations

Settings and Notes are shared operations in `app/Operations`. Session routes and MCP tools both call them, so validation, effects and refusals are the same on every transport. Each operation takes a `Principal` supplied by the transport, never by the caller's arguments.

## Authorization

Every call runs `Access::authorize($principal, $ability)` inside the workspace context. Abilities are `settings.read`, `settings.write`, `notes.read` and `notes.write`. The check fails closed, in this order:

1. The user is an active member of the workspace (`workspace_not_found`).
2. The account is active (`account_inactive`) and the email is verified (`verification_required`).
3. The workspace is admitted (`admission_required`).
4. Write abilities require the workspace owner (`permission_denied`). Viewers can read.
5. For an MCP connection, the connection is unrevoked and the ability is within its approved scopes (`connection_denied`).
6. The capability is on: `workspace.settings` for settings, `workspace.notes` for Notes (`capability_unavailable`). See `config/foundation.php`.
7. If the caller asks for it, a paid or complimentary entitlement exists (`entitlement_required`).

Capabilities, entitlements, membership and admission are separate checks.

## Settings: `WorkspaceProfile`

- `read` returns display name, timezone and the current `settings_version`.
- `update` takes `display_name` (max 120), `timezone` (a valid timezone), `expected_version` and `intent_key`. Any other argument is refused (`unknown_arguments`).

## Notes: `WorkspaceNotes`

- `listing` and `read` (by `id`) are read operations.
- `mutate` handles `create`, `update` and `delete`. `body` is required for create and update (max 2000). `id` and `expected_version` are required for update and delete. `intent_key` is always required.
- Notes are scoped to the current workspace. Note content is not written to the audit log.

## Versions

Settings carry `settings_version` on the workspace; each Note carries `version`, starting at 1. A write whose `expected_version` differs from the stored version is refused and changes nothing (`stale_settings`, `stale_note`). Success increments the version.

## Intent keys

An intent key (max 120 characters) makes a write safe to retry. The operation stores the key with a fingerprint of the request and the result, per workspace and actor, in `settings_intents` and `note_intents`.

- Same key and same request: the saved result is returned and nothing is written again.
- Same key and a different request: refused (`intent_conflict`, `note_intent_conflict`).
- A saved Note result that now points at a row in another workspace is refused (`workspace_not_found`) without touching that row.

Generate a new key per user action and reuse it only to retry that action.

## Complimentary access: `ComplimentaryAccess`

An operator can grant a workspace a complimentary period with a reason, a future expiry and an intent key. There is one active period per workspace. Revoking records who and why and leaves history. A retried grant never reactivates a revoked or expired one, and paid entitlements are not changed. Operators must be verified, active and recorded in `operators`. There is no transport for this operation yet.

## MCP connections: `WorkspaceConnection`

`approve` binds one active OAuth token and a subset of its scopes (`settings.read` is required) to the current workspace; `revoke` ends the connection. Both are audited. See [transports.md](transports.md).

## Audit

`Audit::write` inserts a row into `audit_events` with actor, workspace, event name, JSON details and time, inside the operation's transaction. Events:

- `workspace.settings.updated`
- `workspace.note.create`, `workspace.note.update`, `workspace.note.delete`
- `workspace.connection.approved`, `workspace.connection.revoked`
- `complimentary.granted`, `complimentary.revoked`

A rolled-back write leaves no audit row.

## Errors

Refusals are `RuntimeException` messages. Over HTTP the host maps them to statuses: 404 (`workspace_not_found`, `note_not_found`), 403 (access refusals), 409 (stale versions and intent conflicts), 422 (`unknown_arguments`). JSON requests get `{"error": "<code>"}`; browser requests get an Inertia error page. MCP tools return an error response with the same code.

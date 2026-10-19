# Starter

Guidance for contributors and coding agents working in this repository.

- Preserve the tenant, ownership, grant and stale-write regression tests.
- Focused checks explicitly opt into synthetic SQLite memory. Public requests must never seed fixtures, bypass auth, or use default credentials or keys.
- No external services, production data or provider side effects in tests.
- Fail closed, and restore tenant, permission and settings context in `finally` blocks.
- Keep capabilities, entitlements, membership and admission distinct.
- State missing Inertia, protocol, lifecycle or platform coverage explicitly rather than implying it.

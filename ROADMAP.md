# Roadmap

1.0 means `laravel new app --using=tey/starter` gives you a tenant-aware Laravel app with:

- workspaces, membership and invitations;
- session (Inertia) and delegated MCP/OAuth access to the same operations;
- an upgrade path that applies new starter versions to your app without overwriting your changes.

At 1.0 its public API, lifecycle contract and support policy are documented and frozen.

**Versions are milestones, not dates.**

## Shipped

- [x] **v0.1 — A tenant-aware foundation.** Concierge workspaces and invitations, Spatie tenant and permission context, versioned settings and grants, Fortify sessions with Inertia/Vue pages, Passport PKCE and MCP tools for settings and Notes, and explicit clean-schema migrations. Untagged; the first tag is v0.2.

## The countdown to 1.0

- [ ] **[v0.2 — Installs and upgrades from released packages](https://github.com/teylabs/starter/milestone/1).** Built on Mod 0.8's lifecycle and starter-blueprint 0.1. You install with `laravel new --using=tey/starter` and upgrade with `starter:maintain`, and it's published on Packagist.
- [ ] **[v0.3 — Production first run](https://github.com/teylabs/starter/milestone/2).** A fresh install creates its first operator and verifies email, with no default credentials.
- [ ] **[v0.4 — Real browsers and databases](https://github.com/teylabs/starter/milestone/3).** Browser, mobile, accessibility and PostgreSQL concurrency acceptance, with full hosted CI.
- [ ] **[v0.5 — Wider isolation](https://github.com/teylabs/starter/milestone/4).** Jobs, files, cache and search stay inside their workspace.
- [ ] **[v0.6 — Upgrade automation](https://github.com/teylabs/starter/milestone/5).** Starter upgrades arrive as pull requests, and orphaned files are handled safely.
- [ ] **[1.0 — Stable](https://github.com/teylabs/starter/milestone/6).** Frozen API and lifecycle contract, a versioning and support policy, complete docs and a release candidate.
- [ ] **[1.x](https://github.com/teylabs/starter/milestone/7).** After 1.0, in no particular order.

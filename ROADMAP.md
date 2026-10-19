# Roadmap

tey/starter is a set of features for tenant-aware Laravel apps. You can adopt them into the app you already have, or start a new app with all of them.

- **Existing app:** `composer require tey/starter`, then `php artisan starter:adopt <features>`. Each feature declares the files it owns, what it requires and what it conflicts with. Adoption inspects your app, shows a plan and never overwrites your edits.
- **New app:** `laravel new app --using=tey/starter` installs every feature.
- **Upgrades:** `php artisan starter:maintain` upgrades what you adopted the same way.

1.0 means the feature catalog, adoption and upgrade paths, public API and support policy are documented and frozen.

**Versions are milestones, not dates.**

## Shipped

- [x] **v0.1 — A tenant-aware foundation.** Concierge workspaces and invitations, Spatie tenant and permission context, versioned settings and grants, Fortify sessions with Inertia/Vue pages, Passport PKCE and MCP tools for settings and Notes, and explicit clean-schema migrations. Untagged; the first tag is v0.2.
- [x] **[v0.2 — Installs and upgrades from released packages](https://github.com/teylabs/starter/milestone/1).** Built on released Mod 0.8, starter-blueprint 0.1 and Concierge 0.7.2. `starter:maintain` plans, applies and resumes blueprint upgrades without overwriting your own files; CI, an MIT license and agent docs (`llms.txt`) ship with it.

## The countdown to 1.0

- [ ] **[v0.3 — Adopt features into an existing app](https://github.com/teylabs/starter/milestone/8).** A feature catalog with per-feature manifests, and `starter:adopt` to inspect, plan and apply features into an app that didn't start from the starter, proven in a mature production app.
- [ ] **[v0.4 — Production first run](https://github.com/teylabs/starter/milestone/2).** A fresh install creates its first operator and verifies email, with no default credentials.
- [ ] **[v0.5 — Real browsers and databases](https://github.com/teylabs/starter/milestone/3).** Browser, mobile, accessibility and PostgreSQL concurrency acceptance, with full hosted CI.
- [ ] **[v0.6 — Wider isolation](https://github.com/teylabs/starter/milestone/4).** Jobs, files, cache and search stay inside their workspace.
- [ ] **[v0.7 — Upgrade automation](https://github.com/teylabs/starter/milestone/5).** Starter upgrades arrive as pull requests, and orphaned files are handled safely.
- [ ] **[v0.8 — UI kit](https://github.com/teylabs/starter/milestone/9).** A UI kit with the proven look and feel, free of third-party commercial components, styled with Tailwind only.
- [ ] **[1.0 — Stable](https://github.com/teylabs/starter/milestone/6).** Frozen API, adoption and lifecycle contract, a versioning and support policy, complete docs and a release candidate.
- [ ] **[1.x](https://github.com/teylabs/starter/milestone/7).** After 1.0, in no particular order.

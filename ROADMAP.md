# Roadmap

tey/starter is the new-app template for tenant-aware Laravel apps: `laravel new app --using=tey/starter`. It is built on [tey/kit](https://github.com/teylabs/kit), the library that adds the same features to an app you already have (`composer require tey/kit` and `php artisan kit:add <features>`).

1.0 means a new app gets every kit feature, upgrades with `kit:update` without overwriting your changes, and the template's public API and support policy are documented and frozen.

**Versions are milestones, not dates.**

## Shipped

- [x] **v0.1 — A tenant-aware foundation.** Concierge workspaces and invitations, Spatie tenant and permission context, versioned settings and grants, Fortify sessions with Inertia/Vue pages, Passport PKCE and MCP tools for settings and Notes, and explicit clean-schema migrations. Untagged; the first tag is v0.2.
- [x] **[v0.2 — Installs and upgrades from released packages](https://github.com/teylabs/starter/milestone/1).** Built on released Mod 0.8, starter-blueprint 0.1 and Concierge 0.7.2. `starter:maintain` plans, applies and resumes blueprint upgrades without overwriting your own files; CI, an MIT license and agent docs (`llms.txt`) ship with it.

## The countdown to 1.0

- [ ] **[v0.3 — Built on tey/kit](https://github.com/teylabs/starter/milestone/8).** The template becomes tey/kit with every feature added, checked in CI against `kit:add --all`; feature adoption for existing apps lives in tey/kit.
- [ ] **[v0.4 — Production first run](https://github.com/teylabs/starter/milestone/2).** A fresh install creates its first operator and verifies email, with no default credentials.
- [ ] **[v0.5 — Real browsers and databases](https://github.com/teylabs/starter/milestone/3).** Browser, mobile, accessibility and PostgreSQL concurrency acceptance, with full hosted CI.
- [ ] **[v0.6 — Wider isolation](https://github.com/teylabs/starter/milestone/4).** Jobs, files, cache and search stay inside their workspace.
- [ ] **[v0.7 — Upgrade automation](https://github.com/teylabs/starter/milestone/5).** Starter upgrades arrive as pull requests, and orphaned files are handled safely.
- [ ] **[v0.8 — UI kit](https://github.com/teylabs/starter/milestone/9).** A UI kit with the proven look and feel, free of third-party commercial components, styled with Tailwind only.
- [ ] **[1.0 — Stable](https://github.com/teylabs/starter/milestone/6).** Frozen API, adoption and lifecycle contract, a versioning and support policy, complete docs and a release candidate.
- [ ] **[1.x](https://github.com/teylabs/starter/milestone/7).** After 1.0, in no particular order.

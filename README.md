# jperdior-template

[![CI](https://github.com/jperdior/jperdior-template/actions/workflows/ci.yml/badge.svg)](https://github.com/jperdior/jperdior-template/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![Symfony 7.4](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![Next.js 15](https://img.shields.io/badge/Next.js-15-000000?logo=next.js&logoColor=white)](https://nextjs.org)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/jperdior/jperdior-template/pulls)

My personal project template inspired by https://github.com/open-mercato/open-mercato and with my personal preferences in DDD architecture.

It comes with my preferred stack (PHP 8.4 + Symfony 7.4, DDD + Hexagonal + CQRS, Next.js 15 frontends) and a `User` bounded context already in place — sign-up, JWT auth, role management. The architecture conventions and CI are set up from the start, so you're not doing that per project.

The `.ai/` harness is the other main thing: specs, skills, and review gates that give an AI agent enough context to contribute meaningfully without going off-rails.

---

## What's included

### User bounded context

Most projects need user accounts. Rather than building that from scratch every time, this template ships a complete `User` context you can use as-is or extend:

- **Auth** — sign-up, login, JWT access token + refresh token rotation, httponly cookie strategy on the frontend
- **Roles** — `ROLE_USER` (default) and `ROLE_ADMIN`; a dev admin (`admin@example.com` / `!pw4template`) is auto-seeded on first boot, or promote any user via `make seed-admin`
- **Admin user management** — full CRUD in the admin panel: create, list (paginated, includes soft-deleted), detail, role update, soft delete, restore, force password reset
- **Password reset gate** — web app redirects to `/reset-password` when the flag is set; clears on next login
- **Admin panel** — Next.js admin at `admin.localhost` with a user list, action menus, and a detail page

The `User` context is also the **reference implementation**: every layer, naming convention, and pattern in the codebase is demonstrated here. When you add a new bounded context, mirror it.

→ See [docs/auth.md](docs/auth.md) for the JWT flow, refresh rotation, and cookie strategy.

### AI skills

The template ships a set of slash commands for Claude Code (and Codex) that already know the conventions of this codebase. After setup, type `/` in Claude Code to see the full list.

**Workflow:**
- `/init` — bootstrap a fresh clone: check prerequisites, copy `.env.local`, personalize the project
- `/customize-project` — rename all template placeholders and add your project description to `AGENTS.md` — run once after cloning
- `/new-feature` — create an isolated git worktree + branch from main for your feature
- `/spec-writing` — brainstorm and write a feature spec, auto-proceeds to audit
- `/pre-implement-spec` — audit a spec for gaps, BC risks, and missing tests before coding
- `/implement-spec` — implement an approved spec phase by phase with CI gate between phases
- `/code-review` — review a diff or branch against DDD/CQRS/security rules; runs the CI gate
- `/open-pr` — open a single PR (spec + implementation) from the feature branch

**Backend development:**
- `/scaffold-bounded-context` — generate the full 4-layer DDD skeleton for a new context
- `/add-command`, `/add-query`, `/add-route` — add CQRS commands, queries, and HTTP endpoints
- `/scaffold-doctrine-migration` — generate and review a Doctrine migration
- `/regenerate-api-client` — regenerate the TypeScript API client from the OpenAPI spec

**Frontend development:**
- `/scaffold-nextjs-page`, `/scaffold-shadcn-form` — scaffold frontend pages and forms
- `/integration-tests` — create PHPUnit functional tests and Vitest frontend tests

**Bug fixing:**
- `/root-cause` — drill from a failing test or production error to the offending change
- `/fix` — regression test first, then minimal fix, CI gate, auto-create PR

**Support:**
- `/parallel-research` — spawn multiple agents in parallel to map unfamiliar code before touching it
- `/sync-context-docs` — update AGENTS.md and cross-cutting docs after implementation

→ See [docs/ai-workflow.md](docs/ai-workflow.md) for the full spec-first development workflow.

### Development workflow

The recommended flow for any non-trivial feature:

1. `/new-feature feat-<slug>` — create a worktree + branch from main
2. `/spec-writing` — design the feature, produce a spec doc in `.ai/specs/` (auto-proceeds to audit)
3. `/pre-implement-spec` — audit the spec for gaps, BC risks, and missing tests
4. `/implement-spec` — implement from the approved spec, phase by phase (CI + code-review after each)
5. `/sync-context-docs` — update AGENTS.md and cross-cutting docs (runs per-phase inside implement-spec)
6. `/open-pr` — single PR (spec + code) to main
7. **Clean up** after merge — remove worktree, prune branch

For bug fixes: `/root-cause` → `/fix` → `/auto-create-pr`.

→ See [AGENTS.md](AGENTS.md) for the full task router and AI conventions.

---

## Stack

| Layer | Technology |
|-------|-----------|
| API | PHP 8.4 + Symfony 7.4 |
| Architecture | DDD + Hexagonal + CQRS, modular monolith |
| Auth | Lexik JWT + Gesdinet refresh-token rotation |
| Persistence | PostgreSQL 16, Doctrine 3 (Persistence Model pattern) |
| Queue | Symfony Messenger — sync by default, RabbitMQ-ready via `--profile async` |
| Cache / Locks | Redis 7 |
| Public frontend | Next.js 15 App Router, TypeScript strict, Tailwind, shadcn/ui |
| Admin panel | Next.js 15, same stack, gated to `ROLE_ADMIN` |
| API client | Auto-generated TypeScript from OpenAPI spec |
| Containers | Docker Compose v2 + Traefik (local routing) |

---

## Architecture

The API follows **DDD + Hexagonal Architecture + CQRS** organised as a modular monolith. Every feature lives in a bounded context under `apps/api/src/<Context>/`, with four strict layers:

- **Domain** — pure PHP: aggregates, value objects, repository interfaces, domain events. No framework code allowed here.
- **Application** — use cases as Commands and Queries dispatched through a bus. Handlers are framework-agnostic.
- **Infrastructure** — Doctrine repositories with `*Model` persistence classes (PHP attributes on infrastructure models, never on domain entities), external adapters.
- **Presentation** — Symfony controllers and request DTOs. Thin: validate input, dispatch to bus, return response.

Cross-context communication goes through the event bus only — direct imports between contexts are forbidden. [`deptrac`](https://github.com/qossmic/deptrac) enforces this in CI.

**Why this structure?** CQRS keeps reads and writes separate so they can evolve independently. Hexagonal keeps the domain free of framework coupling so it's testable in isolation. The modular monolith gives the clarity of bounded contexts without the operational overhead of microservices — and it's straightforward to extract a service later if you need to.

→ See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full rationale, layer rules, and the three buses (command, query, event).

---

## Quickstart

See **[docs/getting-started.md](docs/getting-started.md)** for the full walkthrough — prerequisites, first boot, secrets, service URLs, and daily commands.

```sh
git clone <this repo> my-new-project
cd my-new-project
sudo make init   # patches /etc/hosts, installs AI skills
```

Then run `/init` in Claude Code to check prerequisites, copy `.env.local`, and personalize the project. Or say **"customize my project"** to rename template placeholders and add your project description.

No need for `make start` during development — `make test` / `make lint` auto-start a headless per-worktree stack. Use `make start` only when you need browser preview.

Once the stack is up, sign into the admin panel at `admin.localhost` with the auto-seeded dev admin — **`admin@example.com` / `!pw4template`** (dev-only; the seeder refuses to run in `prod` — change it before deploying). See [docs/getting-started.md §6](docs/getting-started.md) for details.

---

## Documentation

| Guide | What's in it |
|-------|-------------|
| [docs/getting-started.md](docs/getting-started.md) | From clone to first endpoint — prerequisites, boot, secrets, daily commands |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | DDD + Hexagonal + CQRS rationale, the four layers, the three buses |
| [docs/persistence.md](docs/persistence.md) | Database schema, naming conventions, *Model pattern, repository pattern, migrations |
| [docs/auth.md](docs/auth.md) | JWT flow, refresh rotation, frontend cookie strategy |
| [docs/adding-a-bounded-context.md](docs/adding-a-bounded-context.md) | Step-by-step guide for adding a new context |
| [docs/ai-workflow.md](docs/ai-workflow.md) | Spec-first AI-driven development with the `.ai/` harness |
| [docs/ops.md](docs/ops.md) | Docker setup, environment variables, CI pipeline |
| [AGENTS.md](AGENTS.md) | Task router — the first file an AI agent reads before any coding |

---

MIT licensed. See [LICENSE](LICENSE).

# jperdior-template

**A spec-driven, AI-engineered monorepo template you can start a real product on today.**

It pairs a PHP 8.4 + Symfony 7.4 API — built on **DDD + Hexagonal Architecture + CQRS** as a
modular monolith — with **Next.js 15** public and admin frontends, and wraps the whole thing in
an AI harness of specs, skills, and CI review gates so an agent can contribute without going
off the rails.

The goal: skip the boilerplate you rewrite on every project. Auth, a clean architecture, a
generated API client, containers, and CI are all in place from commit one, so you start on
*your* domain instead of the scaffolding.

!!! tip "New here? Start with [Getting Started](getting-started.md)"
    Clone, boot the stack, and hit your first endpoint. Then read
    [Architecture](ARCHITECTURE.md) to understand the layers before you add code.

## What's in the box

- **A complete `User` bounded context** — sign-up, login, JWT with refresh-token rotation, an
  httponly-cookie strategy on the frontend, `ROLE_USER`/`ROLE_ADMIN`, and full admin user
  management (create, paginated list, detail, role update, soft delete, restore, forced
  password reset). Use it as-is or extend it — and mirror it when you add your own contexts,
  because it's also the **reference implementation** for every convention in the codebase.
- **A modular-monolith architecture that stays honest** — four strict layers per context,
  cross-context communication only through the bus, enforced in CI by [`deptrac`](https://github.com/qossmic/deptrac).
- **Two Next.js 15 apps** — an internationalized public web app and a `ROLE_ADMIN`-gated admin
  panel, sharing a `ui-react` component library and an auto-generated, refresh-token-aware
  TypeScript API client.
- **An AI harness (`.ai/`)** — specs, slash-command skills, and verification gates that encode
  this codebase's conventions, so AI-assisted work is spec-first and reviewable.

## The stack at a glance

| Layer | Technology |
|-------|-----------|
| API | PHP 8.4 + Symfony 7.4 |
| Architecture | DDD + Hexagonal + CQRS, modular monolith |
| Auth | Lexik JWT + Gesdinet refresh-token rotation |
| Persistence | PostgreSQL 16, Doctrine 3 (Persistence Model pattern) |
| Queue | Symfony Messenger — sync by default, RabbitMQ-ready |
| Cache / Locks | Redis 7 |
| Frontends | Next.js 15 App Router, TypeScript strict, Tailwind, shadcn/ui |
| API client | Auto-generated TypeScript from the OpenAPI spec |
| Containers | Docker Compose v2 + Traefik |

## How the architecture fits together

Every feature lives in a bounded context under `apps/api/src/<Context>/`, split into four layers
that never leak into each other:

- **Domain** — pure PHP: aggregates, value objects, repository interfaces, domain events. No
  framework code, ever.
- **Application** — use cases as Commands and Queries dispatched through a bus; handlers stay
  framework-agnostic.
- **Infrastructure** — Doctrine repositories with `*Model` persistence classes (ORM attributes
  live here, never on domain entities), plus external adapters.
- **Presentation** — thin Symfony controllers: validate input, dispatch to the bus, return a
  response.

CQRS keeps reads and writes evolving independently; hexagonal keeps the domain testable in
isolation; the modular monolith gives bounded-context clarity without microservice overhead —
and leaves the door open to extract a service later.

[Read the full architecture rationale →](ARCHITECTURE.md){ .md-button }

## The AI workflow

Non-trivial features follow a spec-first loop, each step a slash command that already knows the
codebase's conventions:

`/new-feature` → `/spec-writing` → `/pre-implement-spec` → `/implement-spec` (CI + review per
phase) → `/open-pr`. Bug fixes take the shorter `/root-cause` → `/fix` path.

[See the full AI workflow →](ai-workflow.md){ .md-button }

## Explore the guides

<div class="grid cards" markdown>

-   :material-rocket-launch: **[Getting Started](getting-started.md)**

    Clone, boot the stack, seed the dev admin, and run the app locally.

-   :material-sitemap: **[Architecture](ARCHITECTURE.md)**

    The four layers, the three buses, and why the structure holds.

-   :material-cube-outline: **[Adding a Bounded Context](adding-a-bounded-context.md)**

    Scaffold a new context by mirroring the `User` reference implementation.

-   :material-shield-key: **[Auth](auth.md)**

    JWT flow, refresh-token rotation, and the frontend cookie strategy.

-   :material-database: **[Persistence](persistence.md)**

    Schema conventions, the `*Model` pattern, repositories, and migrations.

-   :material-transit-connection-variant: **[Domain Events](domain-events.md)**

    Cross-context communication via events and bus-dispatched commands.

-   :material-robot: **[AI Workflow](ai-workflow.md)**

    The spec → implement → review harness that keeps agents on the rails.

-   :material-server: **[Ops](ops.md)**

    Docker, Compose, environment variables, and the CI pipeline.

</div>

Recorded architectural decisions live under [Decisions (ADR)](adr/0001-keep-handler-usecase-split.md).

---

Source lives at [github.com/jperdior/jperdior-template](https://github.com/jperdior/jperdior-template).
These pages render `docs/*.md` directly — that folder is the single source of truth.

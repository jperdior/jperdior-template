# jperdior-template

A **spec-driven, AI-engineered monorepo template**: a PHP 8.4 + Symfony 7.4 API
(DDD + Hexagonal + CQRS) alongside Next.js 15 frontends, with an AI harness of
specs, skills, and review gates.

It ships a complete `User` bounded context — sign-up, JWT auth with refresh-token
rotation, role management, and an admin panel — that doubles as the **reference
implementation** every new context mirrors.

## Start here

<div class="grid cards" markdown>

-   :material-rocket-launch: **[Getting Started](getting-started.md)**

    Clone, boot the stack, and run the app locally.

-   :material-sitemap: **[Architecture](ARCHITECTURE.md)**

    The DDD + Hexagonal + CQRS layering and bounded-context boundaries.

-   :material-cube-outline: **[Adding a Bounded Context](adding-a-bounded-context.md)**

    Scaffold the four layers by mirroring the `User` reference context.

-   :material-robot: **[AI Workflow](ai-workflow.md)**

    The spec → implement → review harness that keeps agents on the rails.

</div>

## Reference guides

- **[Auth](auth.md)** — User aggregate, JWT, refresh tokens, `security.yaml`.
- **[Persistence](persistence.md)** — schema conventions, the `*Model` pattern, repositories, migrations.
- **[Domain Events](domain-events.md)** — cross-context communication via events and the bus.
- **[Ops](ops.md)** — Docker, Compose, and Kubernetes.
- **[Decisions (ADR)](adr/0001-keep-handler-usecase-split.md)** — recorded architectural decisions.

---

Source lives at [github.com/jperdior/jperdior-template](https://github.com/jperdior/jperdior-template).
These pages render `docs/*.md` directly — that folder is the single source of truth.

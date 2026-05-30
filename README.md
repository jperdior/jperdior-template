# jperdior-template

[![CI](https://github.com/jperdior/jperdior-template/actions/workflows/ci.yml/badge.svg)](https://github.com/jperdior/jperdior-template/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![Symfony 7.4](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![Next.js 15](https://img.shields.io/badge/Next.js-15-000000?logo=next.js&logoColor=white)](https://nextjs.org)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/jperdior/jperdior-template/pulls)

An opinionated multipurpose monorepo starter. PHP 8.4 + Symfony 7.4 API with strict DDD + Hexagonal + CQRS, Next.js 15 frontends, and a full AI engineering harness. Ships with a single `User` bounded context (sign-up, JWT auth, role management) — everything else you build on top.

The AI harness (specs, skills, code-review gates, PR automation) is ported from [open-mercato](https://github.com/open-mercato/open-mercato). The backend architecture reflects my own preferences for PHP DDD + CQRS that I've developed over years of building and maintaining production services: bounded contexts with four strict layers, XML-only Doctrine mapping, three Messenger buses, value-object-first domain models, and explicit separation between the Messenger handler (framework glue) and the use case (framework-free logic).

---

## Stack

| Layer | Technology |
|-------|-----------|
| API | PHP 8.4 + Symfony 7.4 |
| Architecture | DDD + Hexagonal + CQRS, modular monolith |
| Auth | Lexik JWT + Gesdinet refresh-token rotation (single-use) |
| Persistence | PostgreSQL 16, Doctrine 3 (XML mapping, no ORM attributes on entities) |
| Queue | Symfony Messenger, sync by default — RabbitMQ (AMQP) ready via `--profile async` |
| Cache / Locks | Redis 7 |
| Public frontend | Next.js 15 App Router, TypeScript strict, Tailwind, shadcn/ui |
| Admin panel | Next.js 15 App Router, same stack, gated to `ROLE_ADMIN` |
| API client | Auto-generated TypeScript from OpenAPI spec |
| Reverse proxy | Nginx (API) + Traefik (local routing) |
| Containers | Docker Compose v2 |

---

## Quickstart

```sh
git clone <this repo> my-new-project
cd my-new-project
make init
```

`make init` copies `.env.dist` → `.env.local`, patches `/etc/hosts` for local subdomains, and starts the stack. After ~30 seconds:

| URL | Service |
|-----|---------|
| `http://api.localhost` | Symfony API |
| `http://api.localhost/api/doc` | Swagger UI |
| `http://web.localhost` | Next.js public app |
| `http://admin.localhost` | Next.js admin panel |
| `http://localhost:8080` | Traefik dashboard |

Create the first admin account:

```sh
make seed-admin EMAIL=you@example.com
```

---

## Repo layout

```
apps/
  api/          Symfony 7.4 — all bounded contexts under src/<Context>/
  web/          Next.js 15 public app
  admin/        Next.js 15 back-office (ROLE_ADMIN only)
packages/
  shared-kernel-php/   Pure-PHP DDD primitives (AggregateRoot, bus interfaces, value objects)
  ui-react/            Shared shadcn/ui components + Tailwind preset + design-system tokens
  api-client-ts/       Generated TS client + Next.js server-side cookie/refresh wrapper
ops/
  docker/       Per-service Dockerfiles, Nginx config, Compose files (base + dev overlay)
  k8s/          Helm chart skeleton
  ci/           Shared CI scripts (lint, test, build)
.ai/            Specs, skills, QA harness, lessons — the AI engineering harness
docs/           Deep-dive guides
AGENTS.md       Task router — the first file to read before any coding
Makefile        Single entry point for all development operations
```

---

## Common commands

| Command | What it does |
|---------|-------------|
| `make start` | Build images, start stack, tail logs |
| `make stop` | Stop and remove containers |
| `make logs` | Tail all container logs |
| `make api-shell` | Shell inside the API container |
| `make test` | PHPUnit (unit + functional) + pnpm test |
| `make lint` | PHPStan + cs-fixer + deptrac + tsc + eslint |
| `make migrate` | Apply pending Doctrine migrations |
| `make migrate-diff` | Generate a Doctrine migration from entity changes |
| `make setup-test-db` | Create and migrate the test database |
| `make gen-api` | Regenerate the TypeScript API client from OpenAPI spec |
| `make jwt-keys` | Generate JWT keypair (done automatically on first boot) |
| `make seed-admin EMAIL=x` | Promote a user to `ROLE_ADMIN` |
| `make db-reset` | Drop + recreate + migrate (local dev only) |

Run `make help` for the full list.

---

## Architecture overview

The API is a **modular monolith**: one Symfony app, many bounded contexts. This is a deliberate choice over microservices — boundaries are enforced in code by `deptrac`, not over a network. Adding a context is dropping a folder. The shared Postgres connection pool makes transactions trivial. And because contexts already communicate via the event bus, extracting one later is a mechanical change to the transport layer, not the domain.

Every context under `apps/api/src/<Context>/` has four layers:

```
<Context>/
├── Domain/          Pure PHP — aggregates, value objects, repository interfaces
├── Application/     CQRS — Commands, Queries, Handlers, UseCases
├── Infrastructure/  Adapters — Doctrine repos, DBAL types, Symfony services
└── Presentation/    HTTP controllers, CLI commands
```

Three Messenger buses handle all in-process communication:

- **command.bus** — writes (sync by default, async-capable via Doctrine transport)
- **query.bus** — reads (always sync)
- **event.bus** — domain events (sync or async per subscriber)

### Handler + UseCase separation

Each command and query has both a `*Handler` (Messenger glue) and a `*UseCase` (framework-free logic). The handler is a one-liner that delegates to the use case. This looks like ceremony but it pays for itself: the use case can be unit-tested without booting the bus, invoked from a CLI command, or wrapped with cross-cutting concerns (transactions, metrics, audit logging) at the handler level without touching domain logic. Consistency matters more than brevity — every entry point follows `Controller → Bus → Handler → UseCase → Domain`.

### XML Doctrine mapping

Doctrine is mapped in XML (`.orm.xml` files), not PHP attributes. The reason: `#[ORM\Entity]` on a domain entity imports Doctrine into the domain layer. XML mapping lives in `Infrastructure/Persistence/Doctrine/Mapping/`, one file per aggregate. The domain entity stays plain PHP.

The alternative — a double-entity pattern with a Doctrine entity in `Infrastructure/` and a domain aggregate with `toDomain()` / `toOrm()` translators — also works and keeps attributes in the attribute-friendly `Infrastructure/` layer. The cost is maintaining two entity classes and a translation layer per aggregate. XML pays the same purity cost in one mapping file instead of a parallel class hierarchy. For this template, "one class + one XML file" beats "two classes + translation" on duplication.

### Custom DBAL types for value objects

PHP 8.4's lazy-ghost objects (enabled in Doctrine 3) enforce typed property assignment strictly. If an entity property is typed `Email` and Doctrine tries to hydrate a raw string into it, you get a `TypeError`. The fix: custom DBAL types that convert between the DB primitive and the value object at the persistence boundary. Every context registers its types in `config/packages/doctrine.yaml` and references them in its XML mapping. The domain model never sees raw strings.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full design rationale.

---

## Auth

JWT authentication with single-use refresh-token rotation, out of the box:

| Endpoint | Auth | Notes |
|----------|------|-------|
| `POST /auth/signup` | public | Creates a user with `ROLE_USER` |
| `POST /auth/login` | public | Returns access token + refresh token |
| `POST /auth/refresh` | public | Rotates refresh token, returns new access token |
| `GET /api/me` | `IS_AUTHENTICATED_FULLY` | Current user payload |
| `GET /api/admin/users` | `ROLE_ADMIN` | Paginated user list |

Every `/auth/refresh` call issues a new refresh token and revokes the previous one atomically. Reusing a revoked token logs the user out everywhere. On the frontend, the access token lives in memory (never `localStorage`) and the refresh token lives in an HttpOnly cookie. Next.js middleware silently refreshes on 401.

See [docs/auth.md](docs/auth.md) for the full flow and frontend strategy.

---

## AI harness

The `.ai/` directory is the AI engineering harness ported from [open-mercato](https://github.com/open-mercato/open-mercato). It contains everything needed to drive development with Claude or Cursor in a spec-first, skill-based workflow.

### What's included

**Specs** (`.ai/specs/`)
Feature specifications written before implementation. Pending specs live at the top level; deployed ones move to `implemented/`. Each spec is a structured document covering the problem, proposed solution, architecture decisions, data model, API contract, phasing, and risks. The AI reads the spec before writing a line of code.

**Skills** (`.ai/skills/`) — 30+ reusable playbooks invoked as slash commands:

| Skill | What it does |
|-------|-------------|
| `/scaffold-bounded-context` | Generates the full 4-layer skeleton for a new context |
| `/add-command` | Adds a Command + Handler + UseCase + test |
| `/add-query` | Adds a Query + Handler + UseCase + Response + test |
| `/add-route` | Adds an HTTP endpoint with OpenAPI annotations |
| `/scaffold-doctrine-migration` | Generates and reviews a Doctrine migration diff |
| `/scaffold-nextjs-page` | App Router page + loading + error boundaries |
| `/scaffold-shadcn-form` | react-hook-form + zod + shadcn form components |
| `/spec-writing` | Writes a feature spec in the correct format |
| `/pre-implement-spec` | Audits a spec for gaps before implementation begins |
| `/implement-spec` | Implements an approved spec end-to-end |
| `/code-review` | Reviews the current branch diff against conventions |
| `/auto-create-pr` | Pushes the branch and opens a GitHub PR |
| `/auto-review-pr` | Reviews a PR by number |
| `/merge-buddy` | Checks CI gates and merges |
| `/root-cause` | Investigates a failing test or bug |
| `/fix` | Applies a root-cause fix |

**QA harness** (`.ai/qa/`)
Testing guidance at two layers: PHPUnit functional tests (WebTestCase, transactional rollback isolation) and Playwright e2e tests (full user journeys).

**Lessons** (`.ai/lessons.md`)
Institutional memory of architectural decisions and mistakes not to repeat. Each entry has a "why" and a "how to apply". The AI reads this before proposing changes.

**Design-system rules** (`.ai/ds-rules.md`)
Tailwind + shadcn token rules for the frontend. Semantic tokens only, no hardcoded colors, no arbitrary values. The AI checks these before writing UI code.

### How to use it

```sh
# Create a spec for a new feature
/spec-writing "user can upload an avatar"

# Review and approve the spec, then:
/scaffold-bounded-context Media

# Add a command:
/add-command Media UploadAvatar

# Review what was built:
/code-review

# Open a PR:
/auto-create-pr
```

See [docs/ai-workflow.md](docs/ai-workflow.md) for the full workflow.

---

## Adding a bounded context

The reference context is `User` in `apps/api/src/User/`. Use it as the pattern.

The minimum steps:

```sh
# 1. Create the four-layer folder structure
mkdir -p apps/api/src/<Context>/{Domain,Application,Infrastructure,Presentation}

# 2. Add Doctrine mapping
#    → apps/api/src/<Context>/Infrastructure/Persistence/Doctrine/Mapping/<Aggregate>.orm.xml

# 3. Register custom DBAL types and the Doctrine mapping
#    → apps/api/config/packages/doctrine.yaml

# 4. Wire the repository alias in the context's own services file
#    → apps/api/src/<Context>/Infrastructure/Symfony/Resources/config/services.yaml

# 5. Import that services file in config/services.yaml

# 6. Generate and review the migration
make migrate-diff
make migrate
```

See [docs/adding-a-bounded-context.md](docs/adding-a-bounded-context.md) for the detailed walkthrough, or use `/scaffold-bounded-context` with Claude / Cursor to do it automatically.

---

## Documentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — DDD + Hexagonal + CQRS rationale, the four layers, the three buses, PHP 8.4 + Doctrine 3 specifics
- [docs/getting-started.md](docs/getting-started.md) — from clone to first endpoint in 30 minutes
- [docs/auth.md](docs/auth.md) — JWT flow, refresh rotation, frontend cookie strategy, security decisions
- [docs/adding-a-bounded-context.md](docs/adding-a-bounded-context.md) — step-by-step guide for new contexts
- [docs/ops.md](docs/ops.md) — Docker setup, environment variables, CI pipeline, production notes
- [docs/ai-workflow.md](docs/ai-workflow.md) — spec-first AI-driven development with the `.ai/` harness

---

MIT licensed. See [LICENSE](LICENSE).

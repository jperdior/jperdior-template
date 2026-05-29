# Plan: `jperdior-template` — Spec-Driven PHP/Symfony + Next.js Monorepo Template

## Context

The user wants a reusable, AI-engineered greenfield template at `/Users/julio.perdiguer/devel/jperdior-template` that combines:

- **Architecture from `feverup-php`**: PHP 8.4 + Symfony 7.4, DDD + Hexagonal + CQRS via Symfony Messenger, Doctrine 3 with strict XML mapping, immutable value objects, repository pattern (interface in Domain, impl in Infrastructure), thin invokable controllers, single-aggregate transactions, async ingestion isolation. (Verified in exploration.)
- **AI harness from `open-mercato`**: `.ai/specs/` lifecycle, `.ai/skills/` (core + automation tiers), spec-driven workflow with Open Questions gate, code-review CI gate, PR pipeline label state machine, integration test harness, `lessons.md`, Task Router AGENTS.md pattern. (Verified in exploration.)
- **Not in `feverup-php`, added by request**: out-of-the-box authentication; a Next.js 15 + shadcn/ui frontend; three app slots (api + web + admin); PostgreSQL 16; a working hello-world end-to-end on `make start`.
- **Explicit anti-requirement**: **multi-tenancy is NOT in the core.** It must be available as a clean, optional add-on (a separate package wiring a Doctrine SQLFilter + request-scoped `TenantContext`), so the default template is single-tenant and any project can opt in later without retrofitting columns onto core entities.

The intended outcome: cloning this repo and running `make start` boots a Postgres + Symfony API + Next.js web + Next.js admin stack with working JWT auth and a "Notes" hello-world bounded context. The `.ai/` harness is ready from day one for Claude/Cursor to plan, implement, review, and ship features via skills.

---

## High-Level Architecture

```
jperdior-template/
├── apps/
│   ├── api/              # Symfony 7.4 API (DDD+Hex+CQRS, follows feverup-php pattern)
│   ├── web/              # Next.js 15 public web (App Router, shadcn/ui)
│   └── admin/            # Next.js 15 admin panel (shares ui-react package)
├── packages/
│   ├── shared-kernel-php/   # AggregateRoot, ValueObject, Bus interfaces, Clock, Result
│   ├── tenancy-php/         # OPTIONAL multi-tenancy: TenantContext + SQLFilter + interfaces
│   ├── ui-react/            # Shared shadcn/ui components, hooks, theming
│   └── api-client-ts/       # Generated TS client from API OpenAPI spec
├── ops/
│   ├── docker/              # Per-app Dockerfiles + nginx + docker-compose.{base,dev,prod}.yml
│   ├── k8s/                 # Optional Helm chart skeleton (slot ready, not wired by default)
│   └── ci/                  # Shared CI scripts referenced from .github/workflows
├── .ai/                     # Specs, skills, qa harness, lessons, ds-rules — ported from open-mercato
├── .claude/                 # settings.local.json template
├── .github/workflows/       # CI + PR automation (ported & adapted)
├── docs/                    # ARCHITECTURE.md, getting-started.md, multitenancy.md, etc.
├── AGENTS.md                # Root task router + Always/Ask First/Never
├── CLAUDE.md                # → AGENTS.md
├── Makefile                 # Single entry point: make start | test | lint | shell
├── .env.dist                # Top-level env template
├── composer.json            # Path repositories for packages/*-php and apps/api
└── pnpm-workspace.yaml      # JS workspaces: apps/web, apps/admin, packages/ui-react, packages/api-client-ts
```

### Architectural decisions (locked)

- **API deployment model — modular monolith with split worker entrypoint** (matches feverup-php). `apps/api` is **one Symfony app** containing all bounded contexts (`User/`, `Note/`, future ones) side-by-side under `code/src/`. Same codebase ships as **two runtime processes**:
  - `api` container: nginx + php-fpm serving HTTP for every context.
  - `worker` container: `php bin/console messenger:consume async` draining Messenger transports for every context.
  - Same image, different command — exactly like feverup-php's `ops/docker/backend/` vs `ops/docker/worker/`.
  - **No microservices.** Cross-context coupling is forbidden at the code level (a `User\` class may not import `Note\Domain\`); the only allowed cross-context communication is via the Command/Query/Event bus, which keeps a future extraction-to-service mechanical rather than a rewrite. CI enforces this with a `deptrac` ruleset (Phase 11).
  - **Adding a new bounded context** = drop a new `code/src/<Context>/` folder with the four layers. The `scaffold-bounded-context` skill (Phase 12) automates this. No new Docker image, no new deployment, no new DB.
- **Monorepo tooling**: `pnpm` workspaces for JS (apps/web, apps/admin, packages/ui-react, packages/api-client-ts) + Composer path repositories for PHP (apps/api ↔ packages/*-php). One root `Makefile` orchestrates both.
- **PHP-side bounded context layout** (every context, including User and Note): `Domain/`, `Application/`, `Infrastructure/`, `Presentation/` — copied verbatim from feverup-php's `code/src/Event/`.
- **Three Messenger buses**: `command.bus`, `query.bus`, `event.bus`, auto-tagged via `_instanceof` on `CommandHandler`, `QueryHandler`, `DomainEventSubscriber` interfaces from `shared-kernel-php`.
- **Doctrine mapping**: XML only (no attributes on entities). Naming strategy `underscore_number_aware`. Repository interfaces in `Domain/`, Doctrine impls in `Infrastructure/Persistence/`.
- **Auth**: Symfony Security + `lexik/jwt-authentication-bundle` (access tokens) + `gesdinet/jwt-refresh-token-bundle` (refresh tokens, stored in DB). `User` aggregate lives in its own bounded context `apps/api/src/User/`. Passwords hashed via Symfony's `password_hasher` (argon2id). Refresh-token rotation enabled.
- **Frontend auth**: HttpOnly cookie storage of refresh token; access token in memory; Next.js middleware refreshes on expiry. Login/signup/me endpoints exposed on the API.
- **Default transport**: Symfony Messenger `doctrine://` transport (no RabbitMQ required to boot). Docs describe how to switch to AMQP if a project needs it. Redis is included only for cache.
- **DB**: PostgreSQL 16 in compose. Doctrine 3 + DoctrineMigrationsBundle, migrations under `apps/api/migrations/`.
- **OpenAPI**: NelmioApiDocBundle generates `/api/doc.json`; CI regenerates `packages/api-client-ts` via `openapi-typescript` so frontend types stay in sync.
- **Multi-tenancy strategy**: see "Multi-tenancy as an Optional Bounded Context" below.

### Multi-tenancy as an Optional Bounded Context

**Core rule:** no entity in `apps/api/src/` has a `tenant_id` column by default. The User aggregate is single-instance. To opt in, a project follows these steps (documented in `docs/multitenancy.md`):

1. Add `packages/tenancy-php` to `apps/api/composer.json` `require`.
2. Register `TenancyBundle` in `apps/api/config/bundles.php`.
3. Mark relevant entities by implementing the `TenantOwned` interface and adding a `tenant_id` column via a project-specific migration.
4. Enable the `TenantFilter` Doctrine SQLFilter (registered by the bundle, off by default).
5. Configure tenant resolution strategy (JWT claim, subdomain, header) via bundle config.

What lives in `packages/tenancy-php`:
- `TenantId` value object
- `TenantContext` (request-scoped, resolved via a `TenantResolverInterface`)
- `TenantOwned` marker interface
- `TenantFilter` Doctrine SQLFilter (auto-injects `WHERE tenant_id = :current` for marked entities)
- `JwtClaimTenantResolver` and `SubdomainTenantResolver` reference implementations
- `TenancyBundle` Symfony bundle wiring all of the above

This is the correct architectural shape: tenancy is a **cross-cutting concern**, applied via filter + context, not a column convention on every aggregate. Core stays single-tenant; projects opt in cleanly.

---

## Implementation Phases

Each phase is independently verifiable. We pause between phases for review.

### Phase 0 — Repo bootstrap

- Create `/Users/julio.perdiguer/devel/jperdior-template` with `git init`, `.gitignore` (PHP + Node + IDE), `.editorconfig`, `LICENSE`, `README.md`, top-level `Makefile`, `.env.dist`, `composer.json` (path repos), `pnpm-workspace.yaml`, `turbo.json` (optional task graph for JS).
- Stub `AGENTS.md` + `CLAUDE.md` (CLAUDE.md is one line: `@AGENTS.md`).

### Phase 1 — AI harness port

- Port `.ai/` from open-mercato selectively:
  - **Copy as-is**: `lessons.md` (start empty + template), `specs/AGENTS.md`, `qa/AGENTS.md`, `skills/tiers.json` + `tiers.schema.json`, `runs/` placeholder.
  - **Copy & adapt**: root `AGENTS.md` — keep Task Router pattern, Always/Ask First/Never, PR Workflow, Workflow Orchestration; replace open-mercato specifics with template specifics.
  - **Port skills (core tier)**: `spec-writing`, `pre-implement-spec`, `implement-spec`, `code-review`, `integration-tests`, `check-and-commit`, `fix-specs`, `skill-creator`, `create-agents-md`. Adapt validation commands to `make typecheck`, `make lint`, `make test`, `make build`.
  - **Port skills (automation tier)**: `auto-create-pr`, `auto-continue-pr`, `auto-review-pr`, `merge-buddy`, `root-cause`, `fix`, `open-pr`, `sync-merged-pr-issues`, `auto-update-changelog`, `verify-in-repo`.
  - **Skip / drop**: `ds-guardian` (rewrite later for shadcn), `backend-ui-design` (rewrite later for Next.js), `create-ai-agent` (open-mercato-specific), `migrate-mikro-orm` (no MikroORM), `integration-builder` (open-mercato-specific).
- Create `.claude/settings.local.json` template (gitignored copy in `.claude/settings.example.json`).
- Create `docs/ARCHITECTURE.md` summarising DDD+Hex+CQRS choices.

### Phase 2 — Shared kernel (`packages/shared-kernel-php`)

Pure PHP package, no Symfony dep. Mirrors feverup-php's `code/src/Shared/`:
- `Domain/Aggregate/AggregateRoot.php`
- `Domain/Bus/Command/{Command,CommandHandler,CommandBus}.php`
- `Domain/Bus/Query/{Query,QueryHandler,QueryResponse,QueryBus}.php`
- `Domain/Bus/Event/{DomainEvent,DomainEventSubscriber,EventBus}.php`
- `Domain/ValueObject/{Uuid,DateTimeValueObject,StringValueObject}.php`
- `Domain/Repository/TransactionInterface.php`
- `Domain/Clock/ClockInterface.php` + `Infrastructure/Clock/SystemClock.php`
- `composer.json` PSR-4 `Jperdior\SharedKernel\` → `src/`

### Phase 3 — Symfony API skeleton (`apps/api`)

Mirror feverup-php's structure: `apps/api/` is the Symfony project; `apps/api/composer.json` requires `packages/shared-kernel-php` via path repo.

Files modelled on feverup-php paths:
- `apps/api/config/{services.yaml,packages/*.yaml,routes.yaml,bundles.php}` — Messenger 3 buses + `_instanceof` tags + Lexik JWT + Nelmio API Doc + CORS.
- `apps/api/src/Shared/` — Symfony adapters for the buses (`MessengerCommandBus`, `MessengerQueryBus`, `MessengerEventBus` implementing kernel interfaces) + base `DoctrineRepository`.
- `apps/api/composer.json` — PHP 8.4, Symfony 7.4 same bundles as feverup-php + Lexik JWT + Gesdinet Refresh + Nelmio CORS.
- `phpstan.dist.neon` level 8, `.php-cs-fixer.dist.php` (Symfony ruleset).
- `migrations/` empty, ready for first migration.

### Phase 4 — User bounded context (auth)

`apps/api/src/User/`:
- `Domain/`: `User` aggregate, `UserId`, `Email`, `HashedPassword` value objects, `UserRepository` interface, `UserAlreadyExists`/`InvalidCredentials` exceptions.
- `Application/Command/{SignUp,RefreshToken,RevokeToken}/` — commands + handlers.
- `Application/Query/{GetCurrentUser}/` — query + handler + response DTO.
- `Infrastructure/Persistence/Doctrine{UserRepository,Mapping/User.orm.xml}.php`.
- `Infrastructure/Symfony/Security/{UserProvider,JwtUserAdapter}.php` — bridges Symfony Security to domain `User`.
- `Presentation/Http/{SignUpController,LoginController,RefreshController,MeController}.php` + Request/Response DTOs.
- First migration: `users` + `refresh_tokens` tables.
- `config/packages/security.yaml`: stateless firewall `api`, JWT authenticator, public `^/auth` paths, protected `^/api`.

### Phase 5 — Notes hello-world bounded context

`apps/api/src/Note/`:
- `Domain/`: `Note` aggregate (id, ownerId, title, body, timestamps), `NoteId`, `NoteTitle`, `NoteBody`, `NoteRepository`.
- `Application/Command/{CreateNote,UpdateNote,DeleteNote}/`.
- `Application/Query/{ListNotes,GetNote}/`.
- `Infrastructure/Persistence/Doctrine/...`.
- `Presentation/Http/` — 5 invokable controllers, OpenAPI annotations.
- Migration: `notes` table (`owner_id` FK to `users.id`, no tenant column).
- Functional tests covering happy paths + auth required + ownership enforcement.

### Phase 6 — Frontend shared packages

- `packages/ui-react/`: Next-compatible (RSC-aware), exports shadcn primitives + theme provider + `Button`, `Card`, `Input`, `Form`, `Dialog`, `Toast`. Tailwind config exported from here.
- `packages/api-client-ts/`: `pnpm gen:api` runs `openapi-typescript` against `http://api:8080/api/doc.json` and writes `src/types.gen.ts`. Lightweight fetch wrapper `apiClient.ts` with auto refresh-token handling.

### Phase 7 — Web app (`apps/web`)

Next.js 15 App Router + Tailwind + shadcn/ui consuming `@jperdior/ui-react`:
- Routes: `/login`, `/signup`, `/(app)/notes` (list), `/(app)/notes/[id]` (detail).
- Server Actions for mutations using `api-client-ts`.
- Auth handled by Next middleware: refresh token in HttpOnly cookie, access token in memory store (zustand).
- Playwright spec: `apps/web/e2e/auth.spec.ts` proving signup→login→create-note→see-note.

### Phase 8 — Admin app (`apps/admin`)

Same stack, separate app, different theme. Routes: `/login`, `/(admin)/users` (paginated list — admin-only), `/(admin)/notes` (all notes across users). Requires `ROLE_ADMIN` enforced server-side on the API.

### Phase 9 — Optional tenancy package (`packages/tenancy-php`)

Built per the design above but **NOT wired into apps/api by default**. Includes its own README explaining the 5-step opt-in. Includes a small example migration generator (`make scaffold:tenancy:enable`) that adds tenant columns + filter registration in one shot for a given project clone.

### Phase 10 — Ops layer (`ops/`)

- `ops/docker/api/Dockerfile` (multi-stage: builder + php-fpm), shared by `api` (HTTP) and `worker` (Messenger consumer) services — differs only by `CMD`.
- `ops/docker/web/Dockerfile` (Node 22 + Next standalone), `ops/docker/admin/Dockerfile` (same as web).
- `ops/docker/nginx/api.conf` reverse proxy in front of php-fpm.
- `ops/docker/docker-compose.base.yml` services: `postgres`, `redis`, `api` (nginx+fpm), `worker` (messenger consumer), `web`, `admin`. `docker-compose.dev.yml` overlays mounts, debug ports, `xdebug` profile.
- `ops/k8s/` skeleton: `Chart.yaml`, `values.yaml`, templates for api/web/admin Deployment + Service + Ingress + ConfigMap + Secret. Not required to use; documented as "when you outgrow compose".
- `ops/ci/scripts/{install.sh,lint.sh,test.sh,build.sh}` — referenced from GitHub Actions.

### Phase 11 — CI / PR automation (`.github/workflows`)

- `ci.yml`: matrix job → PHPStan, php-cs-fixer check, **`deptrac` (enforces no cross-bounded-context imports outside the bus)**, PHPUnit unit + functional (Postgres service), pnpm typecheck + lint + build, Playwright integration (ephemeral compose).
- `qa-deploy.yml` + `qa-stop-on-merge.yml` stubs from open-mercato (label-triggered).
- `release.yml` for tagging + changelog.
- `skills-tiers-lint.yml` (validates `tiers.json`).
- PR template + issue templates ported from open-mercato.
- Labels documented (mutually exclusive pipeline, additive category/meta/priority) — replicated from open-mercato's PR Workflow section.

### Phase 12 — PHP-specific skills

New skills in `.ai/skills/` adapted to PHP/Symfony:
- `scaffold-bounded-context` — generates Domain/Application/Infrastructure/Presentation skeleton from an aggregate name; mirrors User/Note layout.
- `add-command` / `add-query` — adds a Messenger command or query with handler + functional test scaffold.
- `add-route` — adds an invokable controller + Request DTO + Response DTO + Nelmio annotation + functional test.
- `scaffold-doctrine-migration` — wraps `php bin/console make:migration` with naming conventions and snapshot review.

### Phase 13 — Frontend skills

- `scaffold-nextjs-page` — App Router page with loading.tsx + error.tsx + Server Action stub.
- `scaffold-shadcn-form` — `react-hook-form` + `zod` schema + shadcn `Form` primitives.
- `regenerate-api-client` — runs OpenAPI generation against running API.

### Phase 14 — Documentation

- `README.md` — quickstart (`git clone … && make start`).
- `docs/ARCHITECTURE.md` — DDD+Hex+CQRS deep-dive with diagrams.
- `docs/getting-started.md` — first 30 minutes.
- `docs/adding-a-bounded-context.md` — uses the `scaffold-bounded-context` skill.
- `docs/multitenancy.md` — the 5-step opt-in.
- `docs/auth.md` — JWT flow, refresh rotation, frontend cookie strategy.
- `docs/ops.md` — compose dev, k8s production, env variables reference.
- `docs/ai-workflow.md` — how to drive Claude/Cursor with the skills and specs.

### Phase 15 — Verification

End-to-end smoke before marking the template done:

1. `git clone` into a fresh sibling directory; `cp .env.dist .env.local`.
2. `make start` → all containers healthy.
3. Visit `http://localhost:3000/signup` → create account → redirected to `/notes` → create a note → reload, note still there.
4. Visit `http://localhost:3001` (admin) → log in with the same credentials promoted to admin via `make seed:admin` → see the user and note in admin lists.
5. `curl http://localhost:8080/api/doc` returns OpenAPI JSON; `pnpm gen:api` produces types without diff.
6. `make test` runs: PHPStan level 8 clean, php-cs-fixer clean, PHPUnit green (unit + functional), pnpm typecheck + lint green, Playwright e2e green.
7. `make lint` and `make build` exit 0.
8. Trigger `auto-create-pr` skill against a trivial change → PR appears with correct labels.
9. Run `pre-implement-spec` skill on a fake spec → it walks the workflow.

If every check passes, the template is ready to use.

---

## Critical files / patterns to mirror or reuse

From `feverup-php` (exact patterns to copy):
- `code/src/Event/` four-layer DDD layout → `apps/api/src/<Context>/`
- `code/src/Shared/Domain/Bus/` interfaces → `packages/shared-kernel-php/src/Domain/Bus/`
- `code/src/Shared/Infrastructure/Doctrine/DoctrineRepository.php` → `apps/api/src/Shared/Infrastructure/Doctrine/DoctrineRepository.php`
- `code/config/services.yaml` `_instanceof` block + Messenger config
- `Makefile` targets pattern
- `ops/docker/` structure
- `phpstan.dist.neon` level 8 config
- `code/.php-cs-fixer.dist.php`

From `open-mercato` (port + adapt):
- `AGENTS.md` Task Router (lines 47–108) — adapt rows to template
- `AGENTS.md` PR Workflow section (lines 128–141)
- `.ai/specs/AGENTS.md` lifecycle rules
- `.ai/qa/AGENTS.md` integration testing rules (adapt PHP+Playwright)
- `.ai/skills/spec-writing/`, `.ai/skills/implement-spec/`, `.ai/skills/code-review/`, `.ai/skills/auto-create-pr/`, `.ai/skills/auto-review-pr/`, `.ai/skills/merge-buddy/` — port wholesale, swap commands
- `.ai/lessons.md` template (start empty, seed with 2-3 entries from feverup-php's `invariants.md`)
- `.github/workflows/ci.yml` matrix pattern
- `.ai/skills/tiers.json` + `tiers.schema.json`

---

## What this plan deliberately does NOT do

- Does not include i18n, payments, email sending, SSO, OAuth providers — those go behind their own optional packages added per project.
- Does not commit to Kubernetes; ships skeleton only. Compose is the supported dev/prod path until a project outgrows it.
- Does not bundle a CMS, CRM, or commerce modules. (That's open-mercato's job.)
- Does not include MikroORM, Vercel AI SDK, or any open-mercato-specific runtime — only its **process** and **discipline**.

---

## Iteration boundary

We pause between phases. The natural review points are: after Phase 1 (harness in place), after Phase 5 (Symfony API + auth + hello-world running), after Phase 8 (full stack on `make start`), after Phase 11 (CI green), after Phase 15 (verification complete). At any boundary the user can redirect scope, drop a slot, or expand the design.

# Agents Guidelines

This is a **spec-driven, AI-engineered monorepo template**. PHP 8.4 + Symfony 7.4 API (DDD + Hexagonal + CQRS) plus Next.js 15 frontends, with the AI harness ported from open-mercato.

Leverage the bounded-context system and follow strict naming and coding conventions to keep the system consistent and safe to extend.

## Always

- Check the **Task Router** below before research or coding; a single task may match multiple rows.
- Check `.ai/specs/` for existing specs before modifying an established context.
- Enter plan mode for non-trivial tasks with 3+ steps or architectural decisions.
- For new contexts, mirror the **reference contexts** (`User`, `Note`) for layout and conventions.
- Preserve behavior unless the user or a spec explicitly asks for a behavior change.
- Keep changes minimal, focused, and integrated through real call sites.
- Use the closest package/app `AGENTS.md` for local architecture, imports, and validation commands.
- Re-generate the TS API client (`make gen-api`) after any backend OpenAPI-affecting change.

## Ask First

- Ask before reducing scope, changing architecture, changing public contracts, adding production dependencies, or touching multiple bounded contexts in a way not covered by an existing spec.
- Ask before applying database migrations locally with `make migrate`; PRs should include migration files.
- Ask before adding multi-tenancy to a context (it stays out of core unless explicitly opted in — see `docs/multitenancy.md`).

## Never

- **Never** import framework code (`Symfony\*`, `Doctrine\*`, `Predis\*`) inside `Domain/`.
- **Never** call command/query handlers directly from controllers — always go through the bus.
- **Never** import another bounded context's `Domain/` or `Application/` — cross-context communication goes through the event bus or a public application service. (CI: `deptrac` enforces this.)
- **Never** add Doctrine attributes to domain entities; ORM mapping is XML only.
- **Never** add `tenant_id` columns to entities in `apps/api/src/` unless the `tenancy-php` package is enabled in that project.
- **Never** edit generated files (`apps/api/openapi.json`, `packages/api-client-ts/src/types.gen.ts`) by hand.
- **Never** commit credentials, raw tokens, or `.env.local`.

## Validation Commands

Choose the smallest relevant set for the change:

```bash
make lint          # phpstan + cs-fixer + deptrac + tsc + eslint
make test          # phpunit (unit + functional) + pnpm test
make test-e2e      # Playwright (requires `make start` first)
make build-web     # production Next.js build (web + admin)
make migrate-diff  # generates a Doctrine migration diff
```

## Task Router — Where to Find Detailed Guidance

IMPORTANT: Before any research or coding, match the task to this table. A single task often maps to **multiple rows**. Read **all** matching guides before starting.

| Task | Guide |
|------|-------|
| **Bounded context development** | |
| Creating a new bounded context, scaffolding the 4 layers | `apps/api/AGENTS.md` → New Context + `.ai/skills/scaffold-bounded-context/SKILL.md` |
| Adding a Command (write side) | `apps/api/AGENTS.md` → CQRS + `.ai/skills/add-command/SKILL.md` |
| Adding a Query (read side) | `apps/api/AGENTS.md` → CQRS + `.ai/skills/add-query/SKILL.md` |
| Adding an HTTP endpoint | `apps/api/AGENTS.md` → Presentation + `.ai/skills/add-route/SKILL.md` |
| Adding a Doctrine migration | `.ai/skills/scaffold-doctrine-migration/SKILL.md` |
| Adding a domain event subscriber | `apps/api/AGENTS.md` → Events |
| **Auth** | |
| User aggregate, JWT, refresh tokens, security.yaml | `apps/api/src/User/AGENTS.md` + `docs/auth.md` |
| Adding ROLE_* checks | `apps/api/AGENTS.md` → Security |
| **Persistence** | |
| New aggregate + repository + XML mapping | `apps/api/AGENTS.md` → Persistence |
| Encrypted columns, soft deletes, JSON fields | `apps/api/AGENTS.md` → Persistence |
| **Multi-tenancy (optional)** | |
| Opting a project into multi-tenancy | `docs/multitenancy.md` + `packages/tenancy-php/AGENTS.md` |
| **Frontend** | |
| New page in `apps/web` or `apps/admin` | `apps/web/AGENTS.md` (or `apps/admin/AGENTS.md`) + `.ai/skills/scaffold-nextjs-page/SKILL.md` |
| Form with validation | `.ai/skills/scaffold-shadcn-form/SKILL.md` + `packages/ui-react/AGENTS.md` |
| Calling the API from the frontend | `packages/api-client-ts/AGENTS.md` |
| Regenerating the TS API client | `.ai/skills/regenerate-api-client/SKILL.md` |
| Shared component in `ui-react` | `packages/ui-react/AGENTS.md` |
| **Workflow** | |
| First-time local setup (hosts, .env.local, stack boot) | `.ai/skills/init/SKILL.md` |
| Starting any new feature (creates worktree + branch from main) | `.ai/skills/new-feature/SKILL.md` |
| **Specs & PR Automation** | |
| Writing a spec for a new feature | `.ai/skills/spec-writing/SKILL.md` + `.ai/specs/AGENTS.md` |
| Pre-implementation audit | `.ai/skills/pre-implement-spec/SKILL.md` |
| Implementing an approved spec | `.ai/skills/implement-spec/SKILL.md` |
| Code review (CI gate) | `.ai/skills/code-review/SKILL.md` |
| Auto PR workflows | `.ai/skills/auto-create-pr/SKILL.md`, `.ai/skills/auto-review-pr/SKILL.md`, `.ai/skills/merge-buddy/SKILL.md` |
| **Testing** | |
| Functional / integration tests | `apps/api/AGENTS.md` → Testing + `.ai/qa/AGENTS.md` |
| Playwright end-to-end | `.ai/skills/integration-tests/SKILL.md` |
| **Ops** | |
| Docker, compose, K8s | `docs/ops.md` + `ops/AGENTS.md` |
| CI / GitHub Actions | `.github/workflows/` + `docs/ops.md` |

## Core Principles

- **Simplicity First.** Make every change as simple as possible.
- **No Laziness.** Find root causes. No temporary fixes.
- **Minimal Impact.** Changes should only touch what's necessary.
- **Boundaries are sacred.** A `User\Domain\` import inside `Note\` is a regression, not a refactor.

## Workflow Orchestration

1. **Spec-first** for non-trivial tasks (3+ steps or architectural decisions). Check `.ai/specs/` first. Skip for small fixes.
2. **Subagent strategy**: use subagents for research / parallel analysis. One task per subagent. Keeps main context clean.
3. **Self-improvement**: after corrections, update `.ai/lessons.md` or the relevant AGENTS.md.
4. **Verification**: run `make lint && make test`. Ask: "Would a staff engineer approve this?"
5. **Elegance check**: for non-trivial changes, pause and ask "is there a more elegant way?"

## Monorepo Structure

```
apps/
  api/        Symfony 7.4, all bounded contexts under src/<Context>/
  web/        Next.js 15 public app
  admin/      Next.js 15 back-office
packages/
  shared-kernel-php/   Pure-PHP DDD primitives (AggregateRoot, Bus interfaces, ValueObjects)
  tenancy-php/         Optional multi-tenancy (TenantContext + Doctrine SQLFilter)
  ui-react/            Shared shadcn/ui components
  api-client-ts/       Generated TS client + fetch wrapper (refresh-token aware)
ops/
  docker/     Per-service Dockerfiles + compose
  k8s/        Helm chart skeleton
  ci/         Shared CI scripts
.ai/          Specs, skills, qa, lessons, ds-rules — the AI harness
docs/         ARCHITECTURE, getting-started, multitenancy, auth, ops, ai-workflow
```

## Conventions (project-wide)

- **PHP**: `declare(strict_types=1);` at the top of every file. Final classes by default. `readonly` for value objects, DTOs, queries, responses. `DateTimeImmutable` everywhere in domain code, never `DateTime`.
- **Bounded contexts**: plural-or-singular PascalCase (`User`, `Note`, `Subscriptions`).
- **Event IDs**: `<context>.<aggregate>.<action_past_tense>` (e.g. `user.account.created`).
- **DB**: snake_case columns and tables; plural table names; UUID PKs; columns `id`, `created_at`, `updated_at`, `deleted_at` where applicable.
- **TypeScript**: `strict: true`; no `any`; React server components by default in `apps/web` and `apps/admin`.
- **i18n**: never hard-code user-facing strings; use the locale files under `apps/web/messages/` and `apps/admin/messages/`.

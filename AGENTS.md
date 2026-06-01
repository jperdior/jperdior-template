# Agents Guidelines

This is a **spec-driven, AI-engineered monorepo template**. PHP 8.4 + Symfony 7.4 API (DDD + Hexagonal + CQRS) plus Next.js 15 frontends, with the AI harness ported from open-mercato.

Leverage the bounded-context system and follow strict naming and coding conventions to keep the system consistent and safe to extend.

## Session start — worktree awareness check

At the very start of every conversation, run this check silently:

```bash
# Are we inside a linked worktree?
GIT_DIR=$(cd "$(git rev-parse --git-dir)" 2>/dev/null && pwd -P)
GIT_COMMON=$(cd "$(git rev-parse --git-common-dir)" 2>/dev/null && pwd -P)
BRANCH=$(git branch --show-current)
```

If `GIT_DIR != GIT_COMMON` (we are in a worktree) **and** the branch is not `main`:

```bash
git fetch origin --quiet
AHEAD=$(git log origin/main..HEAD --oneline 2>/dev/null | wc -l | tr -d ' ')
```

If `AHEAD == 0` the branch has been fully merged into `origin/main`. **Immediately tell the user:**

> "This conversation is linked to worktree `<path>` on branch `<branch>`, which has already been merged into main. Do you want to switch back to main and clean up the worktree?"

Wait for the user to confirm before doing anything. If they say yes:
1. Run `make stop` (stops containers that may be running from the worktree)
2. Exit the worktree (`ExitWorktree` tool if available, otherwise `cd` to main repo root)
3. Run `sudo rm -rf <worktree-path>` (Docker may have created root-owned files inside)
4. Run `git worktree prune` from the main repo
5. Delete the local branch: `git branch -d <branch>`
6. Run `make start` to restart containers from main

Do **not** start any new work until the user has acknowledged the merged state.

## Always

- Check the **Task Router** below before research or coding; a single task may match multiple rows.
- Check `.ai/specs/` for existing specs before modifying an established context.
- Enter plan mode for non-trivial tasks with 3+ steps or architectural decisions.
- For new contexts, mirror the **reference context** (`User`) for layout and conventions.
- Preserve behavior unless the user or a spec explicitly asks for a behavior change.
- Keep changes minimal, focused, and integrated through real call sites.
- Use the closest package/app `AGENTS.md` for local architecture, imports, and validation commands.
- Re-generate the TS API client (`make gen-api`) after any backend OpenAPI-affecting change.
- For **every change** — features, bug fixes, doc edits, skill updates, config tweaks, anything — create a new worktree and branch from main using `.ai/skills/new-feature/SKILL.md` before touching any file. No change, however trivial, is ever committed directly to main. Every branch must land via a PR.

## Ask First

- Ask before reducing scope, changing architecture, changing public contracts, adding production dependencies, or touching multiple bounded contexts in a way not covered by an existing spec.
- Ask before applying database migrations locally with `make migrate`; PRs should include migration files.
- Ask before adding any cross-cutting concern (caching, auditing, soft deletes) to a context that doesn't already use it.

## Never

- **Never** import framework code (`Symfony\*`, `Doctrine\*`, `Predis\*`) inside `Domain/`.
- **Never** call command/query handlers directly from controllers — always go through the bus.
- **Never** import another bounded context's `Domain/` or `Application/` — cross-context communication goes through the event bus or a public application service. (CI: `deptrac` enforces this.)
- **Never** add Doctrine attributes to domain entities; ORM mapping is XML only.
- **Never** add `tenant_id` columns to entities in `apps/api/src/`. This template is single-tenant by design. For multi-tenancy, fork the template.
- **Never** edit generated files (`apps/api/openapi.json`, `packages/api-client-ts/src/types.gen.ts`) by hand.
- **Never** commit credentials, raw tokens, or `.env.local`.

## Validation Commands

All `make` targets run inside Docker containers — **never** invoke `php`, `pnpm`, `tsc`, or `eslint` directly on the host.

Choose the smallest relevant set for the change:

```bash
make lint          # phpstan + cs-fixer + deptrac + tsc + eslint (all inside containers)
make test          # phpunit (unit + functional) + pnpm test (all inside containers)
make test-e2e      # Playwright (requires `make start` first)
make build-web     # production Next.js build (web + admin)
make migrate-diff  # generates a Doctrine migration diff
```

### Worktree container workflow

Docker containers mount the **main branch** code by default. When working in a git worktree,
restart the stack from the worktree so containers pick up your changes before linting or testing:

```bash
# 1. Stop containers (run from anywhere — stops all containers)
make stop

# 2. Start containers from the worktree (containers now mount the worktree's code)
cd /path/to/worktree && make start
```

Then run `make lint && make test` normally.

### Pre-PR gate (mandatory)

Before offering to create a PR or push a branch, **always** run and confirm both pass:

```bash
make lint && make test
```

Do not offer a PR if either command fails. Fix all errors first.

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
| **Frontend** | |
| New page in `apps/web` or `apps/admin` | `apps/web/AGENTS.md` (or `apps/admin/AGENTS.md`) + `.ai/skills/scaffold-nextjs-page/SKILL.md` |
| Form with validation | `.ai/skills/scaffold-shadcn-form/SKILL.md` + `packages/ui-react/AGENTS.md` |
| Calling the API from the frontend | `packages/api-client-ts/AGENTS.md` |
| Regenerating the TS API client | `.ai/skills/regenerate-api-client/SKILL.md` |
| Shared component in `ui-react` | `packages/ui-react/AGENTS.md` |
| **Workflow** | |
| First-time project customization (rename placeholders, add project context) | `.ai/skills/customize-project/SKILL.md` |
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
| Run PHP quality locally (PHPStan / cs-fixer / deptrac) | `.ai/skills/lint-php/SKILL.md` |
| Run JS quality locally (tsc / ESLint) | `.ai/skills/lint-js/SKILL.md` |
| **Ops** | |
| Docker, compose, K8s | `docs/ops.md` + `ops/AGENTS.md` |
| CI / GitHub Actions | `.github/workflows/` + `docs/ops.md` |

## Core Principles

- **Simplicity First.** Make every change as simple as possible.
- **No Laziness.** Find root causes. No temporary fixes.
- **Minimal Impact.** Changes should only touch what's necessary.
- **Boundaries are sacred.** A `User\Domain\` import inside `Orders\` is a regression, not a refactor.

## Workflow Orchestration

**Recommended AI-first path for any non-trivial feature:**
`/spec-writing` (design + spec doc) → `/implement-spec` (code, calls scaffolding skills internally) → PR

Use `/scaffold-bounded-context`, `/add-command`, `/add-route` **directly** only for small, already-specified additions where a full spec would be overhead.

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
  ui-react/            Shared shadcn/ui components
  api-client-ts/       Generated TS client + fetch wrapper (refresh-token aware)
ops/
  docker/     Per-service Dockerfiles + compose
  k8s/        Helm chart skeleton
  ci/         Shared CI scripts
.ai/          Specs, skills, qa, lessons, ds-rules — the AI harness
docs/         ARCHITECTURE, getting-started, auth, adding-a-bounded-context, ops, ai-workflow
```

## Conventions (project-wide)

- **PHP**: `declare(strict_types=1);` at the top of every file. Final classes by default. `readonly` for value objects, DTOs, queries, responses. `DateTimeImmutable` everywhere in domain code, never `DateTime`.
- **Bounded contexts**: plural-or-singular PascalCase (`User`, `Order`, `Subscription`).
- **Event IDs**: `<context>.<aggregate>.<action_past_tense>` (e.g. `user.account.created`).
- **DB**: snake_case columns and tables; plural table names; UUID PKs; columns `id`, `created_at`, `updated_at`, `deleted_at` where applicable.
- **TypeScript**: `strict: true`; no `any`; React server components by default in `apps/web` and `apps/admin`.
- **i18n**: never hard-code user-facing strings; use the locale files under `apps/web/messages/` and `apps/admin/messages/`.

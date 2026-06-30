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
1. Run `make stop-test` (stops this worktree's headless test stack)
2. Run `make stop` (stops any dev stack, if running)
3. Exit the worktree (`ExitWorktree` tool if available, otherwise `cd` to main repo root)
4. Run `sudo rm -rf <worktree-path>` (Docker may have created root-owned files inside)
5. Run `git worktree prune` from the main repo
6. Delete the local branch: `git branch -d <branch>`
7. Run `make start` to restart containers from main

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
- **Agentic docs are authoritative, not historical.** Write what the system IS, not how it got there. No "previously / refactor / phase N / fixed in / no longer / legacy / now uses / was added / landed" framing inside `AGENTS.md`, `docs/*.md`, or `SKILL.md` files — that belongs in `.ai/specs/` and `git log`. When a spec is implemented, fold its outcomes into the relevant AGENTS.md/docs as plain present-tense rules; do not carry the spec's "post-refactor" narrative forward.

## Ask First

- Ask before reducing scope, changing architecture, changing public contracts, adding production dependencies, or touching multiple bounded contexts in a way not covered by an existing spec.
- Ask before applying database migrations locally with `make migrate`; PRs should include migration files.
- Ask before adding any cross-cutting concern (caching, auditing, soft deletes) to a context that doesn't already use it.

## Never

- **Never** import framework code (`Symfony\*`, `Doctrine\*`, `Predis\*`) inside `Domain/`.
- **Never** call command/query handlers directly from controllers — always go through the bus.
- **Never** import another bounded context's `Domain/` or `Application/` — cross-context communication goes through the event bus or a public application service. (CI: `deptrac` enforces this.)
- **Never** add Doctrine attributes to domain entities; ORM mapping belongs on `*Model` persistence classes in `Infrastructure/Persistence/Doctrine/`.
- **Never** edit generated files (`apps/api/openapi.json`, `packages/api-client-ts/src/types.gen.ts`) by hand.
- **Never** commit credentials, raw tokens, or `.env.local`.

## Validation Commands

All `make` targets run inside Docker containers — **never** invoke `php`, `pnpm`, `tsc`, or `eslint` directly on the host.

Choose the smallest relevant set for the change:

```bash
make lint          # phpstan + cs-fixer + deptrac + tsc + eslint (all inside containers)
make test          # phpunit (unit + functional) + Vitest (apps/web + apps/admin, all inside containers)
make build-web     # production Next.js build (web + admin)
make migrate-diff  # generates a Doctrine migration diff
```

### Worktree container workflow

`make test`, `make lint`, `make build-web`, `make migrate-diff`, `make gen-api` (and the
other CLI targets) run against a **headless, per-worktree test stack** that auto-starts on
first use — you do **not** need `make start`. The stack:

- is named per worktree (`<project>-test-<worktree-dir>`), so every worktree gets its own
  isolated containers + volumes (no stale vendor / `.next` across worktrees);
- publishes **no host ports**, so any number of worktrees run the CI gate in parallel with
  zero port conflicts;
- mounts the worktree's code, so it always validates the right tree.

```bash
# From anywhere inside the worktree — auto-starts the headless stack on first run:
make lint && make test          # CI gate, parallel-safe, no `make start` needed
make up-test                    # (optional) start/refresh the stack explicitly
make stop-test                  # tear down just this worktree's headless stack
```

`make start` is now only for **browser use**: it brings up the full dev stack (Traefik,
nginx, redis, minio, mailpit) and binds host ports, so it remains single-instance — run it
in one worktree at a time.

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
| Adding context AGENTS.md / guidelines | `.ai/skills/create-agents-md/SKILL.md` |
| **Auth** | |
| User aggregate, JWT, refresh tokens, security.yaml | `apps/api/src/User/AGENTS.md` + `docs/auth.md` |
| Adding ROLE_* checks | `apps/api/AGENTS.md` → Security |
| **Persistence** | |
| Database schema, naming conventions, *Model pattern, repository pattern, migrations | `docs/persistence.md` |
| New aggregate + `*Model` + repository | `docs/persistence.md` + `apps/api/AGENTS.md` |
| Encrypted columns, soft deletes, JSON fields | `docs/persistence.md` + `apps/api/AGENTS.md` |
| **Frontend** | |
| New page in `apps/web` or `apps/admin` | `apps/web/AGENTS.md` (or `apps/admin/AGENTS.md`) + `.ai/skills/scaffold-nextjs-page/SKILL.md` |
| Form with validation | `.ai/skills/scaffold-shadcn-form/SKILL.md` + `packages/ui-react/AGENTS.md` |
| Calling the API from the frontend | `packages/api-client-ts/AGENTS.md` |
| Regenerating the TS API client | `.ai/skills/regenerate-api-client/SKILL.md` |
| Shared component in `ui-react` | `packages/ui-react/AGENTS.md` |
| **Workflow** | |
| First-time project customization (rename placeholders, add project context) | `.ai/skills/customize-project/SKILL.md` |
| First-time local setup (hosts, .env.local, project personalization) | `.ai/skills/init/SKILL.md` |
| Starting an implementation branch (worktree from main) | `.ai/skills/new-feature/SKILL.md` — one `feat/<slug>` worktree covers spec + implementation |
| Committing and pushing with CI gate | `.ai/skills/check-and-commit/SKILL.md` |
| **Specs & PR Automation** | |
| Writing a spec for a new feature | `.ai/skills/spec-writing/SKILL.md` + `.ai/specs/AGENTS.md` |
| Pre-implementation audit | `.ai/skills/pre-implement-spec/SKILL.md` |
| Implementing an approved spec | `.ai/skills/implement-spec/SKILL.md` |
| Syncing context AGENTS.md after implementation | `.ai/skills/sync-context-docs/SKILL.md` |
| Code review (CI gate) | `.ai/skills/code-review/SKILL.md` |
| Auto PR workflows | `.ai/skills/auto-create-pr/SKILL.md`, `.ai/skills/auto-review-pr/SKILL.md`, `.ai/skills/merge-buddy/SKILL.md` |
| Cutting a release (CHANGELOG entry + GitHub release) | `.ai/skills/auto-update-changelog/SKILL.md` + Workflow Orchestration → Release path |
| **Research** | |
| Parallel codebase mapping before implementing in unfamiliar territory | `.ai/skills/parallel-research/SKILL.md` |
| **Testing** | |
| API functional / integration tests (PHPUnit) | `apps/api/AGENTS.md` → Testing + `.ai/qa/AGENTS.md` |
| Frontend unit tests (Vitest + RTL, apps/web + apps/admin) | `.ai/skills/integration-tests/SKILL.md` |
| Run PHP quality locally (PHPStan / cs-fixer / deptrac) | `.ai/skills/lint-php/SKILL.md` |
| Run JS quality locally (tsc / ESLint) | `.ai/skills/lint-js/SKILL.md` |
| **Bug Fixing** | |
| Root-cause analysis (failing test, production error, bisect) | `.ai/skills/root-cause/SKILL.md` |
| Implementing the minimal fix with regression test | `.ai/skills/fix/SKILL.md` |
| Security audit (OWASP, attack vectors) | `.ai/skills/auto-sec-report/SKILL.md` |
| **Ops** | |
| Docker, compose, K8s | `docs/ops.md` + `ops/AGENTS.md` |
| CI / GitHub Actions | `.github/workflows/ci.yml` — lint + test + build on every push |
| | `.github/workflows/release.yml` — release workflow |
| | `.github/workflows/skills-tiers-lint.yml` — validates skills tiers.json |

## Core Principles

- **Simplicity First.** Make every change as simple as possible.
- **No Laziness.** Find root causes. No temporary fixes.
- **Minimal Impact.** Changes should only touch what's necessary.
- **Boundaries are sacred.** A `User\Domain\` import inside `Orders\` is a regression, not a refactor.

## Workflow Orchestration

**Full spec-driven path (any non-trivial feature — 3+ steps or architectural decisions):**

```
Step 1 — Create worktree
  /new-feature feat/<slug>     ← one worktree covers both spec and implementation

Step 2 — Design (inside the worktree)
  /spec-writing                ← draft spec locally (committed to feat/<slug>, no separate spec PR)
  /pre-implement-spec .ai/specs/{file}.md   ← readiness report; fix gaps before coding

Step 3 — Implement (all on the same feat/<slug> branch, one PR at the end)
  /implement-spec .ai/specs/{file}.md       ← phase by phase, CI + code-review gate after each
                                             ← sync-context-docs runs per-phase as part of /implement-spec
  /open-pr                     ← single PR to main (includes spec + code)

Step 4 — Clean up (after PR merges)
  Exit worktree                ← or cd to main repo root
  sudo rm -rf .claude/worktrees/<name>
  git worktree prune
  git branch -d feat/<slug>
  make stop-test               ← tear down the headless test stack
```

**Bug-fixing path (failing test, production error, security finding):**

```
/root-cause          ← drill to the offending change (file:line, commit, PR)
/fix                 ← regression test first → minimal fix → CI gate → code review
/auto-create-pr      ← PR with `bug` label (or `security` for sec findings)
/verify-in-repo      ← (optional) sanity gate if you want to confirm the fix landed
```

Security findings from `/auto-sec-report` can also hand off to `/fix` with the report.

**Short path (small, already-specified addition where a full spec is overhead):**

Use `/scaffold-bounded-context`, `/add-command`, `/add-route` directly. Still run `make lint && make test` before `/open-pr`.

**Release path (cutting `v<version>` from main):**

`CHANGELOG.md` is the source of truth for release notes. `.github/workflows/release.yml` extracts the section matching the pushed tag and publishes it as the GitHub release body — there is no autogenerated changelog from commit messages.

```
# 1. Ensure main is current
git fetch origin && git checkout main && git pull origin main

# 2. Draft the release entry
/auto-update-changelog v<version>     ← inserts a `## [v<version>] — <date>` section in
                                        CHANGELOG.md from PRs merged since the previous
                                        tag, opens a docs PR (`documentation` + `skip-qa`).

# 3. Merge the docs PR

# 4. Tag and push
git pull origin main
git tag v<version>
git push origin v<version>            ← release.yml extracts the `[v<version>]` section
                                        from CHANGELOG.md and publishes the GitHub release.
                                        Mismatch between tag and section heading fails the job.
```

**Skill roles at a glance:**

| Skill | Phase | Purpose |
|-------|-------|---------|
| `/new-feature` | Setup | Creates a `feat/<slug>` worktree+branch from `main`. Called **once** per feature. |
| `/spec-writing` | Design | Drafts the spec locally on the feature branch. Does **not** open a spec-only PR. |
| `/pre-implement-spec` | Audit | Audits the local spec for gaps, missing tests, BC risks. Verdict must be "ready" before coding starts. |
| `/implement-spec` | Implement | Executes the spec phase by phase; runs the CI gate after each phase. |
| `/sync-context-docs` | Document | Updates `apps/api/src/<Context>/AGENTS.md` for every context touched by the branch. Run per-phase inside `/implement-spec` before the verification gate. |
| `/open-pr` | Ship | Opens the implementation PR (spec + code) using the repository PR template. |
| **Bug fixing** | | |
| `/root-cause` | Diagnose | Drills from a failure to the offending change (file:line, commit, PR). Never fixes — hands off to `/fix`. |
| `/fix` | Repair | Regression test first, then minimal fix, CI gate, code review, hands off to `/auto-create-pr`. |
| `/auto-sec-report` | Audit | Paranoid OWASP-oriented security analysis. Hands off to `/fix` with the report. |

Use `/scaffold-bounded-context`, `/add-command`, `/add-route` **directly** only for small, already-specified additions where a full spec would be overhead.

1. **Spec-first** for non-trivial tasks (3+ steps or architectural decisions). Check `.ai/specs/` first. Skip for small fixes.
2. **Subagent strategy**: use subagents for research / parallel analysis. One task per subagent. Keeps main context clean. Use `/parallel-research` for structured multi-angle codebase exploration before implementing in unfamiliar territory.
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

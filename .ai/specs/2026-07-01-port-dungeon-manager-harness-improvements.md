# Port dungeon-manager harness & standalone-gate improvements

## TLDR

`dungeon-manager` (a project built from this template) has diverged with infra/AI-harness
improvements worth folding back: a **standalone CI-gate architecture** that runs PHP and JS
lint/build in ephemeral, dependency-free containers (no postgres, no host ports) instead of
the current model where every gate pays for the full `up-test` stack; a **readiness sentinel
+ crash self-diagnosis** for the stack that *is* still needed (DB-backed gates); a worktree
**branch-naming bug fix** (`feat/<slug>` → `feat-<slug>`) that we hit ourselves this session;
and a handful of generic AI-harness docs (an always-fetch-main invariant, a hotfix path, two
new lessons, automated spec archiving). Playwright is explicitly excluded — dungeon-manager
tried it and froze it ("Playwright e2e is frozen... we don't develop it further"); this
template keeps Vitest + RTL as its only frontend test layer, unchanged.

## Overview

This template and `dungeon-manager` share the same monorepo skeleton and AI-agent harness
(`.ai/`, `.claude/`). `dungeon-manager` has been live longer and has accumulated
infrastructure fixes purely through running into real problems (slow CI-gate timeouts on
crash loops, `/` characters breaking Docker Compose project names, every lint run paying for
a Postgres-backed stack it never queries). None of this is business logic — it's tooling,
Makefile, Docker Compose, and `.ai/`/`.claude/` documentation. The goal is to fold the
generic, validated subset back into the template so every project created from it starts
with these fixes already in place, without dragging in anything dungeon-manager-specific
(its bounded contexts, its k3s production section, its frozen Playwright experiment, its
self-hosted CI runners).

## Problem Statement

1. **Every lint/build gate pays for the full stack.** `make lint-api`, `make lint-web`,
   `make test-web`, and `make build-web` all currently depend on `up-test`, which starts
   `postgres + api + web + admin` — even though PHPStan/cs-fixer/deptrac and tsc/ESLint/Vitest
   need none of that. This template's own CI (`.github/workflows/ci.yml`) already proves the
   PHP lint job needs no Postgres service; the local Makefile path doesn't reflect that.
2. **A crash-looping container hangs for the full 600s timeout.** `wait-for-test-stack.sh`'s
   readiness check (`vendor/autoload.php` exists) false-passes on a crash-looping `api`
   container because `vendor` is a persisted named volume — it survives the crash. The
   developer/agent waits the full timeout before seeing any error.
3. **`EnterWorktree` mangles `feat/<slug>` branch names.** A `/` in the branch name becomes
   `+` in the worktree directory name (`.claude/worktrees/feat+<slug>`), which then leaks into
   `TEST_PROJECT_NAME` (`$(notdir $(PWD))`) and produces an invalid Docker Compose project
   name. We hit this directly at the start of this session and had to manually rename the
   branch.
4. **No automated spec archiving.** `.ai/specs/implemented/` already exists as a destination,
   but nothing moves a merged spec there — it's manual or forgotten.
5. **No documented lightweight path for urgent, root-cause-already-known fixes.** Every
   change goes through the full worktree + spec flow, which is overkill for a 1-3 file hotfix.

## Proposed Solution

Port the validated, generic subset of dungeon-manager's fixes, organized into independently
shippable phases. Each phase leaves `make lint && make test` green.

### A. Standalone CI-gate architecture (the core of this spec)

Split every `make` gate into one of two buckets:

- **Standalone gates — no postgres, no shared stack.** PHP static analysis
  (`lint-api`, `lint-shared-kernel`, `lint-fix`) runs in an **ephemeral** `api` container via
  `docker compose run --rm --no-deps`, reusing cached `api_vendor`/`api_var` named volumes so
  `composer install` is a fast no-op once populated. JS gates (`lint-web`, `test-web`,
  `build-web`) run the same way in ephemeral `node:22-alpine` containers, reusing cached
  `node_modules` volumes. None of these start Postgres or bind a host port.
- **DB-backed gates — still use the shared per-worktree stack.** `test-api` (and
  `test-unit`/`test-functional`), `build-api`, `migrate`, `migrate-diff`, `gen-api`,
  `jwt-keys`, `db-create`, `db-reset`, `db-shell`, `api-shell`, `composer-install`,
  `composer-require` keep using `up-test`, which now starts only **`postgres + api`** (not
  `web`/`admin` — those move to the standalone bucket entirely). `gen-api` is the one
  DB-backed target with a frontend step (see below) and needs special handling.
- `lint-fix` and `lint-shared-kernel` also move to the standalone bucket alongside
  `lint-api`/`lint-web` (they're PHP/JS static-analysis tooling, not DB-backed).

Three existing skills currently describe the *old* behavior (shared persistent containers,
`docker exec <project>-api-1`/`<project>-web-1` by name, "`make lint-api` auto-starts a
headless test stack") and go stale once the gates above become standalone/ephemeral — they're
in scope alongside the Makefile/compose changes:
- `.ai/skills/lint-php/SKILL.md` — drop the "auto-starts a headless test stack" framing and
  the `docker exec <project>-api-1 ...` step-by-step commands (no named persistent container
  exists for standalone gates); point at `make lint-api` as the one-liner instead.
- `.ai/skills/lint-js/SKILL.md` — same fix for `<project>-web-1`/`<project>-admin-1`.
- `.ai/skills/integration-tests/SKILL.md` — its current workflow does `make up-test` then
  execs into the `web`/`admin` containers to run Vitest; once those containers no longer
  start with `up-test`, that sequence breaks. Repoint at `make test-web` (now standalone)
  for frontend tests.

New Makefile variables (mirroring dungeon-manager, generalized for this template's
service/package names; `-T` added to both for non-TTY consistency with the existing
`EXEC := exec -T`; install prefixes pinned to the same `corepack prepare pnpm@11.5.0
--activate` already used by `docker-compose.test.yml`'s `web`/`admin` startup commands, not
just `corepack enable`):

```makefile
JS_RUN  := ${DOCKER_COMPOSE_TEST} run --rm --no-deps -T
WEB_INSTALL   := corepack enable && corepack prepare pnpm@11.5.0 --activate && pnpm install --filter "@jperdior/web..." --filter "./packages/*"
ADMIN_INSTALL := corepack enable && corepack prepare pnpm@11.5.0 --activate && pnpm install --filter "@jperdior/admin..." --filter "./packages/*"

PHP_RUN := ${DOCKER_COMPOSE_TEST} run --rm --no-deps -T ${API_CONTAINER}
PHP_INSTALL := composer install --no-interaction --no-progress && php bin/console cache:warmup
```

**Every** standalone gate recipe line MUST embed its full install prefix
(`${JS_RUN} <svc> sh -c '${WEB_INSTALL} && <command>'` / `${PHP_RUN} sh -c '${PHP_INSTALL}
&& <command>'`) — `run --rm` destroys the container filesystem per invocation, so
`corepack`'s PATH setup does not persist between recipe lines the way it does in the
persistent `up-test` containers. Only the named volumes (`node_modules`, `vendor`,
`pnpm_store`, `.next`) persist, which is what keeps the install a fast no-op. A bare
`${JS_RUN} web pnpm ...` without the install prefix fails with "pnpm: not found".

`docker-compose.test.yml`'s `web`/`admin` services keep their `sleep infinity` idle command
(used by `exec`-based persistent workflows like `make api-shell`-equivalents if ever added)
but are no longer brought up by `up-test` — they're only touched via `run --rm --no-deps`.
**Exception: `gen-api`.** It currently `exec`s into the persistent `web` container
(`pnpm -C packages/api-client-ts gen`) after regenerating `apps/api/openapi.json` via the
`api` container. Since `web` no longer starts with `up-test`, `gen-api`'s second step must
be converted to standalone too: `${JS_RUN} ${WEB_CONTAINER} sh -c '${WEB_INSTALL} && pnpm -C
packages/api-client-ts gen'`, run after the (still DB-backed) OpenAPI dump step, with
`stop-test` torn down afterward as today.

A new skill, **`run-gates`**, becomes the single source of truth for invoking the gate set:
it scopes gates to the diff (`git diff --name-only <base>...HEAD`), dispatches each in-scope
gate as a parallel subagent, brings up the shared stack once *only if* `test-api` is in
scope, and reports a PASS/FAIL table. `check-and-commit` and `code-review` are updated to
invoke `/run-gates` in place of the individual `lint-api`/`lint-web`/`test-api`/`test-web`/
`build-web` target tables they currently enumerate by hand; `implement-spec`'s verification-gate
step is updated the same way. **Skill registration is not just the `.claude/skills` symlink**
— `.ai/skills/tiers.json` is the actual source of truth (`scripts/install-skills.sh` purges
and rebuilds `.claude/skills/` from it on every `make init`), so `run-gates` must get a
`tiers.json` entry (in the `core` tier, alongside its callers) or it's silently dropped on
the next fresh clone. For consistency, `auto-review-pr` and `open-pr` (which also hand-describe
the gate today) are updated to reference `/run-gates` too; `fix`/`root-cause` are left as-is
per their existing, narrower scope (smallest-relevant-gate philosophy, not the full diff-scoped
gate).

### B. Readiness sentinel + crash self-diagnosis (for the DB-backed stack that remains)

- `api`'s startup command in `docker-compose.test.yml` removes `/tmp/stack-ready` at the start
  of every (re)start and only re-creates it once `composer install` + JWT keygen + DB
  create/migrate fully succeed. `/tmp` is container-local, not a volume, so it resets on every
  start — unlike the current `vendor/autoload.php` check, which persists across crashes.
- `wait-for-test-stack.sh` polls `/tmp/stack-ready` instead, and adds crash detection: it
  baselines each service's Docker `RestartCount` before polling, flags a service as
  crash-looping once it accumulates **2+ new restarts** since the baseline (so a single
  recovery-after-fix restart is never mistaken for a crash loop), dumps the last 80 log lines,
  and classifies the cause — `TRUE OOM` (only when `State.OOMKilled=true`), `PHP memory_limit
  exhausted`, `PHP CODE ERROR` (parse/fatal/autowire), or a generic fallback — instead of
  silently waiting out the full timeout.
- Since `up-test` now only starts `postgres + api`, the sentinel/crash-detection logic applies
  to `api` only — the current script's `web`/`admin` readiness checks (`test -d
  /repo/apps/{web,admin}/node_modules`) are removed along with their `up-test` membership.
- `wait-for-test-stack.sh`'s shebang is `#!/bin/sh`, invoked as `sh ops/scripts/...` (this
  host resolves `/bin/sh` to `dash`), and the existing script is already strict-POSIX. The
  rewrite MUST stay strict-POSIX too: no `[[ ]]`, no bash arrays (per-service restart-count
  baselines use a plain shell variable, not an array — there is only one service, `api`, to
  baseline), no process substitution. Containers are resolved via `${DOCKER_COMPOSE_TEST} ps
  -q api` (already the pattern the current script uses for its readiness `exec`), never a
  guessed `<project>-api-1` name, since the per-worktree project prefix makes that name
  unpredictable.
- `docker-compose.test.yml`'s and `wait-for-test-stack.sh`'s header comments (which currently
  describe the stack as "postgres + api + web + admin") are updated to "postgres + api" as
  part of this phase, so they don't go stale.

### C. Branch-naming fix

- `new-feature` (and every skill/doc that documents the convention: `spec-writing`,
  `implement-spec`, `init`, `auto-create-pr`, `.ai/specs/AGENTS.md`, root `AGENTS.md`,
  `README.md`, `docs/ai-workflow.md`) switches branch naming from `feat/<slug>` to
  `feat-<slug>` — lowercase, hyphen-only, no `/`. `auto-create-pr/SKILL.md` has **live shell
  logic**, not just prose, that must change functionally: `[[ "$BRANCH" == feat/* ]]` →
  `feat-*`, and the `git worktree add -b feat/{slug}` / `git push -u origin feat/{slug}`
  construction → `feat-{slug}`.
- Defense in depth: `TEST_PROJECT_NAME` sanitizes any remaining `+` defensively —
  `$(PROJECT_NAME)-test-$(shell echo $(notdir $(PWD)) | tr '+' '-')` — so a worktree directory
  name containing `+` (from any source, not just this specific bug) never produces an invalid
  Compose project name.
- This worktree's own branch (`feat/port-dungeon-manager-improvements`, manually renamed at
  session start) is not touched by this spec — the convention applies going forward, to
  worktrees created after this change merges.

### D. Misc Docker/PHP fixes

- New `ops/docker/api/php-dev.ini`, mounted read-only at
  `/usr/local/etc/php/conf.d/zz-dev.ini` in the test-stack `api` service: disables
  `opcache.validate_timestamps`-off (sets it to `1` with `revalidate_freq=0`) so file changes
  are picked up immediately inside the dev/test container without a restart.
  Mounted in the **test** compose overlay only, the same pattern already used for `web`/`admin`'s standalone, dev-shaped behaviour.
- `Dockerfile` (`ops/docker/api/Dockerfile`): add a BuildKit cache mount on the
  `composer install --no-dev` layer (`--mount=type=cache,target=/tmp/composer-cache`) so
  re-installs stay fast even when `composer.json` busts the layer cache.
- `Dockerfile`: rename the PHP-FPM pool config destination from `zz-app.conf` to
  `zzz-app.conf`. This is a real ordering bug, not cosmetic — FPM applies pool `.conf` includes
  alphabetically, and `zz-app.conf` sorts *before* the base image's own `zz-docker.conf`,
  so the base image's pool directives silently win over ours today. In this template the
  overridden directive is **`listen`** (this template's `php-fpm.conf` has no `access.log`
  directive — that's specific to dungeon-manager's pool config, not ported here). `zzz-`
  sorts after `zz-docker.conf`, fixing the override. `php.ini`'s destination
  (`zz-app.ini`, a different directory/extension) is unaffected and intentionally left as-is.

### E. AI-harness documentation

- Root `AGENTS.md`: new **"INVARIANT — Always refresh main before branching"** section
  (`git fetch origin && git checkout main && git pull origin main` before any
  `/new-feature` or manual branch). Placed at the top of the file, above the project
  description, mirroring its severity.
- Root `AGENTS.md`: rewrite the "Worktree container workflow" section to describe the
  standalone-vs-DB-backed gate split (validation commands table, which gates need the shared
  stack and which don't), and add the **"Crash self-diagnosis"** subsection explaining the
  `PHP CODE ERROR — NOT OOM` banner and when (and when not) to raise memory limits.
- Root `AGENTS.md`: new **"Hotfix path"** in the Workflow Orchestration section — for an
  urgent fix with an already-known root cause and a ≤3-file change, branch directly off
  `main` as `fix-<slug>` (no worktree), run the smallest relevant gate
  (`make build-web` / `make lint-api && make test-api ARG="--filter <Context>"` /
  full `make lint && make test`), skip the spec. Reuses the existing `fix` and `root-cause`
  skills as-is — no new skill file, just the documented shortcut and a Task Router row.
- `ops/AGENTS.md`: add the "Test stack crash self-diagnosis" section (mirrors dungeon-manager's,
  adjusted for `api`-only since `web`/`admin` are no longer part of the shared stack).
- `.ai/lessons.md`: two new entries, genericized (no dungeon-manager class names):
  - **L-008 — HTTP security guards do not apply to console commands.** `#[IsGranted]` on a
    controller only fires for HTTP requests; a console command dispatching the same command
    through the bus bypasses it entirely. If a use case must enforce a role invariant
    regardless of caller, validate inside the use case itself, not only on the controller.
  - **L-009 — Cross-context `*Model` imports are allowed at the Persistence boundary.** A
    repository under `<Context>/Infrastructure/Persistence/Doctrine*Repository.php` MAY
    import another context's `*Model::class` for QueryBuilder JOIN expressions — the coupling
    stays at Infrastructure only (Domain/Application/Presentation cross-context imports
    remain forbidden). Add a `skip_violations` entry in `apps/api/deptrac.yaml` for the
    specific repository + import when this is used. Raw-SQL JOINs on table names need no
    `skip_violations` entry (deptrac sees PHP imports, not SQL strings).
- New `.github/workflows/archive-specs.yml`: on `pull_request.closed` (merged) to `main`,
  finds any new `.ai/specs/*.md` added by the PR (excluding `AGENTS.md`/`CLAUDE.md` and
  anything already under `implemented/`), `git mv`s them into `.ai/specs/implemented/` on a
  new branch, and **opens a PR** (`peter-evans/create-pull-request`, labeled
  `documentation` + `skip-qa`, mirroring the existing `/auto-update-changelog` docs-PR
  pattern) rather than pushing to `main` directly. **This is a deliberate change from
  dungeon-manager's version**, which pushes straight to `main` — that doesn't work here: this
  repo's `main protected` ruleset (active, id 17090362) requires a PR with 1 approval and
  passing status checks, and `github-actions[bot]` is not a bypass actor, so a direct push
  would silently fail. A PR-based archival also matches this template's own governance rule
  ("every change lands via a PR," `AGENTS.md`). Includes a `concurrency` group keyed on the
  workflow name to avoid races if two PRs merge close together.

### Explicitly excluded (verified project-specific or not generally applicable)

- All Playwright work (services, `test-e2e`/`test-e2e-admin` targets, the `e2e` Compose
  profile, the frozen e2e skill content) — per decision, this template stays Vitest + RTL only.
- Production k3s section, dungeon-manager's `phpstan.dist.neon` `ignoreErrors` (its own test
  paths), its `.php-cs-fixer.dist.php` `RefreshToken.php` exclusion, AMQP version pin,
  `.env`/`data` `COPY` lines in the API Dockerfile, self-hosted CI runners, the `ui-design`
  skill, and Task Router rows for dungeon-manager's own bounded contexts (Content, Ruleset,
  etc.) — all project-specific, not generic harness improvements.
- `lint-api`'s php-cs-fixer step stays `--dry-run --diff` (not dungeon-manager's auto-fix) —
  this template's own `ops/AGENTS.md` already states "Never run `php-cs-fixer fix` (writing)
  inside CI — only `--dry-run`. Fixes are local-only," and `make lint-fix` already exists as
  the explicit fix path. Porting auto-fix into `lint-api` would contradict an existing,
  intentional rule.
- No change to `.github/workflows/ci.yml` — it already runs `php-lint` with no `services:`
  block (no Postgres), matching the philosophy of this spec; only `php-tests-functional`
  declares a Postgres service. CI already does what this spec brings to the local Makefile.

## Architecture

Not applicable in the DDD/bounded-context sense — no aggregates, buses, or domain code are
touched. This is exclusively `Makefile`, `ops/docker/*`, `ops/scripts/*`, `.ai/*`,
`.claude/*`, and one new `.github/workflows/archive-specs.yml`.

## Data Models / API Contracts / Frontend Plan

Not applicable — no persistence, endpoint, or frontend-route changes.

## Phasing

| Phase | Goal | Deliverable |
|-------|------|-------------|
| 0 | Spec drafted (this document) | Committed to `feat/port-dungeon-manager-improvements` |
| 1 | Branch-naming fix + `TEST_PROJECT_NAME` sanitization | `new-feature`, `spec-writing`, `implement-spec`, `init`, `auto-create-pr` (incl. its `feat/*` glob and `feat/{slug}` construction), `.ai/specs/AGENTS.md`, root `AGENTS.md`, `README.md`, `docs/ai-workflow.md` updated to `feat-<slug>`; Makefile `tr '+' '-'` added. `make lint && make test` green. **This phase must land and the gate must be re-run in this worktree first** — `.claude/worktrees/feat+port-dungeon-manager-improvements` itself has a `+` in its dirname, so `TEST_PROJECT_NAME` is invalid until this phase's `tr` fix is in place; later phases can't be validated here until then. |
| 2 | Readiness sentinel + crash self-diagnosis | `docker-compose.test.yml` `api` service writes `/tmp/stack-ready`; `wait-for-test-stack.sh` rewritten with `restart_count`/`crashed`/`report_crash`, strict-POSIX (dash-safe: no `[[ ]]`, no arrays, containers resolved via `${DOCKER_COMPOSE_TEST} ps -q api`, never a guessed name); header comments in both files updated to "postgres + api"; `ops/AGENTS.md` + root `AGENTS.md` document it. Verified by intentionally breaking a PHP file, confirming the banner appears within seconds instead of a 600s hang, then reverting; `sh -n` (or `checkbashisms` if available) run against the rewritten script. |
| 3 | Standalone gate architecture | `Makefile` restructured (`JS_RUN`/`PHP_RUN` with `-T` and full install prefixes per line, `up-test` scoped to `postgres+api`, `lint`/`lint-api`/`lint-shared-kernel`/`lint-fix`/`lint-web`/`test-web`/`build-web` made standalone, `gen-api`'s frontend step converted to standalone `${JS_RUN}`); `docker-compose.test.yml` `web`/`admin` no longer started by `up-test`; `lint-php`, `lint-js`, `integration-tests` skills updated to drop stale "auto-starts a headless stack" / named-container-exec framing. Verified: `make lint` and `make test-web`/`make build-web` succeed with `docker ps` showing no `postgres` container for those targets in isolation; `make gen-api` still succeeds end-to-end; full `make lint && make test` still green. |
| 4 | `run-gates` skill | New `.ai/skills/run-gates/SKILL.md` **+ `.ai/skills/tiers.json` entry (`core` tier)** + `.claude/skills` symlink generated via `scripts/install-skills.sh` (not hand-created — it's purged/rebuilt from `tiers.json`); `check-and-commit`, `code-review`, `implement-spec`, `auto-review-pr`, `open-pr`, root `AGENTS.md` updated to invoke `/run-gates` in place of their hand-enumerated target tables. |
| 5 | Misc Docker/PHP fixes | `php-dev.ini` added + mounted; Dockerfile BuildKit cache mount + `zzz-app.conf` rename. `make build-api` / `make lint-api` still green; manually verify the FPM `listen` override takes effect as intended (the directive this template's pool config actually sets, not `access.log`). |
| 6 | AI-harness docs | INVARIANT section, hotfix path, two new lessons, `archive-specs.yml` (PR-based, not direct-push — see Proposed Solution → E). No executable verification beyond `make lint && make test` staying green; `archive-specs.yml`'s YAML validated with `actionlint` or `gh workflow view` if available. Note: since this workflow doesn't exist on `main` until this very PR merges, and it only scans for specs *added by the merging PR* (not all unarchived specs), this spec will not auto-archive itself — archive it with a manual follow-up `git mv` once merged. |

Each phase ends with `make lint && make test` passing per this template's mandatory pre-PR
gate; phases 1-5 additionally get a manual smoke check as described above since the change
surface is infra, not application code with automated test coverage.

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| `gen-api` `exec`s into the `web` container, which `up-test` no longer starts once `web`/`admin` move out of the shared stack | High | Makefile (`gen-api`) | Convert `gen-api`'s frontend step to standalone `${JS_RUN}` (see Proposed Solution → A); covered in Phase 3's deliverable and verified by an end-to-end `make gen-api` run | None — found by audit, fixed in this revision |
| `archive-specs.yml` pushes directly to `main`, which the repo's active branch-protection ruleset (id 17090362: PR + 1 approval + required status checks) blocks for non-bypass actors like `github-actions[bot]` | High | `.github/workflows/archive-specs.yml` | Re-architected to open a PR via `peter-evans/create-pull-request` instead of pushing (see Proposed Solution → E) | None — found by audit, fixed in this revision |
| Ephemeral `run --rm --no-deps` containers fail to find cached named volumes on first run, slow first invocation | Low | Local dev / CI gate speed | Document that first run after this change reinstalls deps once per volume; subsequent runs are fast no-ops, same as today's `up-test` behavior | First-run slowdown, one-time per worktree |
| Rewritten `wait-for-test-stack.sh` introduces a bash-only construct (array, `[[ ]]`, process substitution) that silently breaks under the host's actual `/bin/sh` (dash) | Medium | `ops/scripts/wait-for-test-stack.sh` | Single-service (`api`-only) baseline needs no array; `sh -n` (and `checkbashisms` if available) run against the script during Phase 2; container resolution via `compose ps -q`, never a guessed name | Low — constrained design (one service) makes bashisms unlikely to be needed at all |
| A standalone gate recipe line omits its install prefix (`${WEB_INSTALL}`/`${PHP_INSTALL}`), since each `run --rm` is a fresh container and `corepack`'s PATH setup doesn't persist like it does in `up-test`'s long-running containers | Medium | Makefile (Phase 3 targets) | Every standalone recipe line is reviewed against the "full prefix per line" rule during Phase 3 implementation and would fail loudly (`pnpm: not found` / `php: not found`) under `make lint && make test` if missed — caught by the gate itself, not silent | None — self-detecting via the CI gate |
| `zzz-app.conf` rename changes FPM pool-include ordering in a way that breaks something else relying on the old (buggy) order | Low | `ops/docker/api/php-fpm.conf` | The bug means the override (`listen`) never applied before; fixing it can only add the intended behavior, not remove existing behavior | None expected |
| New `archive-specs.yml` opens a PR mid-flight for a spec that isn't actually "done" yet (PR merged before all phases finished, e.g. a phased rollout) | Low | `.ai/specs/` | Workflow only fires on `pull_request.closed` + `merged == true` to `main`; this template's own flow merges spec + all phases in a single PR, so by definition the spec is "done" when that PR merges; being PR-based (not direct-push) also means a human reviews before it lands | None — matches this template's actual spec lifecycle (single combined PR) |
| `TEST_PROJECT_NAME` sanitization papers over `EnterWorktree`'s `/`→`+` mangling instead of fixing it at the source | Low | Tooling | Branch-naming convention change (Phase 1) addresses the root cause; the `tr` fix is defense-in-depth for any other source of `+` | None |

## Backward Compatibility

- [x] No removed/renamed event IDs — none touched.
- [x] No removed/renamed API routes — none touched.
- [x] No removed response fields — none touched.
- [x] No removed DB columns — none touched.
- [x] No deprecation bridge needed — pure tooling change; existing `feat/<slug>` worktrees
      already in flight are unaffected (the convention only governs new worktrees created by
      `/new-feature` after this merges), and `make lint`/`make test`/`make build-web` keep
      their existing names and externally-visible behavior (pass/fail), only their internal
      Docker plumbing changes.

## Integration Coverage

Not applicable in the PHPUnit/Vitest sense — no application code changes. Verification is
infra-level and described per-phase above (`make lint && make test` plus the targeted manual
smoke checks: crash-banner timing, `docker ps` scoping, FPM access-log override).

## Final Compliance Report

| Gate | Question | Result |
|------|----------|--------|
| Boundary | Cross-context `Domain/`/`Application/` import? | N/A — no domain code touched |
| Bus | Controllers dispatch through `CommandBus`/`QueryBus`? | N/A — no controllers touched |
| Mapping | `#[ORM\*]` attributes on a domain entity? | N/A — no entities touched |
| Validation | Inputs validated at value-object construction? | N/A — no value objects touched |
| Idempotency | Subscribers/workers idempotent under retry? | N/A — no subscribers/workers touched |
| Auth | Protected endpoints declare `ROLE_*`? | N/A — no endpoints touched |
| Naming | Singular aggregate/command/event names? | N/A — no aggregates/commands/events added |
| DateTime | `DateTimeImmutable` in domain code? | N/A — no domain code touched |
| Final readonly | DTOs/queries/responses `final readonly`? | N/A — no PHP application classes added |
| `strict_types` | Every PHP file in `src/`/`tests/`? | N/A — no new PHP source files (Dockerfile/ini/Makefile/shell/YAML/Markdown only) |
| Tests | PHPUnit + Vitest coverage planned? | N/A — infra change; verification is `make lint && make test` plus manual smoke checks per phase (see Phasing) |
| BC | Contract surface removed/renamed without bridge? | No — see Backward Compatibility |

This spec touches no bounded context, domain, application, or presentation code — every gate
above is N/A by virtue of scope, not skipped. The one substantive verification requirement
(does the change actually work) is covered by the per-phase manual checks above plus the
standing `make lint && make test` gate.

## Changelog

| Date | Change |
|------|--------|
| 2026-07-01 | Spec drafted. |
| 2026-07-01 | Revised after `/pre-implement-spec` audit (3 parallel agents): fixed `gen-api` breakage, re-architected `archive-specs.yml` as PR-based (branch-protection ruleset blocks direct push), added missed files (`README.md`, `docs/ai-workflow.md`, `integration-tests`/`lint-php`/`lint-js` skills, `tiers.json`), corrected the `zzz-app.conf` rationale (`listen`, not `access.log`), added POSIX-dash and per-line-install-prefix constraints. |

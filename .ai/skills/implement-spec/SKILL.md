---
name: implement-spec
description: Implement an approved spec from .ai/specs/, phase by phase, on the subagent-driven-development engine with the CI verification gate enforced as the per-phase review rubric and a single code review once all phases are done. Triggers on "implement spec", "build from spec", "code the spec", "implement phase X".
---

# Implement Spec

Execute an approved spec under `.ai/specs/{date}-{slug}.md`. This skill is a
**project-specific overlay on `superpowers:subagent-driven-development` (SDD)**:
SDD owns the execution *machinery* (per-task fresh implementer, ledger,
review-package, fix loop); this skill owns the *project mapping* — what a task
is, and what the reviewer's rubric is.

## The Execution Model — one spec phase = one SDD task

The spec's **phases are the SDD tasks.** SDD dispatches a fresh implementer
subagent per task; here that subagent implements exactly one spec phase, with
**zero inherited session context** — you construct its brief from the phase
text plus the interfaces earlier phases produced. This is the whole reason to
run on SDD: a phase implemented by a context-free subagent can't drift on
half-remembered conversation, and the ledger survives compaction.

You do **not** need a separate `writing-plans` pass for this. The spec phase IS
the task brief. Only reach for `superpowers:writing-plans` first when a single
phase is too coarse to hand a blind subagent — i.e. the phase spans several
independent files with non-obvious interfaces between them. Then decompose that
one phase into `writing-plans` tasks and run them as SDD sub-tasks; the rest of
the spec still runs phase-as-task.

## Superpowers Integration

**Primary engine — invoke and follow it:**
- `superpowers:subagent-driven-development` — owns the task loop. Use its
  `sdd-workspace`, `task-brief`, and `review-package` scripts, its ledger
  (`<workspace>/progress.md`), its 5-round fix loop with model escalation, and
  its final whole-branch review. **Do not re-implement any of that here** — this
  skill only supplies the two project-specific pieces below.

**Invoked by the implementer subagent inside each task:**
- `superpowers:test-driven-development` — Red → Green → Refactor within the
  phase; no production code before a failing test.
- `superpowers:verification-before-completion` — read full gate output, confirm
  0 errors before the implementer reports DONE.

**Two project overlays this skill contributes to the SDD loop:**

1. **Task mapping** — one spec phase = one SDD task (see above).
2. **The per-task review rubric IS this project's CI gate stack.** Where vanilla
   SDD dispatches a generic `task-reviewer`, here the per-phase review runs
   `/sync-context-docs` → `/run-gates` → OpenAPI-drift as its pass/fail criteria
   (see *Per-phase review* below). `/code-review` is **not** part of the
   per-phase rubric — it runs once, over the whole branch, as SDD's final review,
   so review reasons about the finished feature instead of re-reviewing churn
   each phase.

## Prerequisites

- The spec exists under `.ai/specs/` on the current `feat-<slug>` branch
  (committed locally).
- The spec passed `/pre-implement-spec` with verdict = ready.
- An isolated worktree on a `feat-<slug>` branch exists (created by
  `/new-feature`). SDD requires an isolated workspace; never implement on `main`.
- No manual container startup needed. The **lint/build gates** (`make lint-api`,
  `make lint-shared-kernel`, `make lint-web` / `make test-web` / `make build-web`)
  run standalone in ephemeral containers — **no postgres/api**. Only the **PHP
  test gate** (`make test-api`) auto-starts a headless, per-worktree, port-free
  stack (postgres + api). Multiple worktrees run gates in parallel without
  conflict; `make start` is only for browser/e2e.

If any precondition fails, stop and inform the user.

## Setup

Follow SDD's Setup step exactly, with these project specifics:

1. The worktree already exists (`/new-feature` made it). Confirm you are inside
   it on the `feat-<slug>` branch — never on `main`.
2. Resolve the plan's workspace with SDD's `scripts/sdd-workspace <spec-file>`
   and check for an existing ledger at `<workspace>/progress.md`. A ledger whose
   first line names **this spec file** means work is resumable — phases with a
   `Task <N>: complete` line are DONE; resume at the first phase without one. Do
   not re-dispatch completed phases.
3. If no ledger, create it with `# SDD ledger — plan: <spec file path>` as the
   first line.
4. Read the spec once. Note its Global Constraints (version floors, naming/copy
   rules, BRs that bind every phase). Scan for cross-phase conflicts and batch
   them to the user before dispatching phase 1 (SDD's pre-flight scan).

## Per-phase loop

For each phase, run SDD's task loop. The project-specific content:

### 1. Dispatch the implementer

Record BASE (`git rev-parse HEAD`) first. Build the brief from the phase text —
do **not** paste prior-phase summaries or session history. The dispatch carries:
one line on where the phase fits; the phase's deliverables/files/tests verbatim;
the interfaces earlier phases produced (exact signatures the fresh subagent
can't otherwise know); the spec's Global Constraints; and this project's
build rules the implementer must honour:

- **PHP**: bounded-context layout. Use `/scaffold-bounded-context` for new
  contexts, `/add-command`, `/add-query`, `/add-route` for additions. Never
  import framework code in `Domain/`; never bypass the bus; never cross another
  context's `Domain/`/`Application/`.
- **Frontend**: route shape under `apps/web/src/app/` or `apps/admin/src/app/`.
  Use `/scaffold-nextjs-page`, `/scaffold-shadcn-form`.
- **i18n (apps/web)**: any user-facing string added in `apps/web` MUST go through
  the next-intl catalogs, not a hard-coded literal. The implementer runs
  `/translate-strings` before reporting DONE. In-app links use `@/i18n/navigation`.
- **Migrations**: `make migrate-diff` to generate; review the SQL; commit it.
- **API contract**: if the phase touches `apps/api` in any OpenAPI-affecting way
  (routes, DTOs, annotations), run `make gen-api` and commit the regenerated
  `apps/api/openapi.json` + `packages/api-client-ts/src/types.gen.ts` in the same
  phase commit.
- **Tests**: PHPUnit Functional next to the controller under
  `apps/api/tests/Functional/` for backend behaviour; Vitest + RTL colocated
  under `apps/web/src/**/__tests__/` or `apps/admin/src/**/__tests__/` for every
  new frontend feature (extract pure logic into a dependency-free module and
  co-locate its test). Do not add a per-feature Playwright spec —
  `apps/web/e2e/` is a shared smoke journey.

### 2. Handle the report

Per SDD: DONE → review; DONE_WITH_CONCERNS → read concerns first;
NEEDS_CONTEXT → provide and re-dispatch; BLOCKED → assess (context vs. model vs.
too-large vs. plan-wrong→escalate). If the spec proves wrong mid-phase, stop,
update the spec, re-run `/pre-implement-spec`, then resume.

### 3. Per-phase review — the CI gate stack IS the rubric

This replaces SDD's generic `task-reviewer`. Every phase must pass **all**
in-scope gates before its ledger line is written:

1. **`/sync-context-docs`** — update AGENTS.md for every context touched, update
   `docs/persistence.md` if schema changed, update the spec's Changelog.
2. **`/run-gates`** — scopes gates to the phase diff and dispatches each as a
   parallel subagent (frontend lint/build standalone; `test-api` on the shared
   stack). `/run-gates` decides whether the diff warrants the isolated `test-e2e`
   gate — let it call that. Every in-scope gate MUST report PASS.
3. **OpenAPI drift** (if the phase touched `apps/api`): `make gen-api` then
   `git diff --exit-code -- apps/api/openapi.json packages/api-client-ts/src/types.gen.ts`
   must be clean — regenerated artifacts committed with the phase.

**Do not run `/code-review` here.** It runs once over the whole branch after all
phases (see *After all phases* below), so review reasons about the finished
feature instead of re-reviewing churn each phase. A gate failure opens SDD's fix
loop.

### 4. Fix loop

Exactly SDD's loop — 5 rounds max, rounds 1-3 resume the implementer, rounds 4-5
fresh implementer on a more capable model, every round ends with a scoped
re-review (re-run the failing gate on the fix diff). Never fix findings yourself
in the controller session. At the cap, adjudicate per SDD's breaker (park with
ruling, or STOP + BLOCKED on load-bearing findings).

### 5. Complete the phase

- **Commit** (the implementer commits inside the task): one commit per phase,
  message `feat({context}): {phase title} (spec: {file})`.
- Append the ledger line: `Task <N>: complete (commits <base7>..<head7>, gates green)`.
- **Pause** and confirm with the user before the next phase — **unless** they
  said "implement all without stopping", in which case run continuously (SDD's
  continuous-execution rule).

## After all phases

1. **Final doc sync**: `/sync-context-docs` once more to catch anything from the
   last phase; commit doc changes.
2. **Code review gate (once, over the whole branch)** — this is SDD's final
   review and the *only* code review in the flow. Package the diff with
   `scripts/review-package <spec-file> $(git merge-base main HEAD) HEAD`
   (never `HEAD~1`), then dispatch `/code-review` on the most capable model over
   the full branch diff, pointed at the ledger's deferred-minor/parked lines.
   Resolve every Critical and High finding — one fix wave max, one scoped
   re-review, then adjudicate residuals. Commit the fixes.
3. Push: `git push -u origin $(git rev-parse --abbrev-ref HEAD)`.
4. Open the single PR (spec + all phases) via `/open-pr`.

## Cleanup — after the PR merges

- Exit the worktree (`ExitWorktree` tool if available, otherwise `cd` to main
  repo root).
- Delete the worktree: `sudo rm -rf .claude/worktrees/<name>` (Docker may have
  created root-owned files).
- `git worktree prune` from the main repo.
- `git branch -d feat-<slug>`.
- `make stop-test` (tear down this worktree's headless test stack).
- The SDD workspace (`.superpowers/sdd/<spec-basename>/`) is git-ignored scratch;
  delete it once the final review is clean — git history is the record.

## When Things Go Wrong

- Gate passes but changed files aren't picked up → `make stop-test && make up-test`, re-run.
- Test or lint fails on the current phase → routes into the fix loop; use
  `/root-cause` + `/fix` if the root cause spans multiple files.
- deptrac violation → replace the direct import with a domain event or a
  bus-dispatched command/query.
- Spec proves wrong mid-implementation → stop, update the spec, re-run
  `/pre-implement-spec`, then resume the loop at the current phase.
- Controller lost its place after compaction → trust the ledger and `git log`
  over recollection; resume at the first phase without a `complete` line.

## Output

End of each phase:

```
✅ Phase {N}: {Title}
   Files:   {count} touched, {count} tests added
   Gates:   {lint/test/build/e2e} PASS
   Ledger:  Task {N}: complete (commits {base7}..{head7})
   Next:    Phase {N+1}: {Title} — proceed?
```

After all phases:

```
✅ All phases complete on branch `feat-<slug>`.
   Final whole-branch code review: clean
   Next step: /open-pr

   Cleanup after merge:
   1. Exit worktree
   2. sudo rm -rf .claude/worktrees/<name>
   3. git worktree prune
   4. git branch -d feat-<slug>
   5. make stop-test
```

If autonomous (the user said "implement all phases without stopping"), proceed
without asking — per SDD's continuous-execution rule.

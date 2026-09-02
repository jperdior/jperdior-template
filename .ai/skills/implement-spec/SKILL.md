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

A spec is delivered in **delivery units**, each one branch and one PR, declared
as a checklist in the spec's `## Delivery` section (see `/archive-spec`). Specs
are split across several units by default, at 1-3 phases each. This run
implements **exactly one** unit — the one whose backticked branch name is the
current branch — and the phases of that unit are its tasks. Phases belonging to
other units are out of scope for this run, however small they look; that
boundary is what keeps this run's context, its gate matrix and its final code
review at a size each can actually handle.

Within the unit, the spec's **phases are the SDD tasks.** SDD dispatches a fresh implementer
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

- The spec exists under `.ai/specs/` — committed on this branch for a first
  delivery unit, already on `main` for a later one.
- The spec's `## Delivery` ledger names this branch as an unticked unit.
- The spec passed `/pre-implement-spec` with verdict = ready. For a later unit,
  that verdict must still hold against today's `main` (see Setup step 6).
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
   them to the user before dispatching phase 1 (SDD's pre-flight scan). This
   batch is an **escalation, not a routine confirmation** — it happens even in
   autonomous mode, and phase 1 waits until the conflicts are resolved.
5. **Resolve this run's delivery unit** from the spec's `## Delivery` ledger:
   ```sh
   awk '/^## Delivery/{f=1;next} /^## /{f=0} f' <spec-file> | grep '^- \[[ xX]\]'
   ```
   The unit whose backticked branch name equals the current branch is this run's
   scope; its phases are the SDD tasks, and phases belonging to other units are
   out of scope — do not implement them, even when they look small. A unit
   already ticked `- [x]` means this branch's work is claimed as landed: stop and
   ask. A ledger that names no unit for this branch, or a spec with no `## Delivery`
   section at all, is a **failed precondition** — stop and ask the user to place
   this branch in the ledger. Both are escalations; autonomous mode does not skip
   them.
6. **If earlier units are already ticked, the spec is describing a `main` that has
   moved.** Before dispatching phase 1, re-verify the spec's **Current State**
   section and any `file.php:123` references it leans on — line numbers rot
   fastest, and a fresh implementer briefed on a stale line number writes against
   code that is no longer there. Anything the spec assumed and `main` has since
   changed goes back through `/pre-implement-spec` for this unit; fold the
   corrections into the spec on this branch. A first unit skips this step.

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
3. **Delivery ledger + archival decision**: run `/archive-spec <spec-file>`. It
   ticks this branch's unit and then decides: with every unit ticked, this branch
   is the spec's **last** delivery PR and the skill archives the spec into
   `.ai/specs/implemented/` in this same PR; with units still owed, the spec
   stays in `.ai/specs/` and only the tick is committed. Nothing archives specs
   on merge — this is the only place it happens, so the decision is made by the
   run that knows how much of the spec it just built.
4. Push: `git push -u origin $(git rev-parse --abbrev-ref HEAD)`.
5. Open the PR for this delivery unit (spec + its phases) via `/open-pr`.

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
   Ledger:  Task {N}: complete (commits {base7}..{head7}, gates green)
   Next:    Phase {N+1}: {Title} — proceed?
```

After all phases:

```
✅ All phases complete on branch `feat-<slug>`.
   Final whole-branch code review: {clean | {count} parked minor findings}
   Delivery: {done}/{total} units ticked
             {spec archived to .ai/specs/implemented/ — last delivery PR
              | spec stays open, still owed: PR {M} — `{branch}`}
   Next step: /open-pr

   Cleanup after merge:
   1. Exit worktree
   2. sudo rm -rf .claude/worktrees/<name>
   3. git worktree prune
   4. git branch -d feat-<slug>
   5. make stop-test
```

Report `clean` only when **no** unresolved findings remain. Anything parked or
deferred by SDD's breaker is listed by count and severity, never folded into
`clean`.

If autonomous (the user said "implement all phases without stopping"), proceed
without asking — per SDD's continuous-execution rule. Autonomous mode skips
**routine confirmations only** (the end-of-phase "proceed?" pause). It does
**not** suppress an escalation: a failed precondition, an unresolved cross-phase
conflict from the pre-flight scan, a BLOCKED implementer report, or a fix loop
that hits its round cap still stops the run and goes to the user.

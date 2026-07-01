---
name: implement-spec
description: Implement an approved spec from .ai/specs/, phase by phase, with the code-review gate enforced between phases. Triggers on "implement spec", "build from spec", "code the spec", "implement phase X".
---

# Implement Spec

Execute an approved spec under `.ai/specs/{date}-{slug}.md`. Implement phase by phase, run the verification gate after each phase, and update the spec's Changelog as you go.

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:subagent-driven-development` — primary orchestration pattern: fresh subagent per implementation task, spec-compliance + code-quality review between tasks.
- `superpowers:test-driven-development` — enforce Red → Green → Refactor within each phase; no production code before a failing test.
- `superpowers:verification-before-completion` — run the full gate via `/run-gates`, read complete output, confirm 0 errors before claiming any phase done.

**Model discipline for subagents:**
- **Explore agents** → `model: "sonnet"` (focused lookup, minimal reasoning overhead)
- **Plan agents** → `model: "opus"` (architectural reasoning, cross-context decisions)
- **Implementation agents** (via `subagent-driven-development`) → `model: "sonnet"`

## Prerequisites

- The spec exists under `.ai/specs/` on the current `feat-<slug>` branch (committed locally).
- The spec passed `/pre-implement-spec` with verdict = ready.
If any precondition fails, stop and inform the user.

## Workflow

All phases are implemented on the same `feat-<slug>` branch (created by `/new-feature`). A single PR covers spec + all phases.

### For each phase:

1. **Read the spec phase**. Identify deliverables, files to touch, tests to add.
2. **Delegate research** to Explore subagents when the phase spans multiple unfamiliar files.
3. **Implement**:
   - PHP: follow the bounded-context layout. Use `/scaffold-bounded-context` for new contexts, `/add-command`, `/add-query`, `/add-route` for additions.
   - Frontend: follow the route shape under `apps/web/src/app/` or `apps/admin/src/app/`. Use `/scaffold-nextjs-page`, `/scaffold-shadcn-form`.
   - Migrations: run `make migrate-diff`; review the SQL; commit it.
   - Tests: PHPUnit Functional next to the controller under `apps/api/tests/Functional/`; Vitest + RTL colocated under `apps/web/src/**/__tests__/` or `apps/admin/src/**/__tests__/`.
4. **Run `/sync-context-docs`** — update AGENTS.md for every context touched, update `docs/persistence.md` if schema changed, update the spec's Changelog, and sync any other cross-cutting docs.
5. **Verification gate (after every phase)**: invoke `/run-gates`. It scopes the gates to the
   diff and dispatches each as a parallel subagent (lint/build gates standalone, `test-api`
   on the shared stack). Every gate MUST report PASS. Fix before continuing.
6. **Code review gate**: invoke `/code-review` on the diff. Resolve every Critical and High finding.
7. **Commit**: `feat({context}): {phase title} (spec: {file})`
8. **Pause** and confirm with the user before starting the next phase (unless they said "implement all without stopping").

### After all phases are done

1. **Final doc sync**: run `/sync-context-docs` once more to catch any changes from the last phase that weren't covered, then commit the doc changes.
2. Push the branch:
   ```sh
   git push -u origin $(git rev-parse --abbrev-ref HEAD)
   ```
2. Open the single PR via `/open-pr`.
3. **Clean up after the PR merges** — once the PR is merged to main:
   - Exit the worktree (`ExitWorktree` tool if available, otherwise `cd` to main repo root).
   - Delete the worktree: `sudo rm -rf .claude/worktrees/<name>` (Docker may have created root-owned files).
   - Run `git worktree prune` from the main repo.
   - Delete the local branch: `git branch -d feat-<slug>`.
   - Run `make stop-test` (tear down this worktree's headless test stack).

## Subagent Strategy

### When to spawn — before writing any code for a phase

If the phase touches ≥3 unfamiliar files or spans multiple bounded contexts, spawn Explore subagents **in parallel** before writing a single line. Do not interleave research and implementation.

**Spawn one Explore agent per angle with `model: "sonnet"`. Each gets a single, focused question.**

| Angle | Example prompt |
|-------|----------------|
| Call sites | "Find every place in `apps/api/src/` that calls `OrderRepository` (not via an interface). List `file:line` only." |
| Controller dependencies | "List every controller that dispatches `CreateOrder` or any Order-related command. File paths and class names only." |
| Test coverage | "List all PHPUnit test files that import or instantiate `OrderCommandHandler`. File paths only." |
| Event subscribers | "Find all classes that listen to the `order.order.created` event ID. Return class name and file path." |
| Migration scope | "List every table referenced in `apps/api/src/Order/Infrastructure/Persistence/` ORM mappings. Table names only." |
| Frontend dependencies | "Find all TypeScript files that import from `@jperdior/api-client-ts` and call an order-related endpoint. File and endpoint." |

Collect all results before opening any file to edit.

### Plan subagent `model: "opus"`

Spawn one Plan subagent when:
- A phase's design needs more thought than the spec captured.
- The phase touches ≥3 bounded contexts.
- A migration's scope is unclear from the ORM mappings alone.

### Rules

- Do NOT spawn subagents for single-file edits — the overhead isn't worth it.
- Do NOT delegate code generation to subagents — all writes happen in the main agent to ensure consistency.
- Run all research agents before starting implementation, not interleaved with it.

## When Things Go Wrong

| Symptom | Action |
|---------|--------|
| `make test` fails on the current phase | If simple: fix the code or test directly. If the root cause is unclear or spans multiple files, use `/root-cause` to drill down, then `/fix` to ship with a regression test. |
| `make lint` reports a deptrac violation | A cross-context import slipped in. Replace with a domain event or public application service. |
| `make migrate-diff` produces unrelated SQL | Investigate snapshot drift — don't commit unrelated churn. |
| Phase delivery doesn't match the spec's promise | Update the spec FIRST; then code to the updated promise. |
| Spec proves wrong mid-implementation | Stop. Update the spec. Re-run `/pre-implement-spec`. Resume. |
| A subtle bug is discovered (passes tests but wrong behaviour) | Use `/root-cause` to find the offending change, then `/fix` to ship a regression test + fix. Review the fix with `/code-review`. |

## Output

End of each phase:

```
✅ Phase {N}: {Title}
   Files:   {count} touched, {count} tests added
   Next:    Phase {N+1}: {Title} — proceed?
```

After all phases:

```
✅ All phases complete on branch `feat-<slug>`.
   Next step: /sync-context-docs → /code-review → /open-pr

   Cleanup after merge:
   1. Exit worktree
   2. sudo rm -rf .claude/worktrees/<name>
   3. git worktree prune
   4. git branch -d feat-<slug>
   5. make stop-test
```

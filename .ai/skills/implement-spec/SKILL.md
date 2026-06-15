---
name: implement-spec
description: Implement an approved spec from .ai/specs/, phase by phase, with the code-review gate enforced between phases. Triggers on "implement spec", "build from spec", "code the spec", "implement phase X".
---

# Implement Spec

Execute an approved spec under `.ai/specs/{date}-{slug}.md`. Implement phase by phase, run the verification gate after each phase, and update the spec's Changelog as you go.

## Prerequisites

- The spec exists under `.ai/specs/` on the current `feat/<slug>` branch (committed locally).
- The spec passed `/pre-implement-spec` with verdict = ready.
- No container startup needed: `make lint` / `make test` / `make build-web` auto-start a headless, per-worktree, port-free test stack that mounts this worktree's code. Multiple worktrees run the gate in parallel without conflict; `make start` is only for browser use.

If any precondition fails, stop and inform the user.

## Workflow

Each phase in the spec becomes its own branch and PR, stacked on the previous. Merge in order.

### For each phase:

1. **Create the branch** from the current HEAD (feature base for Phase 0, previous phase's branch for Phase N+1):
   ```sh
   git checkout -b feat/{slug}-phase-{N}-{short-title}
   ```

2. **Read the spec phase**. Identify deliverables, files to touch, tests to add.

3. **Delegate research** to Explore subagents when the phase spans multiple unfamiliar files.

4. **Implement**:
   - PHP: follow the bounded-context layout. Use `/scaffold-bounded-context` for new contexts, `/add-command`, `/add-query`, `/add-route` for additions.
   - Frontend: follow the route shape under `apps/web/src/app/` or `apps/admin/src/app/`. Use `/scaffold-nextjs-page`, `/scaffold-shadcn-form`.
   - Migrations: run `make migrate-diff`; review the SQL; commit it.
   - Tests: PHPUnit Functional next to the controller under `apps/api/tests/Functional/`; Vitest + RTL colocated under `apps/web/src/**/__tests__/` or `apps/admin/src/**/__tests__/`.

5. **Verification gate**:
   ```sh
   make lint
   make test
   ```
   Every command MUST exit 0. Fix before continuing.

6. **Code review gate**: invoke `/code-review` on the diff. Resolve every Critical and High finding.

7. **Commit**: `feat({context}): {phase title} (spec: {file})`

8. **Push and open the PR**:
   ```sh
   git push -u origin feat/{slug}-phase-{N}-{short-title}
   gh pr create \
     --title "feat({context}): {phase title}" \
     --base {previous-branch-or-main} \
     --body "Phase {N} of spec .ai/specs/{file}."
   ```

9. **Update the spec changelog**: `| {YYYY-MM-DD} | Phase {N} implemented — {PR URL}. |`

10. **Pause** and confirm with the user before starting the next phase (unless they said "implement all without stopping").

### After all phases are done

Report the full stack and merge order. Remind the user to merge in order — GitHub auto-updates each PR's base as the previous one merges.

## Subagent Strategy

### When to spawn — before writing any code for a phase

If the phase touches ≥3 unfamiliar files or spans multiple bounded contexts, spawn Explore subagents **in parallel** before writing a single line. Do not interleave research and implementation.

**Spawn one agent per angle. Each gets a single, focused question.**

| Angle | Example prompt |
|-------|----------------|
| Call sites | "Find every place in `apps/api/src/` that calls `OrderRepository` (not via an interface). List `file:line` only." |
| Controller dependencies | "List every controller that dispatches `CreateOrder` or any Order-related command. File paths and class names only." |
| Test coverage | "List all PHPUnit test files that import or instantiate `OrderCommandHandler`. File paths only." |
| Event subscribers | "Find all classes that listen to the `order.order.created` event ID. Return class name and file path." |
| Migration scope | "List every table referenced in `apps/api/src/Order/Infrastructure/Persistence/` ORM mappings. Table names only." |
| Frontend dependencies | "Find all TypeScript files that import from `@jperdior/api-client-ts` and call an order-related endpoint. File and endpoint." |

Collect all results before opening any file to edit.

### Plan subagent

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
| `make test` fails on the current phase | Fix before pushing. Never push a red branch. |
| `make lint` reports a deptrac violation | A cross-context import slipped in. Replace with a domain event or public application service. |
| `make migrate-diff` produces unrelated SQL | Investigate snapshot drift — don't commit unrelated churn. |
| Phase delivery doesn't match the spec's promise | Update the spec FIRST; then code to the updated promise. |
| Spec proves wrong mid-implementation | Stop. Update the spec. Re-run `/pre-implement-spec`. Resume. |

## Output

End of each phase:

```
✅ Phase {N}: {Title}   →  {PR URL}
   Branch:  feat/{slug}-phase-{N}-{title}  →  {base branch}
   Files:   {count} touched, {count} tests added

   Merge order so far:
   1. {PR URL}  (feat/{slug}-phase-0-…)
   2. {PR URL}  (feat/{slug}-phase-1-…)
   …

   Next: Phase {N+1}: {Title} — proceed?
```

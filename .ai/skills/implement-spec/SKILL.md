---
name: implement-spec
description: Implement an approved spec from .ai/specs/, phase by phase, with the code-review gate enforced between phases. Triggers on "implement spec", "build from spec", "code the spec", "implement phase X".
---

# Implement Spec

Execute an approved spec under `.ai/specs/{date}-{slug}.md`. Implement phase by phase, run the verification gate after each phase, and update the spec's Changelog as you go.

## Prerequisites

- The spec passed `/pre-implement-spec` with verdict = ready.
- `make start` boots cleanly on the current branch.
- A feature branch exists (`feat/<slug>`).

If any precondition fails, stop and inform the user.

## Workflow

1. **Pick the phase**. If the user said "implement spec", start at Phase 0. If they said "implement Phase 2", jump to Phase 2 and verify Phase 0/1 are already merged.
2. **Read the spec phase**. Identify deliverable, files to touch, tests to add.
3. **Delegate research** to subagents when phases span multiple unfamiliar files. Use Explore subagents to map call sites before editing.
4. **Plan the diff** in your head (or in the TaskList). List the files you'll touch.
5. **Implement**:
   - PHP: follow the bounded-context layout. Use `/scaffold-bounded-context` for new contexts, `/add-command`, `/add-query`, `/add-route` for additions.
   - Frontend: follow the route shape under `apps/web/src/app/` or `apps/admin/src/app/`. Use `/scaffold-nextjs-page`, `/scaffold-shadcn-form`.
   - Migrations: run `make migrate-diff` to generate; review the SQL; commit it.
   - Tests: PHPUnit Functional next to the controller; Playwright e2e under `apps/web/e2e/` or `apps/admin/e2e/`.
6. **Verification gate (after every phase)**:
   ```sh
   make lint
   make test
   make test-e2e   # only if UI changed
   ```
   Every command MUST exit 0 before moving on.
7. **Code review gate**: invoke `/code-review` on the diff. Resolve every Critical and High finding before committing.
8. **Commit**: one commit per phase. Message format: `feat({context}): {phase title} (spec: {file})`. Reference the spec file path.
9. **Update the spec changelog**: append `| {YYYY-MM-DD} | Phase N implemented. |`.
10. **Repeat** for the next phase until the spec is complete.

## Subagent Strategy

Use Explore subagents in parallel for:
- Mapping all existing call sites of a class you intend to change.
- Finding every controller that depends on a given service.
- Confirming a migration's scope before generating.

Use one Plan subagent when a phase's design needs more thought than the spec captured.

Do NOT use subagents for trivial single-file edits.

## When Things Go Wrong

| Symptom | Action |
|---------|--------|
| `make test` fails on the current phase | Fix the test or the code. Don't proceed. |
| `make lint` reports a deptrac violation | A cross-context import slipped in. Replace with a domain event or public application service. |
| `make migrate-diff` produces unrelated SQL | Investigate the snapshot drift — don't commit unrelated churn. |
| Phase delivery doesn't match the spec's promise | Update the spec FIRST; then code to the updated promise. |
| Spec proves wrong mid-implementation | Stop. Update the spec. Re-run `/pre-implement-spec`. Resume. |

## Output

End of each phase, report:

```
✅ Phase {N}: {Title}
   Files touched: {count}
   Tests added: {count}
   Spec changelog updated: yes
   Next: Phase {N+1}: {Title} — proceed? (or all phases done)
```

If autonomous (the user said "implement all phases without stopping"), proceed without asking.

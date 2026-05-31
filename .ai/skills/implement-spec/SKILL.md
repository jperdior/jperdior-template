---
name: implement-spec
description: Implement an approved spec from .ai/specs/, phase by phase, with the code-review gate enforced between phases. Triggers on "implement spec", "build from spec", "code the spec", "implement phase X".
---

# Implement Spec

Execute an approved spec under `.ai/specs/{date}-{slug}.md`. Implement phase by phase, run the verification gate after each phase, and update the spec's Changelog as you go.

## Prerequisites

- The spec passed `/pre-implement-spec` with verdict = ready.
- The spec has a **PR Plan** table (see spec template). If it is missing, add one before proceeding.
- `make start` boots cleanly on the current branch (the feature base branch from `/new-feature`).

If any precondition fails, stop and inform the user.

## Workflow

The unit of work is a **PR**, not a phase. Each PR is a branch stacked on the previous one.
Phases within the same PR are implemented sequentially on that branch before it is pushed.

### For each PR in the spec's PR Plan:

1. **Create the branch** from the current HEAD (feature base for PR 1, previous PR's branch for PR 2+):
   ```sh
   git checkout -b <branch>   # e.g. feat/billing-domain
   ```

2. **Read every phase** assigned to this PR. Identify all deliverables, files to touch, tests to add.

3. **Delegate research** to Explore subagents when phases span multiple unfamiliar files.

4. **Implement all phases for this PR**:
   - PHP: follow the bounded-context layout. Use `/scaffold-bounded-context` for new contexts, `/add-command`, `/add-query`, `/add-route` for additions.
   - Frontend: follow the route shape under `apps/web/src/app/` or `apps/admin/src/app/`. Use `/scaffold-nextjs-page`, `/scaffold-shadcn-form`.
   - Migrations: run `make migrate-diff`; review the SQL; commit it.
   - Tests: PHPUnit Functional next to the controller; Playwright e2e under `apps/web/e2e/` or `apps/admin/e2e/`.

5. **Verification gate**:
   ```sh
   make lint
   make test
   make test-e2e   # only if UI changed
   ```
   Every command MUST exit 0. Fix before continuing.

6. **Code review gate**: invoke `/code-review` on the diff. Resolve every Critical and High finding.

7. **Commit** — one commit per phase within the PR. Format: `feat({context}): {phase title} (spec: {file})`.

8. **Push and open the PR**:
   ```sh
   git push -u origin <branch>
   gh pr create \
     --title "feat({context}): {PR title}" \
     --base <previous-branch-or-main> \
     --body "Part of spec: .ai/specs/{file}. Implements phases {N}–{M}."
   ```

9. **Update the spec changelog**: `| {YYYY-MM-DD} | PR {N} opened — phases {X}–{Y}. |`

10. **Pause** and confirm with the user before starting the next PR (unless they said "implement all without stopping").

### After all PRs are open

Report the full stack and merge order. Remind the user to merge in order — GitHub will auto-update each PR's base as the previous one merges.

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
| `make test` fails on the current PR | Fix before pushing. Never push a red branch. |
| `make lint` reports a deptrac violation | A cross-context import slipped in. Replace with a domain event or public application service. |
| `make migrate-diff` produces unrelated SQL | Investigate snapshot drift — don't commit unrelated churn. |
| Phase delivery doesn't match the spec's promise | Update the spec FIRST; then code to the updated promise. |
| Spec proves wrong mid-implementation | Stop. Update the spec. Re-run `/pre-implement-spec`. Resume. |
| PR Plan is missing from the spec | Add it before starting. Default grouping: Domain → Persistence → Application → Presentation+Tests. |

## Output

End of each PR:

```
✅ PR {N}: {Title}   →  {PR URL}
   Branch:  {branch}  →  {base branch}
   Phases:  {X}–{Y}
   Files:   {count} touched, {count} tests added

   Merge order so far:
   1. {PR 1 URL}  ({branch})
   2. {PR 2 URL}  ({branch})
   …

   Next: PR {N+1}: {Title} — proceed?
```

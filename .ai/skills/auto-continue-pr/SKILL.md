---
name: auto-continue-pr
description: Resume an in-progress PR previously started by auto-create-pr. Claims the PR, checks it out into an isolated worktree, reads HANDOFF.md, continues from the first unchecked step. Usage - /auto-continue-pr <PR-number>
---

# Auto Continue PR

Resume an `auto-create-pr` run that stopped before completion.

## Workflow

1. **Look up the PR**: `gh pr view <number> --json title,headRefName,labels,body,assignees`.
2. **Verify it's resumable**: the PR must have `in-progress` label (or be from a prior `auto-create-pr` run with a `.ai/runs/{slug}/HANDOFF.md` referenced in the body).
3. **Check claim discipline**: if another assignee holds the lock and was active in the last 30 minutes, abort with "PR is actively being worked on by {assignee}".
4. **Claim it**: assign self, ensure `in-progress` is set, post a resume comment ("auto-continue-pr resumed at step N").
5. **Check out the branch into a worktree**:
   ```sh
   git fetch origin {headRefName}
   git worktree add ../jperdior-template.{slug} origin/{headRefName}
   cd ../jperdior-template.{slug}
   ```
6. **Read** `.ai/runs/{date}-{slug}/PLAN.md` + `HANDOFF.md`. Find the first unchecked Progress item.
7. **Continue from that step**. Apply the same verification gate after every step.
8. **Push** new commits.
9. **Update PR** description if the plan changed.
10. **Apply labels** (move to `review` / `changes-requested` / `qa` based on outcome).
11. **Remove `in-progress`** label when done.

## Failure Handling

If the gate fails twice and the fix isn't obvious:
- Update `HANDOFF.md` with the failing step + error output.
- Post a PR comment: "auto-continue-pr handed off at step N — manual attention needed".
- Remove `in-progress` label.

## Output

```
✅ Resumed PR #{N}
   Branch: {headRefName}
   Step: continued from {M}/{total}
   Steps completed in this run: {K}
   Status: ready / handed-off / blocked
```

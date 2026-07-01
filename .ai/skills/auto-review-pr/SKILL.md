---
name: auto-review-pr
description: Review a GitHub PR by number in an isolated worktree using the code-review skill. Submits approve/request-changes and manages labels. Usage - /auto-review-pr <PR-number>
---

# Auto Review PR

Review a PR end-to-end: check it out in a worktree, run the verification gate, run the `code-review` skill, submit a review with the right verdict, transition labels.

## Workflow

1. **Look up the PR**: `gh pr view <number> --json title,headRefName,baseRefName,labels,author`.
2. **Claim it** (review side): post a "auto-review-pr started" comment. Don't add `in-progress` (the author may still hold it); use a distinct review-in-progress comment marker.
3. **Worktree checkout**:
   ```sh
   git fetch origin {headRefName}
   git worktree add .claude/worktrees/review-{N} origin/{headRefName}
   cd .claude/worktrees/review-{N}
   ```
4. **Verification gate**: invoke `/run-gates`. It scopes gates to the diff and dispatches
   each as a parallel subagent (lint/build gates standalone, `test-api` on the shared stack).
5. **Run `/code-review`** on the diff (`git diff origin/{baseRefName}...HEAD`).
6. **Compose review body** using the Code Review output format.
7. **Decide verdict**:
   - **APPROVE** if Critical = 0, High = 0, gate = PASS.
   - **REQUEST_CHANGES** otherwise.
8. **Submit**:
   ```sh
   gh pr review <number> --approve --body "$(cat review.md)"
   # or
   gh pr review <number> --request-changes --body "$(cat review.md)"
   ```
9. **Transition labels**:
   - On approve + `needs-qa`: move to `qa`.
   - On approve + `skip-qa`: move to `merge-queue`.
   - On request-changes: move to `changes-requested`.
10. **Cleanup worktree** (only if not handing off): `git worktree remove .claude/worktrees/review-{N}`.

## Label Transition Rules

| Before | Action | After |
|--------|--------|-------|
| `review` | approve + `skip-qa` | `merge-queue` |
| `review` | approve + `needs-qa` | `qa` |
| `review` | request-changes | `changes-requested` |
| `changes-requested` | re-review approve + `needs-qa` | `qa` |
| `changes-requested` | re-review approve + `skip-qa` | `merge-queue` |

## Output

```
✅ Reviewed PR #{N}
   Verdict: APPROVE / REQUEST_CHANGES
   Gate: {pass-summary}
   Critical: {count}
   High: {count}
   Label moved: {before} → {after}
```

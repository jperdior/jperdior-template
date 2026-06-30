---
name: sync-merged-pr-issues
description: Reconcile merged (and closed-without-merge) PRs with GitHub issues — auto-close issues fixed by merged PRs, comment on issues whose PR was closed without merging. Use for post-merge housekeeping.
---

# Sync Merged PR Issues

After a merge window, close issues that were authoritatively fixed by a merged PR, and post informational comments on issues whose PR was closed without merging.

## Workflow

1. **Compute the window** (default: last 7 days). Allow `since: YYYY-MM-DD` override from the user.
2. **List merged PRs** in the window:
   ```sh
   gh pr list --state merged --search "merged:>={since}" --json number,title,body,closingIssuesReferences,mergedAt,headRefName
   ```
3. **For each merged PR**:
   - Extract linked issues from `closingIssuesReferences` (set by `fixes #N`, `closes #N`, `resolves #N` keywords in the PR body).
   - For each linked issue, if still open, close it with a comment: "Closed by PR #{prNum} (merged {date})".
4. **List closed-without-merge PRs** in the window:
   ```sh
   gh pr list --state closed --search "closed:>={since}" --json number,title,body,mergedAt,closingIssuesReferences
   ```
   Filter to those with `mergedAt: null`.
5. **For each closed-without-merge PR**:
   - For each linked issue, post a comment: "PR #{prNum} was closed without merging — this issue is still open. Reason: {…}"
   - Do NOT close the issue.

## Claim Discipline

- Add `in-progress` label on the issue you're about to modify (so other auto-skills know).
- Remove `in-progress` after the comment/close is posted.

## Output

```
Sync Merged PR Issues — {since} → today

Issues closed: {N}
  - #45 (by PR #142) — feat(note): add list endpoint
  - #61 (by PR #156) — fix(user): refresh-token rotation

Issues notified (PR closed without merge): {M}
  - #88 (PR #150 closed) — comment posted

Issues skipped (already closed / no PR link): {K}
```

## Rules

- Never close an issue that isn't authoritatively referenced (`closingIssuesReferences` must include it).
- Never close an issue without posting the closing comment first.
- Respect claim locks — if another auto-skill holds `in-progress`, skip that issue this run.

---
name: merge-buddy
description: Scan open PRs and classify merge readiness from labels, reviews, CI status, and mergeability. Reports which PRs can merge now and which are close but blocked. Triggers on "what can I merge", "merge status", "merge buddy".
---

# Merge Buddy

Survey open PRs and answer: **which are ready to merge right now, and which are close**.

## Workflow

1. List open PRs:
   ```sh
   gh pr list --state open --json number,title,labels,reviewDecision,mergeable,mergeStateStatus,statusCheckRollup,author,headRefName,isDraft
   ```
2. For each PR, classify:
   - **Ready to merge**: not draft, `merge-queue` label, `reviewDecision == APPROVED`, `mergeable == MERGEABLE`, all checks `SUCCESS`.
   - **Close**: missing one signal (e.g. approved + mergeable but checks still running, or in `qa` label awaiting QA).
   - **Blocked**: `changes-requested` / `blocked` / `do-not-merge` / `qa-failed` labels, or `reviewDecision == CHANGES_REQUESTED`, or `mergeable == CONFLICTING`.
3. Output a single table.

## Output Format

```markdown
## Merge Buddy report — {date}

### Ready to merge now ({N})

| # | Title | Author | Label | Notes |
|---|-------|--------|-------|-------|
| 142 | feat(note): add list endpoint | alice | merge-queue | green |

### Close ({M})

| # | Title | Author | Label | What's missing |
|---|-------|--------|-------|----------------|
| 145 | feat(user): add 2FA | bob | qa | awaiting QA pass |

### Blocked ({K})

| # | Title | Author | Label | Reason |
|---|-------|--------|-------|--------|
| 130 | refactor(api): … | carol | changes-requested | review found 2 Critical issues |
| 131 | chore(deps): bump foo | dave | merge-queue | CI failing (test-e2e) |
```

## Rules

- Never `gh pr merge` from this skill. It only reports.
- Always reflect the actual labels, not inferred state.
- If a PR is `merge-queue` but checks are failing, it goes in **Blocked** with the reason.
- Drafts are excluded from all categories.

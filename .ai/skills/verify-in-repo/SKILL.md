---
name: verify-in-repo
description: Confirm a change is actually present in the working tree before claiming "done". Paranoia gate against false completion claims. Triggers on "verify in repo", "did I actually do X", "is the change in the working tree".
---

# Verify In Repo

Independent check that a specific change is present in the working tree (and optionally committed). Used as a sanity gate before reporting work as complete.

## Workflow

1. **Take the claim** as input: "I added X to file Y", "I created bounded context Z", "I added route R".
2. **Verify file existence**: `ls <path>` or `find . -name '<filename>'`.
3. **Verify content presence**: `grep -n '<symbol>' <path>` for each claimed addition.
4. **Verify commit state** (if a commit was claimed):
   ```sh
   git log --oneline -10
   git log --diff-filter=A --name-only <commit-sha>
   git show <commit-sha> -- <path>
   ```
5. **Verify push state** (if a push was claimed):
   ```sh
   git log origin/$(git rev-parse --abbrev-ref HEAD)..HEAD   # should be empty if pushed
   ```
6. **Output a verdict**: PRESENT / PARTIALLY PRESENT / MISSING / NOT COMMITTED / NOT PUSHED.

## Output Format

```markdown
# Verify In Repo: {claim}

| Check | Result |
|-------|--------|
| File `{path/to/claimed/file}` exists | YES / NO |
| `{class or symbol}` defined in that file | YES / NO |
| {any other structural check relevant to the claim} | YES / NO |
| Committed in this branch | YES (sha {x}) / NO |
| Pushed to origin | YES / NO |

## Verdict
PRESENT — the claim is fully reflected in the working tree.
```

## When to Use

- Before reporting "Phase N done" in `/implement-spec`.
- Before opening a PR in `/auto-create-pr`.
- When an earlier agent claimed work that the current agent can't see.

## Never

- Never invent verification evidence. If a check is unclear, mark UNKNOWN and explain.
- Never modify files in this skill. Read-only.

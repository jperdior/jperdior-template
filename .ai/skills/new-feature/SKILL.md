---
name: new-feature
description: Create an isolated git worktree on a new branch from main and enter it, ready for feature work. Triggers on "new feature", "let's do a new feature", "start a feature", "let's plan a feature", "new branch for", "open a worktree".
---

# New Feature

Spin up an isolated worktree from `main` so the feature has its own branch and working tree without touching the main checkout.

> **This skill is called once per feature.** The same `feat/<slug>` worktree is used for both spec writing and implementation — no separate spec branch or spec-only PR is needed.

## Workflow

1. **Get the feature name** from the user's message or ask if not clear enough to derive a branch name.
2. **Derive the branch name**: `feat/<kebab-case-description>` (max ~40 chars, no special chars except `-`).
3. **Confirm** the branch name with the user if it was derived (not explicitly given).
4. **Create the worktree** using `EnterWorktree` with the derived name. This creates `.claude/worktrees/<name>` on a new branch and enters it.
5. **Report** the branch name, worktree path, and the correct next steps.

## Branch naming

| User says | Branch name |
|-----------|-------------|
| "campaign bounded context" | `feat/campaign-bounded-context` |
| "billing feature" | `feat/billing` |
| "fix the login redirect" | `feat/fix-login-redirect` |

Always use lowercase kebab-case. Strip articles (a, an, the). Truncate at 40 chars.

## Rules

- Always branch from `main`.
- Never create the worktree without confirming the branch name first if it was ambiguous.
- After entering the worktree, remind the user that `ExitWorktree` (or ending the session) will prompt to keep or discard the branch.
- Next step is always `/spec-writing` (writes the spec locally), then `/pre-implement-spec` (audit), then `/implement-spec`. Do NOT skip the audit.

## Output

```
Worktree ready on branch `feat/<name>`.
Path: .claude/worktrees/<name>

Next steps:
1. /spec-writing                              ← draft spec locally on this branch
2. /pre-implement-spec .ai/specs/{file}.md   ← audit the spec for gaps
3. /implement-spec .ai/specs/{file}.md        ← implement phase by phase
4. /open-pr                                   ← single PR (spec + code) to main
```

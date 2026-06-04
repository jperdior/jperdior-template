---
name: new-feature
description: Create an isolated git worktree on a new branch from main and enter it, ready for feature work. Triggers on "new feature", "let's do a new feature", "start a feature", "let's plan a feature", "new branch for", "open a worktree".
---

# New Feature

Spin up an isolated worktree from `main` so the feature has its own branch and working tree without touching the main checkout.

> **This skill is called twice per spec-driven feature:**
> 1. **Before `/spec-writing`** — with a `spec/<slug>` prefix, to write the design doc and open the spec-only PR.
> 2. **Before `/implement-spec`** — with a `feat/<slug>` prefix, to implement the approved spec.

## Workflow

1. **Determine the phase** — are we opening a branch for spec-writing or for implementation?
   - Spec phase (user is about to design): prefix = `spec/`
   - Implementation phase (spec is merged): prefix = `feat/`
   - If the user didn't specify, ask. The prefix matters for the next steps.
2. **Get the feature name** from the user's message or ask if not clear enough to derive a branch name.
3. **Derive the branch name**: `{prefix}/<kebab-case-description>` (max ~40 chars, no special chars except `-`).
4. **Confirm** the branch name with the user if it was derived (not explicitly given).
5. **Create the worktree** using `EnterWorktree` with the derived name. This creates `.claude/worktrees/<name>` on a new branch and enters it.
6. **Restart containers from the worktree**: run `make stop && make start` from the worktree directory. Docker containers mount the directory they were started from — skipping this means all `make lint / test / build` targets run against the previous worktree's code, not yours.
7. **Report** the branch name, worktree path, and the correct next step for the phase.

## Branch naming

| Phase | User says | Branch name |
|-------|-----------|-------------|
| Spec | "campaign bounded context" | `spec/campaign-bounded-context` |
| Spec | "billing feature" | `spec/billing` |
| Impl | "campaign bounded context" | `feat/campaign-bounded-context` |
| Impl | "fix the login redirect" | `feat/fix-login-redirect` |

Always use lowercase kebab-case. Strip articles (a, an, the). Truncate at 40 chars.

## Rules

- Always branch from `main`.
- Never create the worktree without confirming the branch name first if it was ambiguous.
- After entering the worktree, remind the user that `ExitWorktree` (or ending the session) will prompt to keep or discard the branch.
- `spec/` branches: next step is `/spec-writing`. Do NOT start coding.
- `feat/` branches: next step is `/pre-implement-spec` (audit) then `/implement-spec`. Do NOT skip the audit.

## Output

**Spec phase:**
```
Worktree ready on branch `spec/<name>`.
Path: .claude/worktrees/<name>

Next step: /spec-writing
```

**Implementation phase:**
```
Worktree ready on branch `feat/<name>`.
Path: .claude/worktrees/<name>

Next steps:
1. /pre-implement-spec .ai/specs/{file}.md   ← audit the merged spec
2. /implement-spec .ai/specs/{file}.md        ← implement phase by phase
```

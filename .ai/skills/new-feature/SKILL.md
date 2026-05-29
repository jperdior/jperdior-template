---
name: new-feature
description: Create an isolated git worktree on a new branch from main and enter it, ready for feature work. Triggers on "new feature", "let's do a new feature", "start a feature", "let's plan a feature", "new branch for", "open a worktree".
---

# New Feature

Spin up an isolated worktree from `main` so the feature has its own branch and working tree without touching the main checkout.

## Workflow

1. **Get the feature name** from the user's message or ask if not clear enough to derive a branch name.
2. **Derive the branch name**: `feat/<kebab-case-description>` (max ~40 chars, no special chars except `-`).
3. **Confirm** the branch name with the user if it was derived (not explicitly given).
4. **Create the worktree** using `EnterWorktree` with the derived name. This creates `.claude/worktrees/<name>` on a new branch and enters it.
5. **Report** the branch name, worktree path, and what to do next (e.g. "Ready — you're now on `feat/traefik-local-dev`. Start coding or run `/spec-writing` to write a spec first.").

## Branch naming

| User says | Branch name |
|-----------|-------------|
| "Traefik local dev" | `feat/traefik-local-dev` |
| "add billing context" | `feat/add-billing-context` |
| "fix the login redirect" | `feat/fix-login-redirect` |
| "user profile page" | `feat/user-profile-page` |

Always use lowercase kebab-case. Strip articles (a, an, the). Truncate at 40 chars.

## Rules

- Always branch from `main` (the worktree base is HEAD of the current branch, which should be main).
- Never create the worktree without confirming the branch name first if it was ambiguous.
- After entering the worktree, remind the user that `ExitWorktree` (or ending the session) will prompt to keep or discard the branch.
- If the user wants a spec before coding, suggest `/spec-writing` as the next step.
- If the user wants to dive straight in, suggest `/scaffold-bounded-context` or `/add-command` depending on the feature type.

## Output

```
Worktree ready on branch `feat/<name>`.
Path: .claude/worktrees/<name>

Next steps:
- Write a spec first?  →  /spec-writing
- Scaffold a context?  →  /scaffold-bounded-context
- Add a command?       →  /add-command
```

---
name: new-feature
description: Create an isolated git worktree on a new branch from main and enter it, ready for feature work. Triggers on "new feature", "let's do a new feature", "start a feature", "let's plan a feature", "new branch for", "open a worktree".
---

# New Feature

Spin up an isolated worktree from `main` so the feature has its own branch and working tree without touching the main checkout.

> **This skill is called once per feature.** The same `feat-<slug>` worktree is used for both spec writing and implementation — no separate spec branch or spec-only PR is needed.

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:using-git-worktrees` — confirms existing worktree isolation before creating a new one; ensures `EnterWorktree` is preferred over raw `git worktree add`.

> If the feature scope is unclear, invoke `superpowers:brainstorming` before creating the worktree to design the approach before branching.

## Workflow

1. **Get the feature name** from the user's message or ask if not clear enough to derive a branch name.
2. **Derive the branch name**: `feat-<kebab-case-description>` (max ~40 chars, lowercase alphanumeric + hyphens only — no `/` or `+`, which break Docker Compose project names derived from `$(notdir $(PWD))`). **Do not ask for confirmation** — just pick the name and go.
3. **Refresh `main` before branching**: run `git fetch origin` so the worktree's base ref is current. `EnterWorktree`'s default `fresh` base ref branches from `origin/<default-branch>` **as of the last fetch** — without this step the new branch can silently start from a stale `main`. Confirm `git rev-list --count main..origin/main` is `0` (local `main` is up to date); if not, the fetch has already updated `origin/main`, which is what the worktree branches from.
4. **Create the worktree** using `EnterWorktree` with the derived name. This creates `.claude/worktrees/<name>` on a new branch and enters it.
5. **No container startup needed**: `make lint` / `make build-web` run standalone in ephemeral containers (no postgres/api). Only the PHP test gate inside `make test` auto-starts a headless, per-worktree, port-free test stack on first use — no `make start` needed, and multiple worktrees run gates in parallel without conflict. (`make start` is only for browser use.)
6. **Report** the branch name, worktree path, and the correct next steps.

## Branch naming

`feat-<kebab-case>` — max 40 chars, lowercase, hyphens only (no `/` — it breaks Docker Compose project names). Strip articles. Derive and go; never ask for confirmation.

## Output

```
Worktree ready on branch `feat-<name>`.
Path: .claude/worktrees/feat-<name>

Next steps:
1. /spec-writing                              ← draft spec locally on this branch
2. /pre-implement-spec .ai/specs/{file}.md   ← audit the spec for gaps
3. /implement-spec .ai/specs/{file}.md        ← implement phase by phase
4. /open-pr                                   ← single PR (spec + code) to main
```

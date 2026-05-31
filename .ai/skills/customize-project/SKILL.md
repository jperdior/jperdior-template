---
name: customize-project
description: Personalizes the template for a new project — renames placeholders in package files, Makefile, and README, and adds project context to AGENTS.md. Triggers on "customize my project", "rename the template", "set up my project", "personalize this template", "change the project name".
---

# Customize Project

One-time interactive setup that replaces all `jperdior-template` placeholders with your project's identity and records what you're building in `AGENTS.md` so every future AI session starts with context.

## Workflow

Ask the following questions **one at a time** (do not ask all at once):

1. **Project short name** — kebab-case, e.g. `my-blog`. Used in `Makefile`, root `package.json`, and the README title.
2. **Author or org handle** — kebab-case, e.g. `johndoe`. Replaces `jperdior` in all npm package scopes (`@jperdior/`) and Composer vendor names (`jperdior/`).
3. **One-sentence description** — appears in the README intro and `AGENTS.md`.
4. **What are you building?** — 2–3 sentences about the domain, users, and main goal. Goes into `AGENTS.md` so AI agents understand the project without reading the code.

After collecting the four answers, **show a summary** of every file that will change and what will be replaced. Ask for confirmation before applying.

## Changes applied on confirmation

| File | What changes |
|------|-------------|
| `README.md` | Title (`# jperdior-template` → `# <name>`) + intro paragraph |
| `Makefile` | `PROJECT_NAME := jperdior` → `PROJECT_NAME := <name>` |
| `package.json` (root) | `"name": "jperdior-template"` → `"name": "<name>"` |
| `packages/api-client-ts/package.json` | `"name": "@jperdior/api-client-ts"` → `"@<handle>/api-client-ts"` |
| `packages/ui-react/package.json` | `"name": "@jperdior/ui-react"` → `"@<handle>/ui-react"` |
| `packages/shared-kernel-php/composer.json` | `"name": "jperdior/shared-kernel-php"` → `"<handle>/shared-kernel-php"` |
| `apps/api/composer.json` | `"name": "jperdior/api"` + `"jperdior/shared-kernel-php"` dependency ref |
| `apps/web/package.json` | `"name": "@jperdior/web"` + workspace refs `@jperdior/api-client-ts`, `@jperdior/ui-react` |
| `apps/admin/package.json` | Same as web |
| `AGENTS.md` | Prepend a `## Project Context` section |

After applying all edits, **prepend** this block to `AGENTS.md` (after the `# Agents Guidelines` heading):

```markdown
## Project Context

**Name:** <name>
**Description:** <one-sentence description>

<what-are-you-building paragraph>

---
```

Finally, **commit** all changes:
```sh
git add -A
git commit -m "chore: customize project for <name>"
```

## Output

```
Done. Renamed jperdior-template → <name> across all package files.

Added project context to AGENTS.md — every AI session will now know what you're building.

Committed as: chore: customize project for <name>

Next steps:
  make start                      — start the stack if not already running
  /new-feature                    — create a worktree + branch for your first feature
  /spec-writing                   — design the first feature spec-first (recommended)
```

## Rules

- Ask questions one at a time — do not dump all four at once.
- Always show the summary and ask for confirmation before applying any edits.
- Never change the PHP namespace (`App\`) — it is Symfony's standard and renaming it would touch every PHP source file. The `composer.json` name field is sufficient.
- Never overwrite the user's answers — if they want to re-run, they can edit the files directly.
- If any file is missing (e.g., `packages/shared-kernel-php/composer.json`), skip it silently and note it in the summary.
- Commit as a single clean commit — don't split across multiple commits.

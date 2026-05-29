---
name: create-agents-md
description: Create or rewrite an AGENTS.md for a package, app, or bounded context. Ensures prescriptive tone, MUST rules, validation commands, and consistent structure. Triggers on "create AGENTS.md", "rewrite AGENTS.md", "add AGENTS guidelines".
---

# Create AGENTS.md

Scaffold a new `AGENTS.md` for a package, app, or bounded context. The output follows the template structure used throughout this monorepo (Always / Ask First / Never / Validation Commands / detailed sections).

## Workflow

1. **Identify the target**. Is this an app (`apps/<name>`), a package (`packages/<name>`), a bounded context (`apps/api/code/src/<Context>`), or something else?
2. **Read the surroundings**: read the closest parent `AGENTS.md` (root, app-level) to understand inherited rules. Don't repeat them — link to them.
3. **Catalogue what's there**: list the actual files / responsibilities of this area. Don't invent rules for code that doesn't exist.
4. **Generate the file** with the section template below.

## Section Template

```markdown
# {Area} — Agents Guidelines

{One-paragraph what this area is responsible for and what it is NOT responsible for.}

## Always

- {Concrete rule}
- {Concrete rule}

## Ask First

- {Decision that needs maintainer input}

## Never

- {Anti-pattern with reason}

## Validation Commands

```bash
{the smallest set of commands that proves this area works}
```

## Structure

{Directory tree to 2 levels.}

## Conventions

### Naming
- ...

### Imports
- ...

### Tests
- ...

## Common Tasks

| Task | Where to look |
|------|---------------|
| ... | ... |

## Cross-references

- Root: `/AGENTS.md`
- Closest parent: `{path}`
- Related skills: `{list}`
```

## Rules

- **Prescriptive tone**: "Always do X", "Never do Y" — not "you might consider".
- **No invented rules**: every rule must reflect actual code or actual past mistakes (check `.ai/lessons.md`).
- **Link, don't duplicate**: if a rule lives in the root `AGENTS.md`, link to it instead of restating.
- **Validation commands are the smallest set** that proves the area works. Not "run everything".
- **One AGENTS.md per scope**: don't create AGENTS.md inside a context that already has one one level up unless the local rules genuinely differ.

## Never

- Never write AGENTS.md for an area where rules would just say "follow root AGENTS.md" — skip it.
- Never put implementation details that belong in code comments into AGENTS.md.
- Never reference files that don't exist yet.

## After Creating

- Add a CLAUDE.md sibling: `@AGENTS.md` (one-liner).
- Update the root `AGENTS.md` Task Router if the new AGENTS.md introduces a new task category.

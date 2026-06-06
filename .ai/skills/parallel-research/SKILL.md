---
name: parallel-research
description: Spawn multiple Explore subagents in parallel to map an unfamiliar area of the codebase from different angles before implementing. Triggers on "research the codebase", "map this area", "parallel research", "explore before implementing".
---

# Parallel Research

Launch multiple Explore subagents simultaneously to build a complete picture of an area before modifying it. Use this when a task touches ≥3 files in unfamiliar territory, spans multiple bounded contexts, or changes a class with many call sites you haven't seen.

The goal is a **Research Map** — a consolidated set of `file:line` references — that lets you implement with confidence rather than discovering surprises mid-edit.

## When to use

- Before implementing a feature in a bounded context you haven't touched this session.
- Before refactoring a shared class or interface with unknown call sites.
- Before adding a migration that could affect tables used by other contexts.
- When the spec names files or services you haven't read yet.
- Before any change to auth, the event bus, or shared-kernel packages.

## Workflow

1. **Define the angles.** Based on what you're about to implement, identify 2–5 distinct research dimensions. Each angle must answer exactly one question. Examples:
   - "All direct usages of `UserRepository` (not via interface)"
   - "All controllers that dispatch commands in the `Note` context"
   - "All PHPUnit tests that cover `NoteCommandHandler`"
   - "All event subscribers listening to `user.account.created`"
   - "All TypeScript files calling a `/notes` endpoint via the API client"

2. **Spawn Explore subagents in parallel** — one per angle. Each agent prompt should open with a role statement so it reasons from the right perspective (e.g. "You are an expert in this PHP/Symfony codebase. Your job is to find all call sites of X. Return file:line only."). Send all spawns in a single message; do not wait for one to return before launching the next.

3. **Collect results.** Each agent returns `file:line` references. Build the consolidated Research Map.

4. **Flag surprises** — dependencies or call sites you didn't expect. Report these to the user before coding if they could change the implementation plan.

5. **Proceed with implementation**, informed by the full map.

## How to write agent prompts

Each prompt must be:
- **One focused question** — not a multi-step investigation.
- **Specific about what to search** — class name, method, event ID, table name, import path.
- **Minimal output format** — `file:line` list only; no analysis or prose.

**Good:**
> Find every file in `apps/api/src/` that directly instantiates or injects `DoctrineNoteRepository` (not via its interface `NoteRepository`). Return `file:line` only.

**Bad:**
> Look at the Note context and tell me everything that uses repositories.

## Example research map

```markdown
## Research Map: Note aggregate refactor

### Angle 1 — NoteRepository call sites (non-interface)
- `apps/api/src/Note/Infrastructure/Persistence/DoctrineNoteRepository.php:1` — definition
- `apps/api/tests/Note/Infrastructure/DoctrineNoteRepositoryTest.php:12` — only test using concrete class

### Angle 2 — Controllers dispatching Note commands
- `apps/api/src/Note/Presentation/HTTP/CreateNoteController.php:34` — dispatches CreateNote
- `apps/api/src/Note/Presentation/HTTP/DeleteNoteController.php:28` — dispatches DeleteNote

### Angle 3 — PHPUnit tests covering NoteCommandHandler
- `apps/api/tests/Note/Application/CreateNoteHandlerTest.php:1`
- `apps/api/tests/Note/Application/DeleteNoteHandlerTest.php:1`

### Surprises
- `apps/api/src/User/Application/CleanupUserNotesHandler.php:18` — imports `Note\Domain\NoteRepository` directly. **Critical: cross-context import.** Raise with user before proceeding.

### Ready to implement?
- [x] All call sites identified
- [x] All tests identified
- [ ] Cross-context import at `CleanupUserNotesHandler:18` — resolve first
```

## Rules

- Spawn all agents at once — sequential spawning defeats the purpose.
- Each agent must have exactly one question. Multi-question agents drift and miss things.
- Do NOT use agents for design or code generation — they read; you write.
- If an agent returns nothing: verify the search pattern is correct, then conclude the dependency doesn't exist. Do not assume the agent missed it without re-checking.
- If a surprise would change the implementation plan, pause and surface it to the user before writing any code.

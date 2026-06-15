---
name: sync-context-docs
description: Update or create AGENTS.md for every bounded context touched by the current branch. Reads from code — never from memory. Run before opening a PR. Triggers on "sync docs", "update context docs", "sync context docs", "update agents".
---

# Sync Context Docs

After implementing a feature, update (or create) the `AGENTS.md` for every bounded context the branch touched. This keeps business rules, invariants, and API surface documented in the right place so future agents don't have to re-read all the code.

**Run this before `/open-pr` or `/check-and-commit`.** Every doc change on the branch must be committed before opening the PR.

---

## Workflow

### Step 1 — Identify everything that changed

```bash
# 1. Which bounded contexts were touched?
git diff origin/main...HEAD --name-only \
  | grep '^apps/api/src/' \
  | sed 's|apps/api/src/||' \
  | cut -d/ -f1 \
  | sort -u
```
Collect the unique context names (e.g. `User`). Skip `Shared`.

```bash
# 2. Were persistence / schema files touched (migrations, *Model, repositories)?
git diff origin/main...HEAD --name-only \
  | grep -E 'migrations/|Infrastructure/Persistence/Doctrine/' \
  | head -5
```

```bash
# 3. Were workflow / docs files touched?
git diff origin/main...HEAD --name-only \
  | grep -E '^docs/|\.ai/skills/|AGENTS\.md$'
```

### Step 2 — For each changed bounded context

1. **Check if `apps/api/src/<Context>/AGENTS.md` exists.**
   - If yes: read it (to understand what's already documented) and plan an update.
   - If no: you'll create it from scratch using the template below.

2. **Read the code** — always read from the filesystem, never from memory:
   - `Domain/*.php` — aggregates, value objects, domain events, exceptions, invariants
   - `Presentation/Http/` — controllers (routes, HTTP methods, auth annotations)
   - `Application/Command/` and `Application/Query/` — command/query names give the write/read surface
   - `Infrastructure/Persistence/` — new *Model classes, new columns, new repository methods

3. **Write / update the AGENTS.md** following the template below.

4. **Create `CLAUDE.md`** in the same directory if it doesn't exist:
   ```markdown
   @AGENTS.md
   ```

### Step 3 — Sync cross-cutting docs

Check and update these docs if the branch affected their subject matter:

| Doc | When to update |
|-----|----------------|
| `docs/persistence.md` | New entity, new column, new migration, new `*Model` class, new repository pattern |
| `docs/ARCHITECTURE.md` | New bounded context, new bus, new deployment pattern |
| `docs/ai-workflow.md` | New skill, changed workflow step, new automation pattern |
| `.ai/specs/{file}.md` | Update the spec's **Changelog** with phase completion date and summary |
| `.ai/lessons.md` | New pitfall discovered during implementation — add an L-### entry |

Read the existing doc, determine what changed, and update the relevant section. Don't rewrite unchanged sections.

### Step 4 — Check the root `AGENTS.md` Task Router

If the branch introduces a new context or a new task pattern (new type of endpoint, new cross-context communication pattern, new skill), add/update the relevant row in the Task Router table in the root `AGENTS.md`.

### Step 5 — Commit

Stage and commit all doc changes together:

```bash
git add apps/api/src/*/AGENTS.md apps/api/src/*/CLAUDE.md \
       AGENTS.md \
       docs/persistence.md docs/ARCHITECTURE.md docs/ai-workflow.md \
       .ai/specs/ .ai/lessons.md
git commit -m "docs: sync project docs after <feature-name>"
```

---

## AGENTS.md Template

```markdown
# <Context> — Bounded Context

<One paragraph: what this context owns and what it does NOT own.>

---

## API Surface

| Endpoint | Method | Auth | Notes |
|----------|--------|------|-------|
| `POST /api/<resource>` | POST | `ROLE_USER` | ... |

---

## Domain Rules & Invariants

- <Rule 1> (which value object / method enforces it)
- <Rule 2>
- <Cross-context dependency: what, why, how (QueryBus / event)>

---

## Always

- <Concrete rule agents must follow when editing this context>

## Never

- <Anti-pattern with reason>

---

## Structure

\`\`\`
Domain/
├── <Aggregate>.php
├── ...
Application/
├── Command/<Verb><Entity>/...
├── Query/<Get|List><Entity>/...
Infrastructure/
└── Persistence/
    ├── Doctrine<Entity>Repository.php
    └── Doctrine/<EntityModel>.php
Presentation/
└── Http/
    ├── <Verb><Entity>Controller.php
    └── Dto/...
\`\`\`

---

## Validation Commands

\`\`\`bash
make lint
make test
\`\`\`
```

---

## Quality Bar

A good context AGENTS.md answers these questions without reading the code:

1. **What does this context own?** (domain boundary, one paragraph)
2. **What is the full API surface?** (every route, method, auth level)
3. **What are the domain invariants?** (the rules the aggregate enforces)
4. **What are the cross-context dependencies?** (what, direction, mechanism)
5. **What must I never do here?** (anti-patterns specific to this context)
6. **Where are the files?** (abbreviated structure tree)

If any of these are missing or stale, update before committing.

---

## When to Run

| Trigger | Action |
|---------|--------|
| After `/implement-spec` completes a phase | Run `sync-context-docs` for affected contexts |
| Before `/open-pr` | Always run — enforced gate |
| After a hotfix touches domain logic | Run for affected context |
| When a context AGENTS.md is visibly stale | Run on demand |

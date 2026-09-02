# Specs Folder — Agent Rules

Check `.ai/specs/` before modifying an established bounded context. Create or update specs when the change is non-trivial.

## Always

- Check `.ai/specs/` (including `.ai/specs/implemented/`) before modifying a context.
- Create a new spec for a new bounded context, a significant feature, or any architecture change touching multiple files.
- Update an existing spec when changing APIs, data models, workflows, permissions, or cross-context behaviour.
- Keep specs implementation-accurate. Update the **Changelog** section after implementation.
- Give every spec a `## Delivery` ledger — one checklist line per delivery unit, each naming its branch
  in backticks. **Split by default**, 1-3 phases per unit. See **Spec Lifecycle** below.
- Use the root **Task Router** in `AGENTS.md` to identify all related guides for review.

## Ask First

- Ask before moving a spec to `implemented/` if deployment/completion evidence is incomplete.
- Ask before renaming existing spec files.
- Ask before ticking a `## Delivery` unit whose branch is not the current one.

## Never

- Never introduce `SPEC-*` filename prefixes — new specs use `{YYYY-MM-DD}-{kebab-case-title}.md`.
- Never leave stale endpoints, entities, or assumptions in an updated spec.
- Never archive a spec while its `## Delivery` ledger has an unticked unit — the remaining PRs need to
  find it in `.ai/specs/`.
- Never archive a spec by hand-editing paths or `mv` — run `/archive-spec`, which uses `git mv` and
  repoints every in-repo reference to the old path.

## Validation Commands

```bash
find .ai/specs -maxdepth 2 -name '*.md' -print
```

## Spec Lifecycle

- **Root** (`.ai/specs/`): pending, draft, in-progress, or partially implemented specs.
- **Implemented** (`.ai/specs/implemented/`): fully implemented specs. Use `git mv` to preserve history when moving.

Specs live on the same branches as their implementation. There is no separate spec-only branch or spec-only PR — the spec is committed as the first commit of its **first** delivery unit.

### The Delivery ledger

A spec is delivered in **delivery units** — one branch, one PR, one full cycle each (own worktree, own phases, own gates, own `/code-review`) — declared as a checklist in the spec's `## Delivery` section:

```markdown
## Delivery

- [x] **PR 1** — `feat-notes-aggregate` — the Note aggregate, its write path, M1
- [ ] **PR 2** — `feat-notes-read-model` — the query side and the list endpoint
- [ ] **PR 3** — `feat-notes-ui` — the page, the create form, the catalogs
- [ ] **PR 4** — `feat-notes-sharing` — the share command and its subscriber
```

`- [x]` means the unit has landed on `main` or lands with the PR being opened now; `- [ ]` means it is still owed. The branch name in backticks binds a unit to a branch — read it, never guess it.

**Split by default.** Several units per spec is the standard shape, because a unit is the granularity at which every quality mechanism here operates: the final `/code-review` reads the branch diff, `/run-gates` scopes to the branch diff, and `/implement-spec` carries the branch's whole context. A unit too large degrades all three at once, and it does so quietly — the run produces worse work rather than failing. Size each unit at **1-3 phases**, and give each one a name that needs no "and". A one-unit ledger is for genuinely small specs: one bounded context, no migration, no new contract, at most two phases.

Every unit must leave `main` deployable and green — no half-wired user-visible feature, and never a reader without the migration it needs. Full sizing rule and the seams a unit boundary must fall on: `.ai/skills/spec-writing/references/delivery-units.md`.

The ledger is the spec's own record of what has landed, and it works precisely because the spec file travels to `main` with every one of its PRs.

### Later units start from a moved `main`

A unit may begin days after the previous one merged. Re-verify the spec's **Current State** section and any `file.php:123` references before implementing it — line numbers rot fastest — and re-run `/pre-implement-spec` for that unit when anything it assumed has changed. Update the spec in the unit's own PR: it is the live document every remaining unit reads.

### Archival

Archival is a commit on the spec's **last** delivery PR, made by `/archive-spec` and reviewed like any other change. Nothing archives specs on merge: a merge event only knows which files the merging PR touched, so it archives a four-PR spec after PR 1 (see `.ai/lessons.md` L-010).

`/implement-spec` calls `/archive-spec` after its final code review. That skill ticks the current branch's unit, then archives only if no unit is left unticked — otherwise it commits the tick and leaves the spec in `.ai/specs/` for the next branch.

## File Naming

```
{YYYY-MM-DD}-{kebab-case-title}.md
```

Examples:
- `2026-06-04-add-billing-bounded-context.md`
- `2026-06-07-add-user-profile-endpoint.md`
- `2026-06-12-replace-doctrine-transport-with-amqp.md`

## Workflow

### Before coding

- Find related spec(s), read current intent, identify deltas.
- If no spec exists and triggers apply, create one before implementation (use the `spec-writing` skill).
- The spec is authored on the first delivery unit's `feat-<slug>` branch (created by `/new-feature`) and committed locally. No spec-only PR is opened. That unit's implementation follows on the same branch after the audit passes; every later unit gets its own `/new-feature` worktree.

### During coding

- Keep spec sections in sync with architecture and API/model decisions.
- Record scope changes and trade-offs as they happen.

### After coding

- Update **Changelog** with date and one-line summary.
- Re-run the **Final Compliance Report** section in the spec.
- Run `/archive-spec` — it ticks this branch's `## Delivery` unit and archives the spec if it was the last.

## Spec Content Checklist

Every non-trivial spec includes:

- **TLDR** — 2-3 sentence summary.
- **Overview** — context and motivation.
- **Problem Statement** — what we're solving.
- **Proposed Solution** — high-level approach.
- **Architecture** — bounded-context layout, layer responsibilities, bus interactions.
- **Data Models** — entities, value objects, `*Model` persistence class fields.
- **API Contracts** — HTTP routes (path + method + DTO + response), CLI commands.
- **Phasing** — broken into testable phases; each phase ends with a working app.
- **Delivery** — the ledger: one checklist line per delivery unit, each naming its branch in backticks.
- **Risks & Impact Review** — concrete failure scenarios, severity, mitigation, residual risk.
- **Integration Coverage** — which PHPUnit Functional tests (API) and Vitest + RTL tests (frontend) must exist for the new behaviour.
- **Final Compliance Report** — checklist of architectural rules cleared.
- **Changelog** — date + summary appended after implementation.

## Triggers

| Create / Update | Skip |
|---|---|
| New bounded context | Typo / docstring edits |
| New endpoint that crosses contexts | Isolated one-file refactor |
| Schema change touching > 1 entity | Test-only changes |
| New domain event | Dependency bump |
| Changing auth/permissions | CI-only changes |

## Detailed Guidance

Use the `spec-writing` skill: `.ai/skills/spec-writing/SKILL.md`.

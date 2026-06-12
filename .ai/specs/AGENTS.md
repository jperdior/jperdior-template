# Specs Folder — Agent Rules

Check `.ai/specs/` before modifying an established bounded context. Create or update specs when the change is non-trivial.

## Always

- Check `.ai/specs/` (including `.ai/specs/implemented/`) before modifying a context.
- Create a new spec for a new bounded context, a significant feature, or any architecture change touching multiple files.
- Update an existing spec when changing APIs, data models, workflows, permissions, or cross-context behaviour.
- Keep specs implementation-accurate. Update the **Changelog** section after implementation.
- Use the root **Task Router** in `AGENTS.md` to identify all related guides for review.

## Ask First

- Ask before moving a spec to `implemented/` if deployment/completion evidence is incomplete.
- Ask before renaming legacy spec files.

## Never

- Never introduce `SPEC-*` filename prefixes — new specs use `{YYYY-MM-DD}-{kebab-case-title}.md`.
- Never leave stale endpoints, entities, or assumptions in an updated spec.

## Validation Commands

```bash
find .ai/specs -maxdepth 2 -name '*.md' -print
```

## Spec Lifecycle

- **Root** (`.ai/specs/`): pending, draft, in-progress, or partially implemented specs.
- **Implemented** (`.ai/specs/implemented/`): fully implemented and deployed specs. Use `git mv` to preserve history when moving.

Specs are published to `main` via a **spec-only PR** (`spec/<slug>` branch → PR → merge) before any implementation branch is created. This keeps the design discoverable from every clone and ensures the design is reviewed before code is written.

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
- The spec **must be on `main`** (merged via a spec-only PR) before any implementation branch is created. Never implement against a spec that only lives on a feature branch.

### During coding

- Keep spec sections in sync with architecture and API/model decisions.
- Record scope changes and trade-offs as they happen.

### After coding

- Update **Changelog** with date and one-line summary.
- Re-run the **Final Compliance Report** section in the spec.

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

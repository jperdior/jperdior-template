---
name: pre-implement-spec
description: Audit a spec before implementation. Produce a readiness report — gap analysis, backward-compatibility impact, risk assessment, missing tests. Triggers on "pre-implement", "analyze spec", "spec readiness", "spec gap analysis".
---

# Pre-Implement Spec

Audit a spec under `.ai/specs/` before any code is written. Output a **Readiness Report** that surfaces gaps, BC risks, missing coverage, and hidden assumptions. Goal: catch issues that would otherwise force mid-implementation rework.

## Workflow

1. **Identify the spec.** Confirm the file path (e.g. `.ai/specs/2026-06-04-add-notes.md`). If unclear, ask.
2. **Read the spec end-to-end.** Note every aggregate, endpoint, migration, and integration test it proposes.
3. **Run the compliance gate** (`.ai/skills/spec-writing/references/compliance-gate.md`). Every failure is a gap.
4. **Backward-compatibility audit**: for every contract surface the spec touches (event IDs, API routes, response fields, DB columns, DI service names, exported types), check whether it's removed/renamed. If yes, verify the deprecation bridge is documented.
5. **Cross-context impact**: list every existing context the spec touches by **import**, **event subscription**, or **shared package**. Flag direct domain imports as **Critical**.
6. **Test coverage gap**: for each Phase, enumerate the tests the spec says exist. Compare against the code paths added. Flag missing tests as **High**.
7. **Risk hot-spots**: enumerate auth checks, tenant isolation (if `tenancy-php` is in play), refresh-token rotation, idempotency, migration scope.
8. **Output**: produce the Readiness Report (template below).

## Output Format

```markdown
# Readiness Report: {Spec Title}

**Spec**: `.ai/specs/{file}.md`
**Phases**: {N}
**Aggregates affected**: {list}
**Contexts affected**: {list}

## Verdict

- [ ] Ready to implement
- [ ] Needs revisions (see Critical/High findings)

## Critical

{Boundary violations, bus bypass, attribute mapping, missing deprecation bridge, missing auth, multi-tenancy in core.}

## High

{Missing test coverage, unclear undo semantics, vague API contract, missing idempotency guarantees.}

## Medium

{Inconsistent naming, missing phase boundaries, unclear failure modes.}

## Low

{Nits, diagram fixes, copy.}

## Test Coverage Map

| Phase | Path / area | Test type | Spec'd? | Exists? |
|-------|-------------|-----------|---------|---------|
| 0     | …           | PHPUnit Functional | yes | no |
| 1     | …           | Playwright e2e | yes | no |

## Backward Compatibility Audit

| Contract surface | Change | BC impact | Mitigation |
|------------------|--------|-----------|------------|

## Suggested Revisions

1. …
2. …

## Next step

If verdict = ready: run `/implement-spec {file}`.
If verdict = needs revisions: update the spec, then re-run `/pre-implement-spec`.
```

## Heuristics

- A spec that says "we'll handle errors later" is **not ready** — push back.
- A spec with no integration tests for a behaviour change is **not ready**.
- A spec that adds an endpoint without declaring its `ROLE_*` requirement is **not ready**.
- A spec touching auth without mentioning refresh-token rotation is **not ready**.
- A spec adding a column without a migration is **not ready**.

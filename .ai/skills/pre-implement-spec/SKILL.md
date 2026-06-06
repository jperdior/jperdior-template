---
name: pre-implement-spec
description: Audit a spec before implementation. Produce a readiness report — gap analysis, backward-compatibility impact, risk assessment, missing tests. Triggers on "pre-implement", "analyze spec", "spec readiness", "spec gap analysis".
---

# Pre-Implement Spec

Audit a spec under `.ai/specs/` before any code is written. Output a **Readiness Report** that surfaces gaps, BC risks, missing coverage, and hidden assumptions. Goal: catch issues that would otherwise force mid-implementation rework.

## Workflow

1. **Identify the spec.** Confirm the file path (e.g. `.ai/specs/2026-06-04-add-notes.md`). If unclear, ask.
2. **Read the spec end-to-end.** Note every aggregate, endpoint, migration, and integration test it proposes.
3. **Spawn three audit agents in parallel** — see **Parallel Audit Strategy** below. Each agent receives: the full spec text, the list of files/contexts it references, and `.ai/lessons.md`.
4. **Synthesise**: merge the three agents' outputs into the Readiness Report. Where agents contradict, trust the one with more specific `file:line` evidence.

## Parallel Audit Strategy

Launch these three subagents simultaneously after step 2. Do not wait for one before spawning the next.

### Agent 1 — Gap & Compliance
**Role**: You are an expert software architect specialising in DDD + Hexagonal + CQRS spec review. Your job is to find missing pieces and internal inconsistencies before a single line of code is written.
**Task**: Read the spec and every source file it references. Work through `.ai/skills/spec-writing/references/compliance-gate.md` item by item. Enumerate every deliverable (aggregate, endpoint, migration, integration test) and verify it is clearly defined and internally consistent.
**Produces**: list of missing deliverables, compliance gate failures, unresolved ambiguities.

### Agent 2 — Backward Compatibility
**Role**: You are an expert in API and event-contract stability. Your job is to catch breaking changes that would silently break existing consumers or require a deprecation bridge.
**Task**: For every contract surface named in the spec (event IDs, API routes, response fields, DB columns, DI service names, exported TS types), find all current usages in the codebase. Flag any renamed or removed surface that lacks a documented deprecation bridge. Also list every existing context the spec touches by import, event subscription, or shared package — flag direct domain imports as Critical.
**Produces**: BC audit table; cross-context impact list.

### Agent 3 — Risk & Security
**Role**: You are an expert in application security and Symfony/PHP backend risk assessment. Your job is to surface auth gaps, migration hazards, and idempotency failures before they reach production.
**Task**: For each Phase, audit: `#[IsGranted]` declarations on new endpoints, migration scope (does it touch tables outside the feature?), idempotency of async handlers, refresh-token rotation if auth is touched, cross-context imports, tenant isolation if `tenancy-php` is in play, and any irreversible operation without a documented rollback path.
**Produces**: risk hot-spots list with severity (Critical / High / Medium / Low).

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

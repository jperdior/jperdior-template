---
name: spec-writing
description: Draft or review architectural specs under .ai/specs/. Use when starting a new feature, a new bounded context, or any change touching multiple files. Adopts a "staff engineer" reviewer lens to ensure DDD + Hexagonal + CQRS purity, bus discipline, and bounded-context isolation.
---

# Spec Writing & Review

Design and review specifications against this template's architecture (DDD + Hexagonal + CQRS, Persistence Model pattern, three Messenger buses, bounded-context isolation enforced by deptrac). Adopt the **staff engineer** persona — flexible about innovation, uncompromising about boundaries.

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:brainstorming` — design first, code never until approved; collaborative dialogue to validate the approach and produce a design doc before writing the spec.
- `superpowers:writing-plans` — after the spec is finalised, structure it into a concrete implementation plan with bite-sized tasks.

For research-heavy specs (new bounded context, cross-cutting concern), spawn an Explore agent before step 5 to benchmark the design against known PHP/Symfony DDD + Hexagonal + CQRS patterns — return gap analysis and 2-3 alternative designs with trade-offs.

## Workflow

1. **Load Context**: read the task description and `.ai/specs/AGENTS.md`. Identify which bounded contexts and packages are affected. Use the root `AGENTS.md` Task Router to find every related guide.
2. **Initialize**: create `.ai/specs/{YYYY-MM-DD}-{kebab-case-title}.md`.
3. **Start Minimal — Skeleton + Open Questions Gate**: Write a skeleton (TLDR + 2-3 key sections only). Before writing the skeleton, scan the brief for **critical unknowns** — decisions where the wrong assumption forces a rewrite. List them as an **Open Questions** block (`Q1`, `Q2`, …) immediately after the TLDR. **STOP after presenting the skeleton.** Do not proceed past this gate until the user has answered every question.
4. **Apply Answers**: remove the Open Questions block and fill the skeleton.
5. **Research**: when relevant, compare against open-source leaders / RFCs / Symfony recipes. Quote evidence.
6. **Design**: write the Architecture, Data Models, API Contracts sections. For every aggregate, declare which **bounded context** it lives in. For every endpoint, declare which **bus** it dispatches to. For every cross-context need, declare the **domain event** or **public application service** used (NOT a direct import).
7. **Phasing**: break delivery into testable phases. Each phase ends with `make test` passing and a working app. All phases ship together in a single PR (the spec commits as the first commit, then phases accumulate on the same `feat-<slug>` branch). Define phases at a granularity that is independently reviewable and leaves the app in a valid state at each checkpoint.
8. **Risks & Impact**: document concrete failure scenarios (severity, affected area, mitigation, residual risk).
9. **Integration Coverage**: list the PHPUnit Functional tests (API) and Vitest + RTL tests (apps/web, apps/admin) that must exist for the new behaviour.
10. **Compliance Gate**: apply [references/compliance-gate.md](references/compliance-gate.md).
11. **Output**: finalise the spec. If any new business rules were introduced, add them to `.ai/business-rules.md`.
12. **Commit the spec locally** on the current `feat-<slug>` branch:
    - `git add .ai/specs/{file} && git commit -m "spec: {title}"`
    - No spec-only PR is opened. The spec travels with the implementation in the same PR.
    - **Auto-proceed** to `/pre-implement-spec .ai/specs/{file}.md` — the audit runs next. If gaps are found, update the spec and re-audit before coding starts.
    - After the audit passes, run `/implement-spec .ai/specs/{file}.md`.

## Output Formats

### New Spec — Use the Template

See [references/spec-template.md](references/spec-template.md) for the full skeleton.

### Reviewing an Existing Spec

```markdown
# Architectural Review: {Spec Title}

## Summary
{1-3 sentences: what the spec proposes and overall architectural health}

## Findings

### Critical
{Boundary violations (cross-context imports), bus bypass, attribute mapping on domain entities, missing deprecation bridge}

### High
{Missing Phase strategy, unclear undo semantics, missing API contract, no integration coverage}

### Medium
{Inconsistent terminology, missing failure scenarios, suboptimal aggregate boundary}

### Low
{Nits, diagram improvements}

## Checklist
See [references/spec-checklist.md](references/spec-checklist.md).
```

## Review Heuristics (The Staff-Engineer Lens)

1. **Boundary integrity**: does the spec propose any `use App\<OtherContext>\Domain\…` import? If yes, that's a **Critical** violation — propose an event or a public application service instead.
2. **Bus discipline**: do controllers dispatch through `CommandBus` / `QueryBus`? Or does the spec wire a handler directly into a controller? Critical.
3. **Mapping discipline**: does the spec add `#[ORM\Entity]` / `#[ORM\Column]` attributes to a domain entity? Critical — ORM attributes belong on `*Model` persistence classes in `Infrastructure/Persistence/Doctrine/`, never on domain entities.
4. **Aggregate granularity**: is there exactly one aggregate root per write transaction? Splitting one logical transaction across two aggregates is a smell; merging two distinct lifecycles into one aggregate is also a smell.
5. **Value object usage**: are user-supplied strings validated at value-object construction (`UserId`, `Email`, `HashedPassword`) rather than after handler dispatch?
6. **Undoability**: for state-changing commands, is the inverse documented? Even if not implemented yet, the spec should describe how the change can be reverted.
7. **Idempotency**: are subscribers and workers idempotent? Messenger can retry.
8. **Auth & RBAC**: does every protected endpoint declare its `ROLE_*` requirement?
9. **Frontend boundary**: for UI work, is the Server/Client component boundary explicit? Are `"use client"` files justified? Does the spec describe loading / error / empty states?
10. **API contract field alignment**: every endpoint with a request or response body must include an explicit JSON example — not just a DTO class name. The PHP DTO constructor property name (e.g., `$password`) is the exact JSON key that `#[MapRequestPayload]` deserializes, and the TypeScript client must use that exact key. A spec that only names the DTO class without showing the JSON shape is a **High** finding — it guarantees a field-name mismatch between backend and frontend.

## Reference Materials

- [references/spec-template.md](references/spec-template.md) — the canonical skeleton
- [references/spec-checklist.md](references/spec-checklist.md) — the review checklist
- [references/compliance-gate.md](references/compliance-gate.md) — final compliance gate
- Root [`AGENTS.md`](../../../AGENTS.md) — Task Router
- [`.ai/lessons.md`](../../lessons.md) — known pitfalls
- [`.ai/ds-rules.md`](../../ds-rules.md) — design system rules for frontend specs

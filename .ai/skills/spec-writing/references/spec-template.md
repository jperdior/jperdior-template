# Spec Template

```markdown
# {Title}

## TLDR

{2-3 sentences. What is this? Why now? What changes?}

## Overview

{Context, motivation, business value. 1-2 paragraphs.}

## Problem Statement

{What are we solving? Quote concrete pain.}

## Proposed Solution

{High-level approach. Diagrams welcome. Keep ASCII; render at PR time.}

## Architecture

- **Bounded context(s) affected**: {list}
- **New aggregates / value objects**: {list}
- **Buses used**: command / query / event
- **Cross-context interaction**: {via which domain events, or which public application services}

## Data Models

For each aggregate:
- Identifier (UUID v4 unless v5-from-business-key)
- Fields with types and invariants
- Doctrine XML mapping snippet (in `Infrastructure/Persistence/Doctrine/Mapping/<Aggregate>.orm.xml`)
- Migrations needed

## API Contracts

| Method | Path | Auth | Request DTO | Response DTO | Notes |
|--------|------|------|-------------|--------------|-------|

For each:
- Validation rules (route attributes + value-object construction)
- Error responses (404, 422, 401, 403, 409)
- OpenAPI annotations (Nelmio)

## Frontend Plan (if applicable)

- Routes (App Router segments)
- Server vs Client components — justify each `"use client"`
- Forms (shadcn + zod schema)
- Loading / error / empty states
- Mutation strategy (Server Action vs client-side fetch)

## Phasing

| Phase | Goal | Deliverable |
|-------|------|-------------|
| 0 | …  | … (each phase ends with `make test` green) |
| 1 | …  | … |

## PR Plan

Each PR is a branch stacked on the previous — merge in order. `/implement-spec` creates these branches and PRs automatically.

| PR | Branch | Phases | Base |
|----|--------|--------|------|
| 1 | `feat/{slug}-domain` | 0 | `main` |
| 2 | `feat/{slug}-persistence` | 1 | PR 1 |
| 3 | `feat/{slug}-application` | 2 | PR 2 |
| 4 | `feat/{slug}-presentation` | 3 | PR 3 |

> **Default grouping** — collapse or split rows as the feature warrants:
> - **Domain**: aggregate, value objects, repository interface, domain events
> - **Persistence**: XML mapping, Doctrine repository, migration
> - **Application**: commands, queries, handlers
> - **Presentation**: controllers, DTOs, routes, functional tests
>
> Collapse Domain + Persistence into one PR when the aggregate is simple. Add a Frontend PR when UI is involved. Each PR must leave `make lint && make test` green independently.

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|

## Integration Coverage

| Test ID | Type | Path | Asserts |
|---------|------|------|---------|
| TC-… | PHPUnit Functional | `apps/api/tests/Functional/…` | … |
| TC-… | Playwright | `apps/web/e2e/…` | … |

## Backward Compatibility

- [ ] No removed/renamed event IDs
- [ ] No removed/renamed API routes
- [ ] No removed response fields
- [ ] No removed DB columns
- [ ] Deprecation bridge added if any contract surface changed

## Open Questions

(Remove once answered.)

- Q1. …
- Q2. …

## Final Compliance Report

(Filled in at the end. See `references/compliance-gate.md`.)

## Changelog

| Date | Change |
|------|--------|
| {YYYY-MM-DD} | Spec drafted. |
| {YYYY-MM-DD} | Phase 0 implemented. |
```

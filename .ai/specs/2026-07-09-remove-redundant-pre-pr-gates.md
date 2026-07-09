# Remove Redundant Pre-PR Gate Runs

## TLDR

`/implement-spec` already runs `/run-gates` after every phase, so requiring gates again in `/open-pr` and `AGENTS.md`'s "Pre-PR gate (mandatory)" block is pure waste. This spec removes those duplicate requirements and adds a note to `/check-and-commit` clarifying it is not needed in the implement-spec flow.

## Overview

The harness currently mandates three separate gate runs for a typical spec-driven feature:

1. After each `/implement-spec` phase → `/run-gates` (✅ correct, catches regressions early).
2. Before committing the final phase → `/check-and-commit` re-runs `/run-gates`.
3. Before opening the PR → `AGENTS.md` "Pre-PR gate (mandatory)" + `open-pr` Superpowers hint re-runs the gate a third time.

Runs 2 and 3 add minutes of wall-clock time without adding any safety, because the last phase gate is still green when you reach them.

## Problem Statement

- `AGENTS.md` lines 151–159 contain a "### Pre-PR gate (mandatory)" block that says `make lint && make test` must pass **before offering to create a PR**. This was written before `/implement-spec` existed and is now stale.
- `AGENTS.md` line 290 ("Short path") says "Still run `make lint && make test` before `/open-pr`."
- `AGENTS.md` line 335 says "run `make lint && make test`" as a core verification step.
- `open-pr/SKILL.md` lists `superpowers:verification-before-completion` (run `/run-gates`) as a required pre-step.
- `check-and-commit/SKILL.md` mandates a full `/run-gates` run even when invoked right after an implement-spec phase that already ran gates.

## Proposed Solution

**Three targeted edits to harness documents. No PHP/TS code changes.**

1. **`AGENTS.md`** — rephrase the "Pre-PR gate (mandatory)" sub-section (lines 151-159) to a conditional: "Only needed if the branch was NOT built via `/implement-spec`. If you used `/implement-spec`, the last phase's `/run-gates` already covers this." Do NOT remove the short-path gate note (line 290) — that flow has no phase gates. Keep the Core Principles "Verification" bullet but add the same conditional framing.
2. **`open-pr/SKILL.md`** — remove `superpowers:verification-before-completion` from the Superpowers Integration block (it implies re-running gates, which are already green after the last implement-spec phase).
3. **`check-and-commit/SKILL.md`** — add a note at the top of the Workflow section: "If you are in an `/implement-spec` flow and the last phase gate passed, skip steps 2–4 (gate run) and proceed directly to composing the commit."

## Architecture

- **Bounded context(s) affected**: none (harness-only)
- **New aggregates / value objects**: none
- **Buses used**: none
- **Cross-context interaction**: none

## Data Models

N/A — no schema changes.

## API Contracts

N/A — no HTTP endpoints touched.

## Phasing

| Phase | Goal | Deliverable |
|-------|------|-------------|
| 0 | Rephrase pre-PR gate in `AGENTS.md` to a conditional (implement-spec flow only) | Updated `AGENTS.md` — pre-PR gate section + Core Principles rephrased; short-path note preserved |
| 1 | Remove gate hint from `open-pr/SKILL.md` | Updated `open-pr/SKILL.md` |
| 2 | Add "skip-if-post-implement-spec" note to `check-and-commit/SKILL.md` | Updated `check-and-commit/SKILL.md` |

All three phases are harness-file edits only. No `make test` run is needed; the gate is not applicable to documentation-only changes. Each phase leaves the harness in a consistent, usable state.

## Risks & Impact Review

| Risk | Severity | Affected area | Mitigation | Residual |
|------|----------|---------------|------------|----------|
| Short-path branches that skip `/implement-spec` now have no enforced gate before PR | Low | Short-path | Short-path gate note in `AGENTS.md` line 290 is preserved unchanged; hotfix path is unchanged | Low — CI on GitHub catches any failure |
| Developer forgets to run gates entirely on a non-implement-spec branch | Low | Any manual branch | CI catches failures; `check-and-commit` still runs gates when invoked standalone | Acceptable |

## Integration Coverage

N/A — documentation-only change; no PHPUnit or Vitest tests apply.

## Backward Compatibility

- [ ] No removed/renamed event IDs ✅
- [ ] No removed/renamed API routes ✅
- [ ] No removed response fields ✅
- [ ] No removed DB columns ✅
- [ ] Deprecation bridge added if any contract surface changed ✅ (n/a)

## Final Compliance Report

| Gate | Question | Pass |
|------|----------|------|
| Boundary | Cross-context Domain import introduced? | ✅ No — no PHP code |
| Bus | Controller dispatches through bus? | ✅ No — no PHP code |
| Mapping | ORM attributes on domain entity? | ✅ No — no PHP code |
| Validation | Inputs validated at value-object construction? | ✅ No — no PHP code |
| Tests | Coverage planned? | ✅ N/A — doc-only |
| BC | Contract surface removed/renamed? | ✅ No |

## Changelog

| Date | Change |
|------|--------|
| 2026-07-09 | Spec drafted. |

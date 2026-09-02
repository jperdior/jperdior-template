# Delivery Units — how to split a spec across PRs

A **delivery unit** is one branch, one PR, one full cycle: its own worktree, its own phases, its own
gates, its own `/code-review`. A spec declares its units as the checklist in its `## Delivery` section.

**Split by default.** Several units per spec is the standard shape.

## Why splitting is the default

A unit is the granularity at which every quality mechanism in this repo actually operates. `/implement-spec`
dispatches a fresh implementer per phase and reviews the *whole branch* once at the end; `/run-gates` scopes
to the branch diff; `/code-review` reads the branch diff. Make the branch large and all three degrade
together — the final review spans work whose reasoning no longer fits, the gate matrix runs wide on every
push, and the implementation run itself burns its budget on carrying context instead of writing code. Output
quality falls off before the run fails, which is the dangerous part: a too-large unit does not error, it
quietly produces worse work.

Splitting is not overhead. It is what keeps each review, each gate run, and each implementation run at a
size that can be done properly.

## Sizing rule

**One unit = the work one `/implement-spec` run can carry end to end without straining, and one PR a
reviewer can hold in their head.** In practice:

- **One to three phases per unit.** A unit whose phase list runs past three is two units.
- **One PR title, one line, no "and".** If naming the unit needs a conjunction, it is two units.
- **One bounded context where possible.** A unit spanning three contexts is usually three units, unless
  the whole point of the unit is the contract between them.

## Seams a unit boundary must fall on

These are ordering constraints, not preferences. State the required merge order in the ledger.

| Seam | Rule |
|---|---|
| **Security mitigation → the feature it protects** | The mitigation is its own unit and merges **first**. Never ship the data path in the same PR as the fence that makes it safe; a partial merge then leaves the hole open on `main`. |
| **Migration → its reader** | The migration, its `*Model` ORM metadata, and the code reading or writing those columns go in **one** unit. A deployed schema nobody reads yet is harmless; a reader whose columns do not exist is a broken `main`. Never split these apart. |
| **Backend contract → frontend consumer** | The endpoint, its DTOs, the Nelmio annotation and the regenerated `openapi.json` + `types.gen.ts` land before the UI that calls them. The frontend unit then has a real contract to build against instead of a promised one. |
| **New bounded context → its surface** | Unit one is the 4-layer skeleton plus one working command or query end to end. The rest of the surface follows in later units, against a layout that already passes `deptrac`. |
| **Cross-context event → its subscriber** | The publishing side (event class, `EventBus` + `TransactionInterface` wiring) can land with its producer; the reacting side is its own unit. |

## Every unit leaves `main` deployable

Non-negotiable, and it is what makes splitting safe:

- `make lint` and `make test` green on the unit's own branch — each unit runs the full gate.
- No half-wired user-visible feature. Ship the UI in the unit that completes it; a picker that calls an
  endpoint returning 404 is not a delivery unit, it is a broken deploy.
- No dangling contract. A unit that adds a request field nothing reads, or a column nothing writes, has to
  say in the ledger which later unit consumes it.

## Later units start from a moved `main`

Unit 3 may begin days after unit 1 merged, on a `main` that has moved underneath the spec.

- Re-verify the spec's **Current State** section (and any `file.php:123` line references) before
  implementing a later unit. Line numbers rot fastest.
- Re-run `/pre-implement-spec` for that unit when anything it assumed has changed on `main`. The audit is
  cheap next to implementing against a stale premise.
- Update the spec in the unit's own PR when reality has moved. The spec is the live document every
  remaining unit reads.

## When one unit is right

A genuinely small spec: one bounded context, no migration, no new API contract, one or two phases. Then the
spec commits as the first commit, phases accumulate on the same branch, and spec + implementation + archival
ship together in a single PR. Say so in the ledger and move on — do not manufacture units to hit a count.

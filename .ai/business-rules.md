# Business Rules Registry

The domain reasoning this repository keeps outside the code. **A rule is a statement line and a
`**Why:**` line, and nothing else** — the id is the link back to the enforcement point, and
`grep -rn <id> apps/api` is the query that finds it. Whatever narrative a rule needs beyond its why
lives in the spec that introduced it, under `.ai/specs/implemented/`, and in `git log -S`.

```markdown
- **BR-U05** — `POST /auth/forgot-password` always answers `204`, whether or not the email is registered.
  **Why:** any difference in status, body or timing is a user-enumeration oracle.
```

Neither line wraps and neither exceeds 300 characters. There is no `Context:` line (the file it is
in says the context), no `Enforcement:` paragraph (the id is the link), and no pointer to a spec (a
pointer to 400 lines is an invitation to read 400 lines).
`ops/ci/check-business-rules-index.sh` enforces the shape, runs in `make lint` and in CI, and its
header explains each check.

## Where a rule lives

**With the bounded context that owns it**, next to that context's `AGENTS.md`. This file holds only
the table below, the retired ids, and the rules every context needs. A session working on one
context then reads that context's rules instead of the whole registry — which is the entire point,
and the reason the shape is checked rather than merely described.

Add a row when a context declares its first rule. An id's alpha run is its prefix, so the lookup is
exact and never ambiguous even when one prefix starts with another:

```
BR-PB01  ->  BR-PB      BR-P13  ->  BR-P
```

| Prefix | Rules | File |
|---|---|---|
| `BR-U` | 2 | `apps/api/src/User/business-rules.md` |

## Retired ids

Ids that resolve to no current rule — one folded into another, or a prefix left behind by a context
rename. They are listed because a citation resolving to nothing fails the check, and because a
merged migration or an archived spec may still name one and neither can be edited.

Give a retired id a current target only where a rule's own text or an archived spec states the
correspondence **in words**. Never infer one from the number — a renumbering leaves two ids that
share a number describing entirely different rules, and a guessed mapping is worse than an empty
cell. Rows are read from the first column only, so the second may name a live id:

```
| Retired | Now | Why it is here |
|---|---|---|
| `BR-ME07` | BR-RP09 | the Meeting -> Report rename; cited by a merged migration |
```

_None yet._

## Cross-cutting

Rules every context needs — security, tenancy, quotas, storage. They live here rather than in any
one context's file, because filing them under a single context hides them from the others.

_None yet._

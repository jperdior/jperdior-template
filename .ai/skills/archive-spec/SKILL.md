---
name: archive-spec
description: Tick the current branch's unit in a spec's Delivery ledger and move the spec to .ai/specs/implemented/ only when no unit is left unticked. Triggers on "archive spec", "archive the spec", "is this the last PR of the spec".
---

# Archive Spec

Decide whether the branch about to become a PR is the spec's **last** delivery unit, and archive the
spec into `.ai/specs/implemented/` when — and only when — it is.

A spec may span several PRs. The spec file itself is the ledger that records which units have landed,
because it is the one artefact that travels to `main` with every one of those PRs. No CI job archives
specs; archival is a commit on the last delivery PR, reviewed like any other change.

## The Delivery ledger

Every spec carries a `## Delivery` section whose units are checklist lines:

```markdown
## Delivery

- [x] **PR 1** — `feat-notes-aggregate` — the Note aggregate, its write path, M1
- [ ] **PR 2** — `feat-notes-read-model` — the query side and the list endpoint
- [ ] **PR 3** — `feat-notes-ui` — the page, the create form, the catalogs
- [ ] **PR 4** — `feat-notes-sharing` — the share command and its subscriber
```

- `- [x]` — the unit has landed on `main`, or lands with the PR being opened right now.
- `- [ ]` — the unit is still owed.
- Specs are split across several units by default, so most runs of this skill tick and stop. A one-unit
  spec — genuinely small work — archives in its own PR.

The branch name in backticks is what binds a unit to a branch. Read it, never guess it.

## Workflow

1. **Branch gate** — `git branch --show-current`. If the result is `main`, stop: archival is a commit on
   a feature branch that lands through a PR, never a direct edit to `main`.

2. **Resolve the spec** — take it from the argument. With no argument, derive it from the branch:
   ```sh
   git diff --name-only --diff-filter=AM origin/main...HEAD -- '.ai/specs/*.md' \
     | grep -v '/implemented/' | grep -v '/AGENTS\.md$' | grep -v '/CLAUDE\.md$'
   ```
   Zero matches → report "no spec on this branch, nothing to archive" and stop (a hotfix or short-path
   branch legitimately has none). More than one → handle each in turn.

3. **Read the ledger.** Extract the units:
   ```sh
   awk '/^## Delivery/{f=1;next} /^## /{f=0} f' "$SPEC" | grep '^- \[[ xX]\]'
   ```
   **No `## Delivery` section** — a spec written before this convention. Do not assume it is single-unit.
   Stop and ask the user how many delivery units the spec has, add the ledger, and continue.

4. **Tick this branch's unit.** Find the unticked line whose backticked branch name equals the current
   branch and rewrite its `- [ ]` to `- [x]`. Then:
   - Already ticked → the unit landed earlier; leave it and carry on to step 5.
   - The branch is named by **no** line → stop and ask. Either the ledger is stale or this branch is not a
     delivery unit of this spec. Never invent a unit and never tick an arbitrary one.

5. **Count what is left.**
   ```sh
   awk '/^## Delivery/{f=1;next} /^## /{f=0} f' "$SPEC" | grep -c '^- \[ \]' || true
   ```
   - **Greater than zero → do not archive.** Commit the ticked ledger, report the units still owed, and
     stop. The spec stays in `.ai/specs/` where the next branch will find it.
   - **Zero → archive.** Continue to step 6.

6. **Close the spec out** before moving it:
   - Append a `## Changelog` row: `| {YYYY-MM-DD} | Implemented — PR N of N, spec archived. |`
   - Fill the **Final Compliance Report** if it is still a placeholder (see
     `.ai/skills/spec-writing/references/compliance-gate.md`).

7. **Move it, preserving history:**
   ```sh
   git mv ".ai/specs/$(basename "$SPEC")" ".ai/specs/implemented/$(basename "$SPEC")"
   ```

8. **Repoint every reference.** A moved spec breaks every in-repo link to its old path — `.ai/lessons.md`,
   `.ai/business-rules.md`, `docs/**`, other specs, `AGENTS.md` files:
   ```sh
   BASE=$(basename "$SPEC")
   grep -rl "\.ai/specs/$BASE" --include='*.md' . | grep -v node_modules
   ```
   Rewrite each hit `.ai/specs/<BASE>` → `.ai/specs/implemented/<BASE>`. Skip anything already carrying
   `implemented/`, and leave `.ai/specs/implemented/*` files that quote a *different* spec's historical
   path alone — an archived spec is a historical record and its own text is never repointed.

9. **Commit** on the current branch:
   ```sh
   git add -A .ai/specs .ai/lessons.md .ai/business-rules.md docs AGENTS.md
   git commit -m "chore(specs): archive {slug} — last delivery unit"
   ```
   When step 5 said "do not archive", the message is instead
   `chore(specs): tick delivery unit {N} for {slug}`.

## Output

Archived:

```
✅ Spec archived: .ai/specs/implemented/{file}.md
   Delivery: {N}/{N} units ticked — this branch was the last.
   References repointed: {count} file(s)
   Committed: {sha7}
```

Not archived:

```
⏸  Spec stays open: .ai/specs/{file}.md
   Delivery: {done}/{total} units ticked (this branch = PR {N}).
   Still owed:
     - PR {M} — `{branch}` — {scope}
   Committed: {sha7}  (ledger tick only)
```

## Rules

- **Never** archive a spec with an unticked unit. That is the whole point of the skill: archival driven
  by a merge event cannot see past the merging PR, and archives a four-PR spec after PR 1.
- **Never** tick a unit for work that is not on the branch. The ledger is a claim about `main`.
- **Never** hand-move a spec with `mv` — `git mv` is what keeps the file's history.
- **Never** archive from `main`.
- A spec that is abandoned rather than implemented is **not** archived by this skill — `implemented/`
  means implemented. Ask the user what to do with it.

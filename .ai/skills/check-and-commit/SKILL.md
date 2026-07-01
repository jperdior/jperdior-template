---
name: check-and-commit
description: Verify the branch is ready (lint + test + build green), fix obvious issues, then commit and push. Triggers on "check and commit", "commit my changes", "ship it", "finish this branch".
---

# Check and Commit

Take a working branch from "I think it's done" to "committed and pushed with confidence".

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:verification-before-completion` — before every commit, run the full gate, read the complete output, and confirm 0 errors. Never claim "branch is ready" without fresh evidence from the current run.

## Workflow

1. **Confirm scope**. Run `git status`, `git diff --stat`. If unrelated files are staged or there are surprises, ask the user.
2. **Run the verification gate** — invoke `/run-gates`. It scopes the gates to the diff and dispatches each as a parallel subagent (all lint/build gates standalone, only the PHP test gate `test-api` on the shared stack).
3. **Fix obvious problems**:
   - Code-style issues → `make lint-fix`
   - Missing OpenAPI annotations → add them
   - Hardcoded strings the i18n linter caught → move to locale files
   - `any` types → narrow them
   - Cross-context import (deptrac fail) → replace with event or public application service
4. **Re-run `/run-gates`**. Every gate MUST report PASS.
5. **Compose the commit**:
   - One commit per logical change (or one per phase if implementing a spec).
   - Title format: `<type>(<context>): <summary>` (e.g. `feat(note): add CreateNoteController`).
   - Body: brief rationale + spec reference if applicable.
   - Use the project's commit-message conventions (Conventional Commits).
6. **Commit**. NEVER use `--no-verify` unless the user explicitly asks. NEVER amend a pushed commit unless asked.
7. **Push** if the user asked to push. Otherwise stop after committing.

## Output

```
✅ Branch ready
   Diff: {N} files changed (+{added} −{removed})
   Lint: PASS
   Tests: PASS ({M} test cases)
   Build: PASS
   Commit: <sha> <title>
   Pushed: yes / no (depending on user intent)
```

## Failure handling

- **Test failure** → fix the test or the code. Do NOT skip with `test.skip()` unless documented.
- **Type error** → fix the type or narrow with a runtime check. NEVER add `any`.
- **Boundary violation** → never bypass deptrac; replace the direct import.
- **Untracked file** that looks like it should be ignored → suggest adding to `.gitignore` rather than committing.

## Never

- Never run `git push --force` to a shared branch.
- Never run `git reset --hard` to "make CI pass".
- Never `git add .` blindly. Stage by name.
- Never amend a pushed commit unless the user asks.
- Never commit a `.env.local` or any file the `.gitignore` was meant to catch.

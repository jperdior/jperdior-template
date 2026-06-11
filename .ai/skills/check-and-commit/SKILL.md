---
name: check-and-commit
description: Verify the branch is ready (lint + test + build green), fix obvious issues, then commit and push. Triggers on "check and commit", "commit my changes", "ship it", "finish this branch".
---

# Check and Commit

Take a working branch from "I think it's done" to "committed and pushed with confidence".

## Workflow

1. **Confirm scope**. Run `git status`, `git diff --stat`. If unrelated files are staged or there are surprises, ask the user.
2. **Run the verification gate** in order:
   ```sh
   make lint-api
   make lint-web
   make test-api
   make test-web
   ```
3. **If UI changed**, also run:
   ```sh
   make build-web
   ```
4. **Fix obvious problems**:
   - Code-style issues → `make lint-fix`
   - Missing OpenAPI annotations → add them
   - Hardcoded strings the i18n linter caught → move to locale files
   - `any` types → narrow them
   - Cross-context import (deptrac fail) → replace with event or public application service
5. **Re-run the gate**. Every step MUST exit 0.
6. **Compose the commit**:
   - One commit per logical change (or one per phase if implementing a spec).
   - Title format: `<type>(<context>): <summary>` (e.g. `feat(note): add CreateNoteController`).
   - Body: brief rationale + spec reference if applicable.
   - Use the project's commit-message conventions (Conventional Commits).
7. **Commit**. NEVER use `--no-verify` unless the user explicitly asks. NEVER amend a previously-pushed commit unless asked.
8. **Push** if the user asked to push. Otherwise stop after committing.

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

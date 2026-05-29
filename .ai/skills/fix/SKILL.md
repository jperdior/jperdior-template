---
name: fix
description: Implement the minimal fix for a known root cause. Adds a regression test, runs the verification gate, opens a PR. Triggers on "fix this bug", "implement the fix", "apply the fix".
---

# Fix

Take a `root-cause` report and ship the minimal fix.

## Prerequisites

- A root cause report exists (from `/root-cause` or written by the user).
- The offending file and lines are identified.
- A reliable reproduction is available.

## Workflow

1. **Confirm the reproduction** locally (run the failing test or trigger the bug path).
2. **Write the regression test FIRST**:
   - PHPUnit Functional under `apps/api/tests/Functional/<Context>/…RegressionTest.php` if API.
   - Playwright spec under `apps/web/e2e/regression/…spec.ts` if UI.
   - The test MUST fail on the current code.
3. **Implement the minimal fix**. No refactoring beyond what the fix requires.
4. **Confirm the regression test now passes**.
5. **Run the verification gate**:
   ```sh
   make lint && make test && (make build-web && make test-e2e if UI changed)
   ```
6. **Run `/code-review`** on the diff.
7. **Commit**:
   - Title: `fix({context}): {one-line summary}`
   - Body: link to root-cause report + the regression test added + the line(s) changed.
8. **Hand off to `/auto-create-pr`** to open the PR. Apply category label `bug` and meta label `needs-qa` if the bug is user-facing, `skip-qa` if it's CI/test/internal.

## Rules

- **Regression test before fix.** Always. If the test still passes after the test is added, you haven't reproduced the bug correctly.
- **Minimal fix.** Resist the urge to refactor surrounding code.
- **Update `.ai/lessons.md`** if the bug pattern is likely to recur. Add a new L-### entry with the **why** and **how to apply**.
- **Don't bypass guards.** A bug fix that disables a check, removes a validation, or weakens auth is a sign you haven't found the real cause.

## Output

```
✅ Fix shipped
   Root cause: {one-line}
   Files changed: {N}
   Regression test: {path}
   Gate: PASS
   PR: {URL}
```

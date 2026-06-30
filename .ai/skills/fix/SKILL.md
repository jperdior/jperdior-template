---
name: fix
description: Implement the minimal fix for a known root cause. Adds a regression test, runs the verification gate, opens a PR. Triggers on "fix this bug", "implement the fix", "apply the fix".
---

# Fix

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:test-driven-development` — the regression test MUST be written first and confirmed RED before any fix is applied; no exceptions ("too simple to test" is not an exception).
- `superpowers:verification-before-completion` — confirm the full gate passes and the regression test is GREEN before claiming the fix is done.

For multi-file fixes (3+ files changed), dispatch an implementation agent after the failing regression test exists:

**Agent — Minimal Fix** `model: "sonnet"`

```
Agent({
  description: "Apply minimal fix",
  model: "sonnet",
  prompt: """
    You are an implementer applying a minimal bug fix. Do NOT refactor anything beyond what the failing test requires.
    Root cause: [file:line from root-cause report]
    Failing regression test: [test path and class name] — already confirmed RED.
    Step 1: Run the regression test and confirm RED output.
    Step 2: Write the minimal code change at [file:line] to make it pass.
    Step 3: Run `make test-api ARG='--filter [TestClass]'` and confirm GREEN.
    Step 4: Run `make lint-api` and confirm 0 errors.
    Report: exact lines changed + paste the passing test output.
  """
})
```

Take a `root-cause` report and ship the minimal fix.

## Prerequisites

- A root cause report exists (from `/root-cause` or written by the user).
- The offending file and lines are identified.
- A reliable reproduction is available.

## Workflow

1. **Confirm the reproduction** locally (run the failing test or trigger the bug path).
2. **Write the regression test FIRST**:
   - PHPUnit Functional under `apps/api/tests/Functional/<Context>/…RegressionTest.php` if API.
   - Vitest + RTL under `apps/web/src/**/__tests__/…regression.test.tsx` (or the equivalent path under `apps/admin/`) if UI.
   - The test MUST fail on the current code.
3. **Implement the minimal fix**. No refactoring beyond what the fix requires.
4. **Confirm the regression test now passes**.
5. **Run the verification gate**:
   ```sh
   make lint && make test && (make build-web if UI changed)
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

---
name: fix
description: Implement the minimal fix for a known root cause. Adds a regression test, runs the verification gate, opens a PR. Triggers on "fix this bug", "implement the fix", "apply the fix".
---

# Fix

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:using-git-worktrees` — the fix MUST run in a fresh, dedicated **worktree** branched from an up-to-date `main`, never in the main checkout and never on whatever branch happens to be checked out. This is unconditional — there is no diff size below which a plain in-place branch is acceptable.
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

## Setup — Update `main`, THEN isolate on a fresh branch (before any other step)

**The very first action of this skill — before reading any code, reproducing the bug, or even inspecting the current branch — is to fetch and fast-forward local `main`.** A stale local `main` is the single most destructive failure mode here: you read outdated files, "discover" bugs that were already fixed, chase ghosts across merged worktrees, and branch from an obsolete base. **Never** read a source file to diagnose a bug until local `main` matches `origin/main`.

**Never apply a fix on whatever branch happens to be checked out**, either. A stale, merged, or shared branch is not a valid base — working on it silently corrupts the diff, restarts from an outdated tree, and violates the AGENTS.md branching invariants.

Do these in order, before anything else:

1. **FIRST — fetch so `origin/main` is current** (per the AGENTS.md "Always refresh main before branching" invariant). `origin/main` is the base the new worktree branches from, so fetching is all this workflow needs:
   ```sh
   git fetch origin
   ```
   Do **not** `git checkout main` here — in a linked worktree that fails when the primary worktree already has `main` checked out (the normal case). Treat `origin/main` as the source of truth; if your primary worktree's local `main` is behind, fast-forward it there, separately. If you already read files from a stale tree, **re-read them after the fetch** — they may be stale.
2. **Reject the current branch as a base unless it is a fresh, purpose-made fix branch you created in this session.** Now that `main` is current, check what the bug actually lives on:
   ```sh
   git log origin/main..HEAD --oneline   # empty = HEAD is at origin/main (e.g. an already-merged branch) → create a fresh fix branch. Non-empty = unmerged commits: fine if this is the fix branch YOU created this session; STOP only if it's an unfamiliar or shared branch you did not create.
   ```
   A non-empty diff is *expected* for your own in-progress fix branch — it is **not** by itself a reason to stop. Stop only when the branch is `main`, an already-merged branch, or someone else's / an unrelated branch. Continue on the current branch only if you created it for *this* fix.
3. **Create a dedicated worktree from the refreshed `main` — ALWAYS, no exceptions.** Every fix runs in its own worktree branched from the updated `main`, however small the change (yes, even a one-line CSS/config/env edit). Run `/new-feature fix-<slug>` (or `EnterWorktree`):
   ```sh
   /new-feature fix-<slug>          # creates .claude/worktrees/fix-<slug> on branch fix-<slug> from main
   ```
   **Never** `git checkout -b fix-<slug>` in the main checkout. There is no "minimal hotfix" shortcut for `/fix` — the size of the diff is irrelevant. Working directly in the main checkout lets `main` accumulate uncommitted, unbranched changes and makes gates validate the wrong tree; a worktree makes that class of mistake impossible. (The AGENTS.md "Hotfix path" is a *separate* entry point for urgent fixes that do **not** go through `/fix`; once you are running `/fix`, this worktree rule governs.)
4. **Verify you are inside the worktree** (not the main checkout) before writing any code — `pwd` MUST contain `.claude/worktrees/fix-<slug>`:
   ```sh
   pwd && git branch --show-current
   ```

Do not proceed to the reproduction or the regression test until local `main` is updated **and** the isolated branch exists and is confirmed.

## Workflow

0. **Update local `main` first, then isolate the work in a dedicated worktree** branched from that up-to-date `main` — see **Setup** above. Fetching + fast-forwarding `main` is the mandatory FIRST action, before reading any code or reproducing the bug; creating the worktree comes right after. A worktree is mandatory for every fix regardless of size — never work in the main checkout.
1. **Confirm the reproduction** locally (run the failing test or trigger the bug path).
2. **Write the regression test FIRST**:
   - PHPUnit Functional under `apps/api/tests/Functional/<Context>/…RegressionTest.php` if API.
   - Vitest + RTL under `apps/web/src/**/__tests__/…regression.test.tsx` (or the equivalent path under `apps/admin/`) if UI.
   - The test MUST fail on the current code.
3. **Implement the minimal fix**. No refactoring beyond what the fix requires.
   - If the fix adds or changes user-facing text in `apps/web`, run **`/translate-strings`** so the string lands in the next-intl catalogs (`apps/web/messages/{en,es}.json`) with key-parity — never a hard-coded literal.
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

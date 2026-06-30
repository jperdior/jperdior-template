---
name: root-cause
description: Drill from a failing test, production error, or user-reported bug to the offending change. Produces a "this is what broke and when" report. Triggers on "root cause", "what broke", "why is X failing", "bisect".
---

# Root Cause

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:systematic-debugging` — 4-phase methodology (reproduce → pattern analysis → hypothesis → implement); the Iron Law: no fixes without root cause investigation first.

For complex multi-component failures, dispatch a debugging agent before step 4:

**Agent — Root Cause Analysis** `model: "opus"`

```
Agent({
  description: "Root cause analysis",
  model: "opus",
  prompt: """
    You are a senior debugger. Apply systematic-debugging phases 1-3 only — do NOT propose or apply any fix.
    Evidence:
    - Failing test / stack trace: [paste output]
    - Affected files: [list]
    - Recent git changes: [git log --oneline -10]
    Task:
    1. Phase 1 — Reproduce: confirm the failure is deterministic; identify which component boundary it crosses.
    2. Phase 2 — Pattern analysis: find working examples of the same pattern in the codebase. Read reference implementations completely. List every difference between working and broken.
    3. Phase 3 — Hypothesis: form ONE specific hypothesis ("X is root cause because Y"). Do not test it yet.
    Output: root cause statement (file:line), confidence level (HIGH/MEDIUM/LOW), evidence chain.
  """
})
```

Find the root cause of a bug or failing test. Output a precise report identifying the offending change (file:line, commit, PR).

## Workflow

1. **Reproduce** the failure locally. If you can't reproduce, ask the user for exact steps + environment.
2. **Read the error**: stack trace, failing assertion, log lines. Don't guess — look at the actual output.
3. **Access container logs** when the error isn't surfaced by tests:
   ```sh
   make logs                 # tail all containers (dev stack)
   make logs-ci              # dump all container logs (headless test stack)
   ```
   API logs output to `php://stderr` at `debug` level in dev. Frontend logs are Next.js stdout. Search for error-level entries with `grep -i error`.
4. **Map to code**: identify the function / class / route involved. Read the source.
4. **Form a hypothesis** about which change introduced the regression.
5. **Use `git bisect` or `git log -S`** to find the commit:
   ```sh
   git log -S '<symbol from the diff>' --oneline -- <path>
   git log --oneline <path>
   git bisect start <known-bad> <known-good>
   ```
6. **Verify the suspect commit**: check out the parent, confirm the bug is absent. Check out the suspect, confirm the bug is present.
7. **Identify the change**: the exact lines that introduced the regression.
8. **Identify the PR**: `gh pr list --state merged --search "<commit-sha>"`.
9. **Output the report** (template below).

## Output Format

```markdown
# Root Cause: {bug description}

## Symptom
{What the user / test reported.}

## Reproduction
{Exact steps to reproduce.}

## First-bad commit
- SHA: `{sha}`
- PR: #{N} ({title})
- Author: {name}
- Date: {YYYY-MM-DD}

## Offending change
- File: `{path}:{line}`
- What changed: {one-sentence description}

## Why it broke
{Mechanism — the actual chain from the change to the symptom.}

## Fix direction
{The minimal change that would resolve it. Don't write the fix here — that's `/fix`'s job.}

## Confidence
- HIGH if bisect localized to a single commit AND the failing test maps cleanly to the offending lines.
- MEDIUM if bisect is clean but the mechanism is not 100% pinned.
- LOW if multiple commits in the bisect range plausibly cause the issue. Ask for help.

## Suggested follow-up
- Add a regression test at: `{file path}`
- Update `.ai/lessons.md` if this pattern is likely to recur.
```

## Rules

- Never propose a fix in this skill. Hand off to `/fix` with the report.
- Never blame "flakiness" without evidence (rerun 5+ times; check for shared state).
- Never close the investigation at "weird, works for me". If you can't reproduce, document the gap.

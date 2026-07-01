---
name: auto-create-pr
description: Run an autonomous task end-to-end and ship it as a PR against the default branch. Drafts a plan in .ai/runs/, commits on a fresh worktree branch, implements step by step, runs the verification gate, applies labels. Triggers on "create a PR for", "ship this", "auto PR", "do this and PR it".
---

# Auto Create PR

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:writing-plans` — produces the `PLAN.md` with global constraints, exact file paths, step-by-step TDD cycles, and interface contracts between tasks.
- `superpowers:subagent-driven-development` — fresh subagent per implementation task, spec-compliance + code-quality review between tasks.
- `superpowers:verification-before-completion` — must verify the full gate passes before opening the PR.

**Agent — Plan Creation** `model: "opus"`

For non-trivial tasks (>2 files), dispatch a plan agent before step 2:

```
Agent({
  description: "PLAN.md creation",
  model: "opus",
  prompt: """
    You are a senior engineer writing an implementation plan. Task: [task description].
    Read the relevant AGENTS.md files for each bounded context touched.
    Produce a PLAN.md in .ai/runs/{YYYY-MM-DD}-{slug}/ following the writing-plans format:
    - Global constraints (PHP version, no cross-context imports, TDD required)
    - Task list with exact file paths (create / modify / test)
    - Step-by-step TDD cycle per task (write failing test → run → implement → run → commit)
    - Interfaces produced/consumed between tasks
    Do NOT write any code. Write the plan only.
  """
})
```

**Agent — Task Implementation** `model: "sonnet"`

For each implementation task:

```
Agent({
  description: "Implement task N",
  model: "sonnet",
  prompt: """
    You are an implementer. Execute exactly Task N from the plan at [plan path].
    Files to touch: [list from plan].
    Step 1: Write the failing test at [test path]. Run it and confirm RED.
    Step 2: Write minimal production code to pass the test. Run it and confirm GREEN.
    Step 3: Commit with message: [format from plan].
    Report status: DONE, DONE_WITH_CONCERNS, NEEDS_CONTEXT, or BLOCKED.
    Do NOT implement other tasks or refactor beyond what the test requires.
  """
})
```

Execute a user-described task autonomously and open a GitHub PR. Resumable via `auto-continue-pr` if interrupted.

## Workflow

1. **Confirm scope** in one sentence; rephrase the task back to the user. If the task touches > 1 bounded context or needs a spec, **stop and recommend `/spec-writing` first** — auto-create-pr is for bounded changes.
2. **Create a run folder**: `.ai/runs/{YYYY-MM-DD}-{slug}/` with:
   - `PLAN.md` — task description, file list, step-by-step plan
   - `HANDOFF.md` — current step + open todos (resumable)
3. **Ensure we're on a feature branch** — check if already inside a `feat-<slug>` worktree (from `/new-feature`):
   ```sh
   BRANCH=$(git branch --show-current)
   GIT_DIR=$(cd "$(git rev-parse --git-dir)" && pwd -P)
   GIT_COMMON=$(cd "$(git rev-parse --git-common-dir)" && pwd -P)
   if [[ "$BRANCH" == feat-* && "$GIT_DIR" != "$GIT_COMMON" ]]; then
     echo "Already in feat-<slug> worktree — reusing."
   else
     git worktree add .claude/worktrees/{slug} -b feat-{slug}
     cd .claude/worktrees/{slug}
   fi
   ```
4. **Implement** the plan step by step. After each step, append `[x]` to the Progress checklist in `PLAN.md` and update `HANDOFF.md`.
5. **Verification gate** (mandatory):
   ```sh
   make lint
   make test
   make build-web      # if UI changed
   ```
   Every command MUST exit 0.
6. **Code review gate**: run `/code-review` on the diff. Resolve every Critical and High finding.
7. **Commit** in logical chunks (one commit per phase if multi-step).
8. **Push**:
   ```sh
   git push -u origin feat-{slug}
   ```
9. **Open the PR** with `gh pr create` using a HEREDOC body:
   ```sh
   gh pr create --title "{type}({context}): {summary}" --body "$(cat <<'EOF'
   ## What
   <!-- One sentence: what does this PR do? -->

   ## Why
   Implements spec: <!-- .ai/specs/{file}.md — or "N/A" if no spec -->

   ## How
   <!-- Key implementation decisions. Skip the obvious. -->

   ## Test plan
   - [x] `make lint` exits 0
   - [x] `make test` exits 0
   - [ ] Manually tested: <!-- describe the happy path you exercised -->

   ## Checklist
   - [ ] No cross-bounded-context Domain imports (`deptrac` will catch them in CI)
   - [ ] New entities use XML mapping only (no `#[ORM\*]` attributes)
   - [ ] New migration reviewed — `up()` and `down()` both correct
   - [ ] No credentials or tokens committed
   - [ ] OpenAPI-affecting change → `make gen-api` run and diff committed
   EOF
   )"
   ```
10. **Label the PR**: apply the right pipeline label (`review`) + category label (`feature` / `bug` / `refactor` / `security` / `dependencies` / `documentation`) + meta label (`needs-qa` if UI changed, `skip-qa` for docs/CI/tests-only).
11. **Report** the PR URL to the user.

## Pipeline Labels (mutually exclusive)

- `review` — ready for code review (default when opening)
- `changes-requested` — review found issues; author working
- `qa` — approved, needs manual QA
- `qa-failed` — QA found regressions
- `merge-queue` — ready to merge
- `blocked` — external blocker
- `do-not-merge` — explicitly held back

## Category Labels (additive)

`bug` · `feature` · `refactor` · `security` · `dependencies` · `documentation`

## Meta Labels (additive)

- `needs-qa` — UI changes, new features, user-facing flows
- `skip-qa` — docs / CI / tests / typo / dependency-only
- `in-progress` — auto-skill currently working on it

## Claim Discipline

When an auto-skill starts a task, it MUST:
- Assign the PR to the bot/user running the skill.
- Add the `in-progress` label.
- Post a claim comment ("auto-create-pr started on this task — will finish or hand off.")

When the skill finishes (success or failure), it MUST remove `in-progress`.

## When to Stop and Hand Off

If any of these happens, write a complete `HANDOFF.md` and stop:
- Verification gate fails after 2 reasonable fix attempts.
- The plan requires architectural decisions the user hasn't approved.
- A spec is missing and the change is non-trivial.
- More than one bounded context is touched in a way not covered by a spec.

The user (or a subsequent `auto-continue-pr` invocation) can resume from `HANDOFF.md`.

## Output

```
✅ PR opened: {URL}
   Branch: feat-{slug}
   Worktree: .claude/worktrees/{slug}
   Steps completed: {N}/{M}
   Pipeline label: review
   Category: {feature|bug|…}
   Meta: needs-qa / skip-qa
```

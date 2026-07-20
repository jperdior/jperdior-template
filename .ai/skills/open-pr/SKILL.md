---
name: open-pr
description: Open a GitHub PR for the current branch with templated body, labels, and link to spec. Triggers on "open a PR", "create the PR", "submit PR".
---

# Open PR

Open a PR for the work already on the current branch (the branch must have commits ahead of the base).

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:finishing-a-development-branch` — structured branch-completion flow: verify tests → detect environment → present 4 options (merge locally, push+PR, keep, discard). Can replace steps 1-4 of this skill.

## Workflow

1. **Check branch state**:
   ```sh
   git status
   git log --oneline origin/main..HEAD       # adjust base if not 'main'
   git diff origin/main...HEAD --stat
   ```
   If the branch has no commits ahead, stop and ask the user.
2. **Push** if not already pushed:
   ```sh
   git push -u origin $(git rev-parse --abbrev-ref HEAD)
   ```
3. **Compose the PR body** using the template below.
4. **Open the PR**:
   ```sh
   gh pr create --title "<type>(<context>): <summary>" --body "$(cat <<'EOF'
   ## What
   <!-- One sentence: what does this PR do? -->

   ## Why
   Implements spec: <!-- .ai/specs/{file}.md — or "N/A" if no spec -->

   ## How
   <!-- Key implementation decisions. Skip the obvious. -->

   ## Test plan
   - [ ] `make lint` exits 0
   - [ ] `make test` exits 0
   - [ ] Manually tested: <!-- describe the happy path you exercised -->

   ## Checklist
   - [ ] No cross-bounded-context Domain imports (`deptrac` will catch them in CI)
   - [ ] New entities use PHP attribute mapping on model classes only (no `#[ORM\*]` on domain entities)
   - [ ] New migration reviewed — `up()` and `down()` both correct
   - [ ] No credentials or tokens committed
   - [ ] OpenAPI-affecting change → `make gen-api` run and diff committed
   EOF
   )"
   ```
5. **Apply labels**: `review` + category (`feature` / `bug` / `refactor` / `security` / `dependencies` / `documentation`) + meta (`needs-qa` or `skip-qa`).
6. **Report** the PR URL.

## PR Title Convention

`<type>(<context>): <summary>` — Conventional Commits.

| Type | When |
|------|------|
| `feat` | new behaviour |
| `fix` | bug fix |
| `refactor` | code change with no behavioural change |
| `chore` | maintenance, deps, CI |
| `docs` | documentation only |
| `test` | tests only |
| `perf` | perf improvement |
| `security` | security fix |

`<context>` is the bounded context or app: `user`, `note`, `web`, `admin`, `api`, `ops`, …

## Output

```
✅ PR opened: {URL}
   Title: {…}
   Labels: review, {category}, {needs-qa|skip-qa}
   Base: main
   Head: {branch}
```

## Rules

- NEVER force-push to a shared base branch.
- Always use a HEREDOC for the body to preserve formatting.
- Always include the spec link in the body if one exists.
- Always check `git status` first — uncommitted changes mean the PR will be incomplete.

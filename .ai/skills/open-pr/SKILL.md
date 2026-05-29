---
name: open-pr
description: Open a GitHub PR for the current branch with templated body, labels, and link to spec. Triggers on "open a PR", "create the PR", "submit PR".
---

# Open PR

Open a PR for the work already on the current branch (the branch must have commits ahead of the base).

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
   ## Summary
   - bullet 1
   - bullet 2

   ## Spec
   {link to .ai/specs/... or "N/A"}

   ## Test plan
   - [ ] make lint
   - [ ] make test
   - [ ] make test-e2e (if applicable)

   ## Screenshots
   (if UI changed)
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

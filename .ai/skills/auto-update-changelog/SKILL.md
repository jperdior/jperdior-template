---
name: auto-update-changelog
description: Draft a CHANGELOG.md release entry for every PR merged since the last release tag, then ship it as a docs PR. Use at release time.
---

# Auto Update Changelog

Generate a release entry in `CHANGELOG.md` covering every PR merged since the most recent release tag.

## Workflow

1. **Find the last release tag**:
   ```sh
   git describe --tags --abbrev=0
   ```
   If no tag exists, ask the user for the starting commit.
2. **List merged PRs** since that tag:
   ```sh
   gh pr list --state merged --search "merged:>={tag-date}" \
     --json number,title,labels,author,mergedAt,body \
     --limit 200
   ```
3. **Classify each PR** by category label:
   - `feature` → ✨ Features
   - `bug` → 🐛 Fixes
   - `refactor` → ♻️ Refactor
   - `security` → 🔒 Security
   - `dependencies` → ⬆️ Dependencies
   - `documentation` → 📚 Docs
4. **Compose the release entry** (template below) and prepend it to `CHANGELOG.md`.
5. **Hand off to `/auto-create-pr`** to open a docs-only PR with `documentation` + `skip-qa` labels.

## Release Entry Template

```markdown
## [Unreleased] — {YYYY-MM-DD}

### ✨ Features
- feat(note): add list endpoint (#142) — @alice
- feat(user): add password reset flow (#150) — @bob

### 🐛 Fixes
- fix(user): refresh-token rotation on concurrent requests (#156) — @carol

### ♻️ Refactor
- refactor(api): extract NoteRepository interface (#160) — @dave

### 🔒 Security
- security(auth): bump LexikJWT to patch CVE-… (#165) — @eve

### ⬆️ Dependencies
- chore(deps): bump symfony/messenger 7.4.2 (#170) — @dependabot

### 📚 Docs
- docs(multitenancy): clarify SQLFilter activation (#175) — @julio
```

## Rules

- One entry per PR. No squashing entries across PRs.
- Use the PR title verbatim (with type prefix). If the title is unclear, escalate to the user.
- Skip PRs without a category label — flag them in the output for manual triage.
- Never modify previously-released sections. New entries go under `[Unreleased]`.
- Apply the Supersede Credit rule if a previous entry was superseded by a later PR — keep both, mark the older one with `(superseded by #N)`.

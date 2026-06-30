---
name: auto-update-changelog
description: Draft a CHANGELOG.md release entry for the version about to be tagged, covering every PR merged since the previous release tag, and ship it as a docs PR. Run BEFORE pushing the tag — release.yml extracts the entry by version and uses it as the GitHub release body. Usage - /auto-update-changelog v<version>
---

# Auto Update Changelog

`CHANGELOG.md` is the source of truth for release notes. `.github/workflows/release.yml` extracts the section matching the pushed tag and publishes it as the GitHub release body. This skill produces that section before the tag is pushed.

## Trigger

```
/auto-update-changelog v<version>
```

Always pass the explicit version (e.g. `v0.2.0`, `v1.4.3-rc1`). The version becomes the section heading and must match the git tag pushed in the next step of the release path.

## Workflow

1. **Find the last release tag**:
   ```sh
   git describe --tags --abbrev=0 2>/dev/null
   ```
   If no tag exists yet (first release), ask the user for the starting commit SHA.
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
4. **Bootstrap `CHANGELOG.md` if missing** with the header skeleton (see below).
5. **Compose the release entry** (template below) under heading `## [v<version>] — {YYYY-MM-DD}` and insert it directly after the file header, above any prior version sections.
6. **Hand off to `/auto-create-pr`** to open a docs-only PR with `documentation` + `skip-qa` labels.
7. After the docs PR merges, the user tags `main` (`git tag v<version> && git push origin v<version>`) and `release.yml` picks up the entry.

## File header (for bootstrap)

```markdown
# Changelog

All notable changes to jperdior-template. The entry for each tagged version is consumed by `.github/workflows/release.yml` to populate the GitHub release body — see Workflow Orchestration → Release path in `AGENTS.md`.

Run `/auto-update-changelog v<version>` to draft the next entry before tagging.
```

## Release Entry Template

```markdown
## [v0.1.0] — 2026-06-30

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
- docs(api): clarify CQRS bus wiring (#175) — @julio
```

## Rules

- The version in the section heading MUST match the git tag pushed afterwards. Mismatch breaks `release.yml`'s extraction.
- One entry per PR. No squashing entries across PRs.
- Use the PR title verbatim (with type prefix). If the title is unclear, escalate to the user.
- Skip PRs without a category label — flag them in the output for manual triage.
- Never modify released sections. New entries go above all prior version sections.
- Apply the Supersede Credit rule if a previous entry was superseded by a later PR — keep both, mark the older one with `(superseded by #N)`.

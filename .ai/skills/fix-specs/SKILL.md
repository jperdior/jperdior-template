---
name: fix-specs
description: Normalise spec filenames under .ai/specs/ to the {YYYY-MM-DD}-{slug}.md convention. Triggers on "fix specs", "normalise spec filenames", "clean up SPEC-* names".
---

# Fix Specs

Normalise legacy spec filenames so they all match `{YYYY-MM-DD}-{kebab-case-title}.md`. Update internal cross-references after renames.

## Workflow

1. **Survey**:
   ```sh
   find .ai/specs -maxdepth 2 -name '*.md' -print
   ```
2. **Identify non-conforming filenames** (anything not matching `^\d{4}-\d{2}-\d{2}-[a-z0-9-]+\.md$`).
3. **For each non-conforming file**:
   - Derive the date from `git log --diff-filter=A --follow --format=%aI -1 -- <file>` (first commit that added it).
   - Derive the slug from the file's H1 heading, lowercased and kebab-cased.
   - Check for collisions with an existing `{date}-{slug}.md` file. If a collision exists, append a disambiguator (`{date}-{slug}-v2.md`) and flag for review.
4. **Rename with `git mv`** (preserves history):
   ```sh
   git mv .ai/specs/SPEC-123-something.md .ai/specs/2026-06-04-something-meaningful.md
   ```
5. **Update internal references**: grep the repo for the old filename, update every occurrence.
6. **Report**:
   ```
   Renamed: {old} → {new}
   References updated: {N} files
   Collisions flagged: {list}
   ```

## Rules

- Use `git mv`, never plain `mv` + `git add`.
- Never delete a spec file without explicit user confirmation.
- If a spec has been moved to `implemented/`, normalise it in place; don't move it back to the root.
- Disambiguate colliding slugs with a `-v2` / `-followup` suffix; never overwrite.

## Validation

```sh
# Verify all spec filenames match the convention:
find .ai/specs -name '*.md' | grep -v -E '\.ai/specs(/implemented)?/[0-9]{4}-[0-9]{2}-[0-9]{2}-[a-z0-9-]+\.md$' || echo "All clean"
```

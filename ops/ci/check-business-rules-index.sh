#!/usr/bin/env bash
# Business rules and lessons: format, filing, and citation integrity.
#
# WHY THIS EXISTS. A rules registry that nothing measures grows into an essay per rule. Once it
# does, a session that needs one rule has no way to read only that rule: it reads the whole file,
# and pays for every rule in the repository to learn one. The registry this template ships with is
# small; this check is what keeps it that way, from the first rule onwards, instead of after the
# file has become unreadable and a migration is needed to fix it.
#
# WHAT A RULE IS. One `- **BR-XXnn** — <statement>` line, optionally followed by exactly one
# `  **Why:** <reason>` line. Neither wraps, and neither exceeds MAX_LINE characters. The enforcement
# point is NOT written down: the id itself is the link, and `grep -rn BR-XXnn apps/api` is the query
# that finds it. Whatever narrative a rule needs beyond its why belongs in the spec that introduced
# it, under `.ai/specs/implemented/`, and in `git log -S`.
#
# WHERE A RULE LIVES. With the bounded context that owns it, in
# `apps/api/src/<Context>/business-rules.md`, next to that context's AGENTS.md. The root
# `.ai/business-rules.md` holds the prefix table, the retired ids, and only those rules that every
# context needs.
#
# WHAT THIS DELIBERATELY DOES NOT CHECK. There is no frozen inventory of ids. A repository's rule
# set grows, so pinning it would be a merge conflict on every new rule. Uniqueness (check 2) and
# citation integrity (check 4) are what remain, and together they catch a rule that is duplicated,
# misfiled, or cited after being deleted.
#
# PORTABILITY. Runs on the HOST -- in CI as `bash ops/ci/check-business-rules-index.sh`, locally
# through `make lint`. POSIX text tooling only: `grep -E` and `awk`, never `grep -P`, `sed -i`,
# `mapfile` or `readarray`. It must pass under macOS's BSD grep as well as the Linux runner, and a
# GNU-ism would be green in CI and broken on a laptop.
#
# Files come from `git ls-files`, never a recursive walk: `.claude/worktrees/` is gitignored but
# physically inside the repository, so `grep -r .` counts every id once per worktree that exists.

set -euo pipefail

ROOT_FILE=".ai/business-rules.md"
LESSONS_FILE=".ai/lessons.md"
MAX_LINE=300

# A citation in one of these is not evidence that a rule should still exist: a merged migration is
# immutable (it has already run on deployed databases), an archived spec is frozen history, and
# `BR-XX` is the placeholder the docs use when naming the convention itself.
EXCLUDED_FROM_CITATIONS='^(apps/api/migrations/|\.ai/specs/)'
PLACEHOLDER='BR-XX'

cd "$(git rev-parse --show-toplevel)"

fail=0
note() { printf '  %s\n' "$*" >&2; }
problem() { printf '\nFAIL: %s\n' "$*" >&2; fail=1; }

[ -f "$ROOT_FILE" ] || { printf 'FAIL: %s does not exist\n' "$ROOT_FILE" >&2; exit 1; }

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# `--others --exclude-standard` as well as the tracked set: a rule file added in the working tree is
# not yet tracked, and enumerating only `--cached` would skip it silently -- the check would pass
# while every rule in the new file went unread. Ignored paths stay excluded, so `.claude/worktrees/`
# (gitignored, but physically inside the repo) is still never descended into.
list_files() { git ls-files --cached --others --exclude-standard "$@"; }

# The rule files: the root registry plus one per bounded context that has declared any.
list_files -- "$ROOT_FILE" 'apps/api/src/*/business-rules.md' | sort -u > "$tmp/rule_files"

# Markdown carries worked examples of the very syntax this script parses, so a fenced block is
# never content: a ```markdown sample of a rule bullet is documentation, not a declaration, and an
# id named in one is not a citation. Every scan below reads through this.
strip_fences() { awk '/^```/ { fence = !fence; next } !fence' "$1"; }

# ---------------------------------------------------------------------------
# 1. Format: one statement line, at most one why line, neither over MAX_LINE.
# ---------------------------------------------------------------------------
while IFS= read -r f; do
  awk -v file="$f" -v max="$MAX_LINE" '
    # `in_rule` tracks being INSIDE a rule block, not merely awaiting its why. Tracking only the
    # latter lets a third line pass whenever a why is present, which is the exact shape the essay
    # grows back in: statement, why, and then a paragraph.
    /^```/ { fence = !fence; in_rule = 0; next }
    fence { next }
    /^- \*\*BR-[A-Z]+[0-9]+\*\* — / {
      if (length($0) > max) printf "%s:%d: rule line is %d chars (max %d)\n", file, NR, length($0), max
      in_rule = 1; has_why = 0
      next
    }
    /^- \*\*BR-/ {
      printf "%s:%d: malformed rule bullet (want `- **BR-XXnn** — statement`)\n", file, NR
      in_rule = 0
      next
    }
    /^[[:space:]]+\*\*Why:\*\* / {
      if (!in_rule) printf "%s:%d: **Why:** line does not follow a rule\n", file, NR
      else if (has_why) printf "%s:%d: a rule may carry only one **Why:** line\n", file, NR
      if (length($0) > max) printf "%s:%d: why line is %d chars (max %d)\n", file, NR, length($0), max
      has_why = 1
      next
    }
    /^[[:space:]]*$/ { in_rule = 0; next }
    /^#/ { in_rule = 0; next }
    {
      if (in_rule) printf "%s:%d: a rule may carry only a statement and one **Why:** line\n", file, NR
      in_rule = 0
    }
  ' "$f" >> "$tmp/format_errors"
done < "$tmp/rule_files"

if [ -s "$tmp/format_errors" ]; then
  problem "a rule is not a statement line plus at most one **Why:** line"
  while IFS= read -r line; do note "$line"; done < "$tmp/format_errors"
fi

# ---------------------------------------------------------------------------
# 2. Every id is declared exactly once, across every rule file.
# ---------------------------------------------------------------------------
while IFS= read -r f; do
  # `|| true` because a rule file that declares nothing is normal, not a failure: the root registry
  # holds no rules at all once every context owns its own, and under `pipefail` an empty grep would
  # abort the whole check with no output.
  strip_fences "$f" \
    | grep -oE '^- \*\*BR-[A-Z]+[0-9]+\*\*' \
    | grep -oE 'BR-[A-Z]+[0-9]+' \
    | while IFS= read -r id; do printf '%s\t%s\n' "$id" "$f"; done || true
done < "$tmp/rule_files" | sort > "$tmp/declared_with_file"

cut -f1 "$tmp/declared_with_file" > "$tmp/declared"
sort -u "$tmp/declared" > "$tmp/declared_unique"

if [ "$(wc -l < "$tmp/declared")" -ne "$(wc -l < "$tmp/declared_unique")" ]; then
  problem "a rule id is declared more than once"
  uniq -d "$tmp/declared" | while IFS= read -r id; do
    note "$id in: $(grep -E "^$id	" "$tmp/declared_with_file" | cut -f2 | tr '\n' ' ')"
  done
fi

# ---------------------------------------------------------------------------
# 3. Filing: every prefix-table row resolves, and every id sits in the file its prefix names.
#
# The alpha run of an id IS its prefix, so a two-letter prefix and the one-letter prefix it starts
# with never collide and the lookup needs no longest-match rule. (No literal ids in this file's
# comments: it scans itself, and an example would read as a citation of a rule that does not exist.)
# ---------------------------------------------------------------------------
# shellcheck disable=SC2016  # the backticks are markdown literals in the table rows, not substitution
strip_fences "$ROOT_FILE" | grep -E '^\| `BR-[A-Z]+` ' \
  | awk -F'|' '{ gsub(/[ `]/, "", $2); gsub(/^[ \t]+|[ \t]+$/, "", $4); print $2 "\t" $4 }' \
  | sed 's/`//g' > "$tmp/prefix_table" || true

if [ ! -s "$tmp/prefix_table" ]; then
  problem "$ROOT_FILE declares no prefix table (rows like: | \`BR-U\` | 3 | \`apps/api/src/User/business-rules.md\` |)"
fi

while IFS="$(printf '\t')" read -r prefix target; do
  [ -n "$prefix" ] || continue
  case "$target" in
    "this file") continue ;;
  esac
  [ -f "$target" ] || problem "prefix table row \`$prefix\` names $target, which does not exist"
done < "$tmp/prefix_table"

while IFS="$(printf '\t')" read -r id file; do
  prefix="$(printf '%s' "$id" | grep -oE '^BR-[A-Z]+')"
  target="$(awk -F'\t' -v p="$prefix" '$1 == p { print $2 }' "$tmp/prefix_table")"
  if [ -z "$target" ]; then
    problem "$id is declared in $file but its prefix \`$prefix\` has no row in the prefix table"
    continue
  fi
  [ "$target" = "this file" ] && target="$ROOT_FILE"
  [ "$file" = "$target" ] || problem "$id is declared in $file but its prefix \`$prefix\` names $target"
done < "$tmp/declared_with_file"

# ---------------------------------------------------------------------------
# 4. Every cited id resolves: declared, or listed as retired.
# ---------------------------------------------------------------------------
# Only the FIRST column of a retired-ids table row. Prose in that section may name an id while
# explaining the section, and the second column names a LIVE id, so scanning the whole section
# would quietly retire both.
awk -F'|' '
  /^## Retired ids/ { inside = 1; next }
  /^## / { inside = 0 }
  inside && /^\| `BR-[A-Z]+[0-9]+`/ { gsub(/[ `]/, "", $2); print $2 }
' <(strip_fences "$ROOT_FILE") | sort -u > "$tmp/retired" || true

list_files | grep -vE "$EXCLUDED_FROM_CITATIONS" > "$tmp/citation_files"

# Read each file once, through strip_fences for markdown. A fenced example of the rule syntax is
# documentation, not a citation -- without this the registry's own worked example is a dangling id.
scan_citations() {
  while IFS= read -r f; do
    [ -f "$f" ] || continue
    case "$f" in
      *.md) strip_fences "$f" ;;
      *)    cat "$f" ;;
    esac
  done < "$tmp/citation_files"
}

scan_citations | grep -oE 'BR-[A-Z]+[0-9]+' | grep -v "^$PLACEHOLDER" | sort -u > "$tmp/cited" || true

sort -u "$tmp/declared_unique" "$tmp/retired" > "$tmp/resolvable"
comm -23 "$tmp/cited" "$tmp/resolvable" > "$tmp/dangling" || true

if [ -s "$tmp/dangling" ]; then
  problem "a rule id is cited but resolves to no rule (declare it, or list it under '## Retired ids')"
  while IFS= read -r id; do
    where=""
    while IFS= read -r f; do
      [ -f "$f" ] || continue
      case "$f" in
        *.md) strip_fences "$f" ;;
        *)    cat "$f" ;;
      esac | grep -q "$id" && where="$where $f"
    done < "$tmp/citation_files"
    note "$id — cited in:$where"
  done < "$tmp/dangling"
fi

# ---------------------------------------------------------------------------
# 5. Lesson ids are unique.
# ---------------------------------------------------------------------------
lessons_total=0
if [ -f "$LESSONS_FILE" ]; then
  grep -oE '^## L-[0-9]+' "$LESSONS_FILE" | grep -oE 'L-[0-9]+' | sort > "$tmp/lessons" || true
  lessons_total="$(wc -l < "$tmp/lessons" | tr -d ' ')"
  if [ "$lessons_total" -ne "$(sort -u "$tmp/lessons" | wc -l | tr -d ' ')" ]; then
    problem "a lesson id appears more than once in $LESSONS_FILE"
    uniq -d "$tmp/lessons" | while IFS= read -r id; do note "$id"; done
  fi
fi

# ---------------------------------------------------------------------------
# Census. Printed on success as well as failure: the numbers are the progress report.
# ---------------------------------------------------------------------------
printf 'Business-rule and lesson index:\n'
printf '  rule files (root + per-context)   %s\n' "$(wc -l < "$tmp/rule_files" | tr -d ' ')"
printf '  rules declared                    %s\n' "$(wc -l < "$tmp/declared_unique" | tr -d ' ')"
printf '  retired ids                       %s\n' "$(wc -l < "$tmp/retired" | tr -d ' ')"
printf '  distinct ids cited in the tree     %s\n' "$(wc -l < "$tmp/cited" | tr -d ' ')"
printf '  lessons                           %s\n' "$lessons_total"

if [ "$fail" -ne 0 ]; then
  # shellcheck disable=SC2016  # backticks quote the markdown shape being described, not a command
  printf '\nRules: one `- **BR-XXnn** — statement` line, at most one `  **Why:**` line, each under %s chars,\n' "$MAX_LINE"
  printf 'filed in the file the prefix table names. See the header of this script.\n'
  exit 1
fi

printf '\nOK — every rule is one line plus a why, filed with its context, and every citation resolves.\n'

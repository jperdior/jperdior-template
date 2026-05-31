#!/usr/bin/env sh
# Symlink skills from .ai/skills/<name>/ into the AI-agent skills directory.
#
# Requires: sh, python3 (pre-installed on macOS and most Linux distros)
#
# Usage (interactive — prompts for agent if --target not supplied):
#   sh scripts/install-skills.sh
#
# Non-interactive (CI / make init):
#   sh scripts/install-skills.sh --target claude     # Claude Code  → .claude/skills/
#   sh scripts/install-skills.sh --target cursor     # Cursor       → .cursor/rules/
#   sh scripts/install-skills.sh --target codex      # Codex        → .codex/skills/
#   sh scripts/install-skills.sh --target all        # all three
#
# Tier flags (can combine with --target):
#   sh scripts/install-skills.sh --all               # every tier
#   sh scripts/install-skills.sh --with automation   # default + automation tier
#   sh scripts/install-skills.sh --tiers core,security
#   sh scripts/install-skills.sh --clean             # remove all symlinks for chosen agent(s)
#   sh scripts/install-skills.sh --list              # show tiers and their skills

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SKILLS_SRC="$REPO_ROOT/.ai/skills"
TIERS_JSON="$SKILLS_SRC/tiers.json"

# Agent → install directory mapping
dir_for_agent() {
    case "$1" in
        claude) echo ".claude/skills" ;;
        cursor) echo ".cursor/rules"  ;;
        codex)  echo ".codex/skills"  ;;
        *) echo "Unknown agent: $1" >&2; exit 1 ;;
    esac
}

# --- argument parsing ---
MODE="install"
TIERS_ARG=""
WITH_ARG=""
TARGET=""

while [ $# -gt 0 ]; do
    case "$1" in
        --all)    MODE="all";   shift ;;
        --clean)  MODE="clean"; shift ;;
        --list)   MODE="list";  shift ;;
        --tiers)  TIERS_ARG="$2"; shift 2 ;;
        --with)   WITH_ARG="$2";  shift 2 ;;
        --target) TARGET="$2";    shift 2 ;;
        *) echo "Unknown flag: $1" >&2; exit 1 ;;
    esac
done

# --- interactive agent selection ---
if [ -z "$TARGET" ]; then
    printf "Which AI agent are you using?\n"
    printf "  1) Claude Code  (.claude/skills/)\n"
    printf "  2) Cursor       (.cursor/rules/)\n"
    printf "  3) Codex        (.codex/skills/)\n"
    printf "  4) All of the above\n"
    printf "Enter choice [1-4]: "
    read -r choice
    case "$choice" in
        1) TARGET="claude" ;;
        2) TARGET="cursor" ;;
        3) TARGET="codex"  ;;
        4) TARGET="all"    ;;
        *) echo "Invalid choice." >&2; exit 1 ;;
    esac
fi

# Expand "all" into individual agent list
if [ "$TARGET" = "all" ]; then
    AGENTS="claude cursor codex"
else
    AGENTS="$TARGET"
fi

# --- helpers ---
purge_dir() {
    dir="$1"
    [ -d "$dir" ] || return 0
    for entry in "$dir"/*; do
        [ -e "$entry" ] || [ -L "$entry" ] || continue
        rm -rf "$entry"
    done
}

# --- list mode ---
if [ "$MODE" = "list" ]; then
    python3 - "$TIERS_JSON" <<'PY'
import json, sys
with open(sys.argv[1]) as f:
    d = json.load(f)
print("Tiers:")
for name, tier in d["tiers"].items():
    marker = " (default)" if name in d["default"] else ""
    print(f"  {name}{marker} — {tier['description']}")
    for s in tier["skills"]:
        print(f"    • {s}")
PY
    exit 0
fi

# --- clean mode ---
if [ "$MODE" = "clean" ]; then
    for agent in $AGENTS; do
        rel=$(dir_for_agent "$agent")
        purge_dir "$REPO_ROOT/$rel"
        echo "Cleaned $rel"
    done
    exit 0
fi

# --- resolve skill list via python3 ---
SKILLS=$(python3 - "$TIERS_JSON" "$MODE" "$TIERS_ARG" "$WITH_ARG" <<'PY'
import json, sys

tiers_file, mode, tiers_arg, with_arg = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]

with open(tiers_file) as f:
    d = json.load(f)

if mode == "all":
    tier_names = list(d["tiers"].keys())
elif tiers_arg:
    tier_names = [t.strip() for t in tiers_arg.split(",") if t.strip()]
else:
    tier_names = list(d["default"])
    if with_arg:
        tier_names.extend(t.strip() for t in with_arg.split(",") if t.strip())

seen = set()
skills = []
for t in tier_names:
    if t not in d["tiers"]:
        print(f"Unknown tier: {t}", file=sys.stderr)
        sys.exit(2)
    for s in d["tiers"][t]["skills"]:
        if s not in seen:
            seen.add(s)
            skills.append(s)

print("\n".join(skills))
PY
)

# --- install ---
missing=""

for agent in $AGENTS; do
    rel=$(dir_for_agent "$agent")
    target_dir="$REPO_ROOT/$rel"
    purge_dir "$target_dir"
    mkdir -p "$target_dir"

    echo "$SKILLS" | while IFS= read -r skill; do
        src="$SKILLS_SRC/$skill"
        dst="$target_dir/$skill"
        if [ ! -d "$src" ]; then
            continue
        fi
        rel_src=$(python3 -c "import os; print(os.path.relpath('$src', '$target_dir'))")
        ln -s "$rel_src" "$dst"
    done
done

skill_count=$(echo "$SKILLS" | grep -c .)
echo "Installed $skill_count skill(s) into:"
for agent in $AGENTS; do
    rel=$(dir_for_agent "$agent")
    echo "  • $rel"
done

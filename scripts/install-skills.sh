#!/usr/bin/env sh
# Symlink skills from .ai/skills/<name>/ into the AI-agent skills directory.
#
# Requires: sh, awk, grep, sed — all pre-installed on every macOS and Linux system.
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
#   --all               every tier
#   --with automation   default + automation tier
#   --tiers core,security
#   --clean             remove all symlinks for chosen agent(s)
#   --list              show tiers and their skills

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SKILLS_SRC="$REPO_ROOT/.ai/skills"
TIERS_JSON="$SKILLS_SRC/tiers.json"

# =============================================================================
# Helper functions (must be defined before use)
# =============================================================================

# Agent → install directory (all targets are 2 levels deep from repo root)
dir_for_agent() {
    case "$1" in
        claude) echo ".claude/skills" ;;
        cursor) echo ".cursor/rules"  ;;
        codex)  echo ".codex/skills"  ;;
        *) echo "Unknown agent: $1" >&2; exit 1 ;;
    esac
}

# Print all defined tier names
all_tiers() {
    grep -E '^    "[a-z][-a-z]*": \{' "$TIERS_JSON" \
        | sed 's/.*"\([a-z][a-z-]*\)".*/\1/'
}

# Print the default tier names (handles inline array "default": ["core"])
default_tiers() {
    grep '"default"' "$TIERS_JSON" \
        | sed 's/.*\[//; s/\].*//; s/"//g; s/,/ /g' \
        | tr ' ' '\n' \
        | grep -v '^[[:space:]]*$'
}

# Print skill names for a given tier (one skill per line).
# Skills are indented 8 spaces in tiers.json; "skills": [ uses 6 spaces — that
# difference lets us avoid matching the key line itself.
tier_skills() {
    awk -v tier="$1" '
        /^    "[a-z][-a-z]*": \{/ {
            s = $0
            gsub(/^[[:space:]]*"/, "", s)
            gsub(/"[[:space:]]*:.*$/, "", s)
            in_tier = (s == tier)
            in_skills = 0; depth = 0
        }
        in_tier && /"skills"/ { in_skills = 1; depth = 0 }
        in_skills && /\[/     { depth++ }
        in_skills && /\]/ {
            depth--
            if (depth <= 0) { in_skills = 0; in_tier = 0 }
        }
        in_skills && depth > 0 && /^        "[a-z]/ {
            s = $0
            gsub(/^[[:space:]]*"/, "", s)
            gsub(/"[[:space:]]*,?[[:space:]]*$/, "", s)
            print s
        }
    ' "$TIERS_JSON"
}

# Build deduplicated skill list for selected tiers
resolve_skills() {
    if [ "$MODE" = "all" ]; then
        tier_list="$(all_tiers)"
    elif [ -n "$TIERS_ARG" ]; then
        tier_list="$(echo "$TIERS_ARG" | tr ',' '\n')"
    else
        tier_list="$(default_tiers)"
        if [ -n "$WITH_ARG" ]; then
            tier_list="$(printf '%s\n%s' "$tier_list" "$(echo "$WITH_ARG" | tr ',' '\n')")"
        fi
    fi

    known="$(all_tiers)"
    (echo "$tier_list" | while IFS= read -r t; do
        t="$(echo "$t" | tr -d '[:space:]')"
        [ -z "$t" ] && continue
        if ! echo "$known" | grep -qx "$t"; then
            echo "Unknown tier: $t" >&2; exit 2
        fi
        tier_skills "$t"
    done) | awk '!seen[$0]++'
}

purge_dir() {
    dir="$1"
    [ -d "$dir" ] || return 0
    for entry in "$dir"/*; do
        [ -e "$entry" ] || [ -L "$entry" ] || continue
        rm -rf "$entry"
    done
}

# =============================================================================
# Argument parsing
# =============================================================================

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

# =============================================================================
# List mode — no agent selection needed
# =============================================================================

if [ "$MODE" = "list" ]; then
    defaults="$(default_tiers)"
    all_tiers | while IFS= read -r tier; do
        if echo "$defaults" | grep -qx "$tier"; then
            marker=" (default)"
        else
            marker=""
        fi
        desc="$(grep -A1 "\"$tier\":" "$TIERS_JSON" \
            | grep '"description"' \
            | sed 's/.*"description":[[:space:]]*"//; s/"[,]*[[:space:]]*$//')"
        echo "  $tier$marker — $desc"
        tier_skills "$tier" | while IFS= read -r s; do echo "    • $s"; done
    done
    exit 0
fi

# =============================================================================
# Agent selection (interactive if --target not supplied)
# =============================================================================

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

if [ "$TARGET" = "all" ]; then
    AGENTS="claude cursor codex"
else
    AGENTS="$TARGET"
fi

# =============================================================================
# Clean mode
# =============================================================================

if [ "$MODE" = "clean" ]; then
    for agent in $AGENTS; do
        rel="$(dir_for_agent "$agent")"
        purge_dir "$REPO_ROOT/$rel"
        echo "Cleaned $rel"
    done
    exit 0
fi

# =============================================================================
# Install
# =============================================================================

SKILLS="$(resolve_skills)"

for agent in $AGENTS; do
    rel="$(dir_for_agent "$agent")"
    target_dir="$REPO_ROOT/$rel"
    purge_dir "$target_dir"
    mkdir -p "$target_dir"

    echo "$SKILLS" | while IFS= read -r skill; do
        [ -z "$skill" ] && continue
        src="$SKILLS_SRC/$skill"
        dst="$target_dir/$skill"
        if [ ! -d "$src" ]; then
            echo "Warning: skill folder not found: $skill" >&2
            continue
        fi
        # All agent dirs are 2 levels deep (e.g. .claude/skills/) so the
        # relative path back to .ai/skills/ is always ../../.ai/skills/<skill>
        ln -s "../../.ai/skills/$skill" "$dst"
    done
done

skill_count="$(echo "$SKILLS" | grep -c .)"
echo "Installed $skill_count skill(s) into:"
for agent in $AGENTS; do
    echo "  • $(dir_for_agent "$agent")"
done

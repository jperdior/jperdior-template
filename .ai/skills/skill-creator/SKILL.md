---
name: skill-creator
description: Scaffold a new skill folder with SKILL.md frontmatter, optional references/, and tiers.json registration. Triggers on "create a skill", "new skill", "scaffold skill".
---

# Skill Creator

Generate a new skill folder under `.ai/skills/<name>/` with a working `SKILL.md` and (optionally) `references/` and `scripts/`.

## Workflow

1. **Ask for the skill's purpose** in one sentence, plus 3-5 trigger phrases (the words a user might type or that should auto-select the skill).
2. **Pick a `name`**: kebab-case, present-tense verb or noun-phrase (`scaffold-bounded-context`, `code-review`).
3. **Pick the tier**: `core`, `automation`, `security`. Default to `core` if it's daily-driver.
4. **Write the frontmatter**:
   ```markdown
   ---
   name: <kebab-case-name>
   description: <one-sentence purpose>. Triggers on "<phrase 1>", "<phrase 2>", "<phrase 3>".
   ---
   ```
5. **Write the body** with these standard sections:
   - **Workflow** (numbered steps)
   - **Output** (what the skill produces, format)
   - **Rules** / **Never** (anti-patterns)
   - **Validation** (commands to verify the skill worked)
6. **Register in `tiers.json`**: add the skill name to the relevant tier's `skills` array.
7. **Run `pnpm install-skills`** to create the symlink under `.claude/skills/`.
8. **Verify** by invoking `/skill-name` in Claude Code.

## SKILL.md Layout Template

```markdown
---
name: <name>
description: <purpose>. Triggers on "<phrase 1>", "<phrase 2>".
---

# <Title>

<One-paragraph what this skill does.>

## Workflow

1. ...
2. ...

## Output

<What does the skill emit? Format?>

## Rules

- ...

## Validation

```sh
<commands to verify>
```
```

## Sizing

| Size | Lines | When to split |
|------|-------|---------------|
| Small | < 100 | Single-purpose conventions |
| Medium | 100-300 | Component libraries, API patterns |
| Large | 300+ | Split into `references/` files; keep SKILL.md the entry point |

## Description Writing

The `description` field drives auto-selection. Include:

- **Trigger phrases**: "when building X", "when reviewing Y", "scaffold Z"
- **Domain terms**: the exact words a user types
- **Outcomes**: "produces a PR", "scaffolds a bounded context"

## Never

- Never invent a skill that duplicates an existing one — check `.ai/skills/` first.
- Never put executable code in `SKILL.md`; put it in `scripts/` and reference it.
- Never skip the `tiers.json` registration step.

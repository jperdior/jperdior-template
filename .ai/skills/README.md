# Agent Skills

Skills extend AI agents (Claude Code, Cursor, Codex) with task-specific capabilities. Each skill is a folder with a `SKILL.md` (YAML frontmatter + markdown body), optionally with `references/` and `scripts/`.

## Structure

```
.ai/skills/
├── README.md
├── tiers.json              # tier manifest (single source of truth)
├── tiers.schema.json       # JSON Schema for tiers.json
└── <skill>/                # one folder per skill
    ├── SKILL.md
    ├── references/         # optional, loaded on demand
    └── scripts/            # optional executables
```

## SKILL.md Frontmatter

```markdown
---
name: skill-name
description: When to use it. Include trigger words and domain terms so auto-selection works.
---
```

## Tiers

| Tier | Default? | What's inside |
|------|----------|---------------|
| `core` | yes | Spec lifecycle, code review, scaffolders. Installed by default. |
| `automation` | opt-in | PR/issue automation. Run `pnpm install-skills --with automation`. |
| `security` | opt-in | OWASP/attack-vector audits. Run `pnpm install-skills --with security`. |

## Installation

Skills are symlinked into `.claude/skills/` and `.codex/skills/` by `pnpm install-skills`. The install script (see `scripts/install-skills.mjs`) reads `tiers.json` and creates one symlink per selected skill.

```sh
pnpm install-skills                              # core only
pnpm install-skills --with automation            # core + automation
pnpm install-skills --with automation,security   # multiple tiers
pnpm install-skills --tiers core,security        # explicit set
pnpm install-skills --all                        # every tier
pnpm install-skills --clean                      # remove all symlinks
```

## Using Skills

| Agent | Invoke | List |
|-------|--------|------|
| Claude Code | `/skill-name` | `/skills` |
| Codex | `$skill-name` | `/skills` |

Skills also trigger automatically when a task matches the skill's `description`.

## Available Skills (core)

| Skill | When to use |
|-------|-------------|
| `spec-writing` | Drafting a new spec under `.ai/specs/`. Includes the Open-Questions gate and phased breakdown. |
| `pre-implement-spec` | Auditing a spec before implementing it — gaps, risks, contract impact. |
| `implement-spec` | Executing an approved spec phase by phase with subagents and the code-review gate. |
| `code-review` | Reviewing a PR/diff/commit against architecture, security, naming, and quality rules. Runs the CI gate. |
| `integration-tests` | Running and creating PHPUnit functional tests (API) and Vitest + RTL tests (apps/web, apps/admin) from a spec or description. |
| `check-and-commit` | Run `make lint && make test && make build-web`, fix obvious issues, commit and push. |
| `fix-specs` | Normalise spec filenames to `{YYYY-MM-DD}-{slug}.md`. |
| `skill-creator` | Scaffold a new skill folder + SKILL.md template. |
| `create-agents-md` | Generate an AGENTS.md for a new package/app/context. |
| `scaffold-bounded-context` | Generate `apps/api/src/<Context>/{Domain,Application,Infrastructure,Presentation}` skeleton. |
| `add-command` | Add a CQRS write command + handler + functional test scaffold. |
| `add-query` | Add a CQRS query + handler + response DTO + functional test scaffold. |
| `add-route` | Add an invokable controller + Request DTO + Response DTO + Nelmio annotation + functional test. |
| `add-event-subscriber` | Add a domain-event subscriber so one context reacts to another's event; the producer's event is imported directly, and it scaffolds the consumer use case, subscriber, and functional test. |
| `scaffold-doctrine-migration` | Wrap `php bin/console doctrine:migrations:diff` with naming + snapshot review. |
| `scaffold-nextjs-page` | App Router page with `loading.tsx` + `error.tsx` + Server Action stub. |
| `scaffold-shadcn-form` | React-hook-form + zod schema + shadcn `Form` primitives. |
| `regenerate-api-client` | Run OpenAPI gen against the running API and refresh `packages/api-client-ts`. |
| `new-feature` | Create a `feat-<slug>` git worktree + branch from an up-to-date `main`. Called once per feature. |
| `sync-context-docs` | Update bounded-context `AGENTS.md` files after implementation. Run per-phase before opening a PR. |
| `parallel-research` | Spawn multiple Explore agents in parallel to map unfamiliar code before touching it. |
| `run-gates` | Run the CI verification gate — dispatch each in-scope gate as a parallel subagent, scoped to the diff. |
| `lint-php` | Run PHPStan + cs-fixer + deptrac in isolation (no test stack). |
| `lint-js` | Run tsc + ESLint across apps/web, apps/admin, packages in isolation. |
| `ui-design` | Decide which UI layer to use (`@jperdior/ui-react` → Tailwind → semantic HTML); how to add a Radix component. |
| `translate-strings` | Extract user-facing `apps/web` strings into the next-intl catalogs (`en`/`es`) and keep key-parity green. |
| `init` | Bootstrap a fresh clone — prerequisites, `.env.local`, project personalization. |
| `customize-project` | Rename template placeholders and add project context. Run once, at project start. |

## Available Skills (automation)

| Skill | When to use |
|-------|-------------|
| `auto-create-pr` | Run an autonomous task end-to-end and ship it as a PR. Drafts a plan in `.ai/runs/`, commits on a fresh worktree, runs the verification gate, applies labels. |
| `auto-continue-pr` | Resume an `auto-create-pr` run from the first unchecked step. |
| `auto-review-pr` | Review a PR in an isolated worktree using the `code-review` skill. Approve / request-changes + label transitions. |
| `merge-buddy` | Scan open PRs and classify merge readiness (label / review / CI / mergeable). |
| `root-cause` | Drill from a failing test or production error to the offending change. |
| `fix` | Implement the minimal fix for a known root cause; runs `code-review` + tests. |
| `open-pr` | Open a PR for the current branch with templated body + labels. |
| `verify-in-repo` | Confirm a change is actually present in the working tree (paranoia gate before claiming "done"). |
| `sync-merged-pr-issues` | Auto-close issues fixed by merged PRs; comment on closed-without-merge PRs. |
| `auto-update-changelog` | Draft a CHANGELOG entry for every PR merged since the last release. |

## Creating a New Skill

1. Run `/skill-creator` (or create manually below).
2. Add `.ai/skills/<name>/SKILL.md` with frontmatter + instructions.
3. Add `references/` files for content over ~300 lines.
4. Add the skill to `tiers.json` under the right tier.
5. Run `pnpm install-skills` to symlink it.

## Skill vs Global Instruction

| File | Scope | Active |
|------|-------|--------|
| `CLAUDE.md` / `AGENTS.md` | Global project rules | Always |
| `apps/*/AGENTS.md`, `packages/*/AGENTS.md` | Local rules | When working in that area |
| Skills | Task-specific | On demand or by auto-selection |

Use skills when guidance is task-specific, substantial (> 50 lines), or benefits from explicit invocation.

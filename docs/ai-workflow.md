# AI Workflow

The `.ai/` directory is the AI engineering harness, ported from [open-mercato](https://github.com/open-mercato/open-mercato). It defines a spec-first, skill-driven development workflow designed for use with Claude Code, Cursor or any other agent of preference.

The goal: an AI agent that behaves like a staff engineer who knows the codebase conventions, not a generic code generator that needs correcting on every PR.

---

## What's included

```
.ai/
├── specs/          Feature specifications (pending → implemented/)
├── skills/         30+ reusable playbooks invoked as slash commands
├── qa/             Integration test harness guidance
├── lessons.md      Institutional memory — mistakes not to repeat
└── ds-rules.md     Design system rules for frontend work
```

### Specs (`.ai/specs/`)

A spec is a structured document written **before** implementation. It covers:
- The problem and user stories
- Proposed solution and architecture decisions
- Data model and API contract changes
- Phasing and acceptance criteria
- Open questions that must be resolved before coding starts
- Risks and rollback plan

Specs are flat files named `.ai/specs/{YYYY-MM-DD}-{slug}.md` (e.g. `2026-06-06-add-notes.md`). The AI reads the spec before writing a line of code, which means implementation stays aligned with the agreed design rather than drifting as the codebase evolves.

The spec format is enforced by `/spec-writing`. It's not optional ceremony — it's how you catch "we'll need a migration for that" before someone writes 300 lines of code against the wrong schema.

### Skills (`.ai/skills/`)

A skill is a playbook: a markdown file with step-by-step instructions the AI follows to complete a specific type of task. Skills are invoked as slash commands (`/scaffold-bounded-context`, `/add-command`, etc.). They reference the codebase conventions, point to relevant `AGENTS.md` sections, and include validation steps.

Skills exist because the same tasks come up repeatedly. Without a skill, the AI has to rediscover the conventions every session. With a skill, it reads the playbook, applies it, and the result is consistent with the rest of the codebase.

### QA harness (`.ai/qa/`)

Testing guidance at two layers:
- **PHPUnit functional tests**: how to write `WebTestCase` tests with transaction rollback isolation, how to use page objects and fixtures, how to test CQRS flows end-to-end through HTTP
- **Playwright e2e**: full user journey tests that run against a live Compose stack; used for critical paths (sign-up → login → protected action)

### Multi-agent patterns

Several skills spawn parallel subagents to do work faster and more thoroughly than a single sequential pass:

- **`/pre-implement-spec`** — after reading the spec, launches three agents simultaneously: a Gap & Compliance reviewer, a Backward Compatibility auditor, and a Risk & Security assessor. Each has a focused role prompt and reports findings independently; the orchestrator synthesises them into the Readiness Report.
- **`/code-review`** — launches an Architecture reviewer, a Security reviewer, and a Frontend reviewer in parallel while the CI gate runs at the same time. The separate role prompts keep each agent narrowly focused.
- **`/implement-spec`** — before coding each phase, spawns Explore agents in parallel to map call sites, test coverage, event subscribers, and migration scope. All writes stay in the main agent.
- **`/parallel-research`** — standalone skill for ad-hoc multi-angle codebase exploration. Use it before implementing anything in unfamiliar territory.

The rule throughout: **spawn in parallel, write in the main agent**. Subagents are readers and analysts; they never produce commits.

### Lessons (`.ai/lessons.md`)

Institutional memory of mistakes worth not repeating. Each entry has a why and a how-to-apply. The AI reads `lessons.md` at the start of each session before proposing changes.

Write a new lesson whenever you correct a non-obvious mistake. Lessons should capture the *reasoning*, not just the rule — a rule without a why gets ignored or misapplied in edge cases.

### Design system rules (`.ai/ds-rules.md`)

Tailwind + shadcn/ui token rules for frontend work. Semantic tokens only (`bg-background`, `text-foreground`, `border-border`). No hardcoded colors. No arbitrary values. The AI checks these before writing any UI code — catches `bg-white` and `text-gray-500` before they land.

---

## The spec-first workflow

```
1. Write spec   →  /spec-writing
2. Audit spec   →  /pre-implement-spec
3. Implement    →  /implement-spec
4. Review       →  /code-review
5. PR           →  /auto-create-pr
6. Merge        →  /merge-buddy
```

### When to write a spec

Specs are for non-trivial features: anything involving a new bounded context, a migration, a new API contract, or a multi-step implementation. Skip the spec for small fixes, typo corrections, and refactors that don't change behavior.

### When to skip the spec

One-liners, bug fixes, and isolated changes that don't affect public contracts or data models can go straight to implementation. If you're unsure, write a spec — it's 10 minutes of alignment that saves hours of rework.

---

## Skills reference

### Workflow

| Skill | What it does |
|-------|-------------|
| `/new-feature` | Creates an isolated git worktree + branch from `main`. Called twice per spec-driven feature: once with `spec/` prefix, once with `feat/` prefix |
| `/spec-writing` | Produces a `.ai/specs/{YYYY-MM-DD}-{slug}.md` with the full spec format; opens a spec-only PR |
| `/pre-implement-spec` | Audits a merged spec against the codebase using parallel specialized agents; flags gaps, BC risks, and missing tests; produces a Readiness Report |
| `/implement-spec` | Implements an approved spec phase by phase; runs the CI gate after each phase |
| `/sync-context-docs` | Updates `AGENTS.md` files for every bounded context touched by the branch; run before opening the PR |
| `/code-review` | Reviews the current branch diff using parallel specialized reviewer agents (architecture, security, frontend) and runs the CI gate simultaneously |
| `/check-and-commit` | Lint + test + commit |
| `/fix-specs` | Normalises spec filenames under `.ai/specs/` to the `{YYYY-MM-DD}-{slug}.md` convention |

### Research

| Skill | What it does |
|-------|-------------|
| `/parallel-research` | Spawns multiple Explore subagents simultaneously to map an unfamiliar area of the codebase from different angles before implementing |

### PHP / Symfony

| Skill | What it does |
|-------|-------------|
| `/scaffold-bounded-context` | Generates the full 4-layer skeleton: Domain, Application, Infrastructure, Presentation |
| `/add-command` | Adds a Command + Handler + UseCase; wires handler tag |
| `/add-query` | Adds a Query + Handler + UseCase + Response DTO |
| `/add-route` | Adds an HTTP endpoint with controller, request DTO, and Nelmio OpenAPI attributes |
| `/scaffold-doctrine-migration` | Runs `migrate-diff`, reviews SQL, helps write rollback |
| `/lint-php` | Runs PHPStan + cs-fixer + deptrac locally inside Docker |

### Frontend

| Skill | What it does |
|-------|-------------|
| `/scaffold-nextjs-page` | App Router page with loading and error boundaries |
| `/scaffold-shadcn-form` | react-hook-form + zod + shadcn Form primitives; validates against ds-rules |
| `/regenerate-api-client` | Runs openapi-typescript against the live API, commits the result |
| `/lint-js` | Runs tsc + ESLint locally inside Docker |
| `/integration-tests` | Run, create, or convert PHPUnit functional and Playwright e2e tests |

### PR automation

| Skill | What it does |
|-------|-------------|
| `/auto-create-pr` | Pushes the branch, opens a GitHub PR with the correct format and labels |
| `/auto-review-pr` | Reviews a PR by number; checks for convention violations |
| `/merge-buddy` | Verifies CI is green and merges |
| `/root-cause` | Investigates a failing test or production bug |
| `/fix` | Applies a root-cause fix found by `/root-cause` |

### Harness maintenance

| Skill | What it does |
|-------|-------------|
| `/create-agents-md` | Generates a minimal `AGENTS.md` for a new package or bounded context |
| `/skill-creator` | Scaffolds a new skill from a description |

---

## AGENTS.md hierarchy

Every package and app has its own `AGENTS.md`:

```
AGENTS.md                      root — Task Router entry point
apps/api/AGENTS.md             Symfony conventions, CQRS wiring, Doctrine rules
apps/api/src/User/AGENTS.md    User context — auth, roles, security.yaml
apps/web/AGENTS.md             Next.js public app conventions
apps/admin/AGENTS.md           Admin panel conventions
packages/ui-react/AGENTS.md    Design system + shadcn rules
packages/api-client-ts/AGENTS.md  How to use the generated client
ops/AGENTS.md                  Docker, Compose, CI
```

The root `AGENTS.md` Task Router is the entry point. It maps task types to the specific local guide to read first. Claude Code reads the nearest `AGENTS.md` to the files it's editing. Never contradict a local `AGENTS.md` without updating it — stale guidance is worse than no guidance.

---

## How to extend the harness

### Adding a new skill

Create `.ai/skills/<skill-name>/SKILL.md`. The format:
- **Goal**: one sentence on what the skill produces
- **Steps**: numbered, concrete, referencing the relevant `AGENTS.md` sections
- **Validation**: which `make` targets to run after
- **Examples**: input/output examples

Register it in the root `AGENTS.md` Task Router if it applies to a recurring task type.

### Creating an AGENTS.md for a new package

```
/create-agents-md
```

The skill generates a minimal `AGENTS.md` from the package layout. Edit it to add the local conventions the AI should follow — imports, naming, testing patterns, validation commands.

### Updating lessons

After any correction or non-obvious discovery:

```bash
# Open .ai/lessons.md and add an entry:
## L-NNN — <short title>

**Don't** <the mistake>.

**Why**: <the reasoning — what goes wrong without this rule>.

**How to apply**: <when and where this kicks in>.
```

Short entries get read. Long entries get skipped.

---

## Typical session

```sh
# Step 1 — Design: spec worktree
/new-feature spec/password-reset

# Write the spec, answer open questions, open a spec-only PR to main
/spec-writing "user can reset their password"

# [merge the spec PR]

# Step 2 — Audit: catch gaps before writing code
/pre-implement-spec .ai/specs/2026-06-06-password-reset.md

# Step 3 — Implement: feature worktree
/new-feature feat/password-reset

# Scaffold and implement phase by phase
/scaffold-bounded-context PasswordReset   # if new context needed
/implement-spec .ai/specs/2026-06-06-password-reset.md

# Update AGENTS.md files for touched contexts
/sync-context-docs

# Review the full diff before opening the PR
/code-review

# Open PR:
/auto-create-pr
```

The skills handle the boilerplate. You handle the domain logic and the review.

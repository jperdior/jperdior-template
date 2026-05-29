# AI Workflow

This template ships with a full AI engineering harness under `.ai/`. The intended workflow is spec-first, skill-driven, and self-improving.

## Roles

| Tool | Role |
|------|------|
| Claude Code / Cursor | Paired engineer — reads AGENTS.md, follows the Task Router, uses skills |
| `.ai/specs/` | Source of truth for planned features |
| `.ai/skills/` | Reusable playbooks for common tasks |
| `.ai/lessons.md` | Accumulated project-specific knowledge |
| `.ai/qa/` | Integration test harness guidance |

## Typical feature loop

```
1. Write spec   →  /spec-writing
2. Audit spec   →  /pre-implement-spec
3. Implement    →  /implement-spec
4. Review       →  /code-review
5. PR           →  /auto-create-pr
6. Merge        →  /merge-buddy
```

### 1. Write a spec

```
/spec-writing
```

Produces a `.ai/specs/<slug>/SPEC.md` with: context, user stories, acceptance criteria, open questions. Open questions must be resolved before implementation.

### 2. Pre-implementation audit

```
/pre-implement-spec
```

Reads the spec, maps it to the codebase, identifies risks, proposes the implementation plan. Flags anything that would violate AGENTS.md rules.

### 3. Implement

```
/implement-spec
```

Follows the plan from step 2. Creates bounded context scaffold, migrations, tests. Runs `make lint && make test` before reporting done.

### 4. Code review

```
/code-review
```

Reviews the diff on the current branch against main. Checks: DDD layer violations, missing tests, security issues, migration correctness, OpenAPI completeness.

### 5. Create PR

```
/auto-create-pr
```

Pushes the branch, creates the GitHub PR with the correct labels, links the spec.

### 6. Merge

```
/merge-buddy
```

Checks CI is green, labels are correct, reviewers approved, then merges.

## Skills reference

### Core

| Skill | Purpose |
|-------|---------|
| `/spec-writing` | Write a new feature spec |
| `/pre-implement-spec` | Audit a spec before coding |
| `/implement-spec` | Implement an approved spec |
| `/code-review` | Review current branch diff |
| `/check-and-commit` | Lint + test + commit |
| `/fix-specs` | Update stale specs after implementation drift |

### PHP / Symfony

| Skill | Purpose |
|-------|---------|
| `/scaffold-bounded-context` | Generate a new context skeleton |
| `/add-command` | Add a Messenger command + handler + test |
| `/add-query` | Add a Messenger query + handler + response |
| `/add-route` | Add an HTTP endpoint with OpenAPI annotations |
| `/scaffold-doctrine-migration` | Generate + review a migration |

### Frontend

| Skill | Purpose |
|-------|---------|
| `/scaffold-nextjs-page` | App Router page with loading + error boundaries |
| `/scaffold-shadcn-form` | react-hook-form + zod + shadcn Form primitives |
| `/regenerate-api-client` | Run openapi-typescript against the running API |

### Automation

| Skill | Purpose |
|-------|---------|
| `/auto-create-pr` | Push branch + open GitHub PR |
| `/auto-review-pr` | Review a PR by number |
| `/merge-buddy` | Check gates + merge |
| `/auto-update-changelog` | Update CHANGELOG on merge |
| `/root-cause` | Investigate a failing test or bug |
| `/fix` | Apply a root-cause fix |

## AGENTS.md hierarchy

Every package and app has its own `AGENTS.md` with local conventions. The root `AGENTS.md` Task Router is the entry point — it tells you which local guide to read first.

Claude reads the nearest `AGENTS.md` to the files it's editing. Never contradict a local AGENTS.md without updating it.

## lessons.md

When you correct a mistake or find a non-obvious invariant, update `.ai/lessons.md`. Claude reads it at the start of each session to avoid repeating past mistakes.

```
/skill-creator   # create a new skill from a task you just completed
/create-agents-md  # generate an AGENTS.md for a new package
```

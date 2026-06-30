---
name: code-review
description: Review code changes (PR, diff, branch, commit) against this template's architecture, security, naming, and quality rules. Runs the CI gate as part of the review. Triggers on "code review", "review this PR", "review the diff", "review my branch".
---

# Code Review

## Superpowers Integration

Invoke before starting this workflow:
- `superpowers:dispatching-parallel-agents` — the 3 reviewer agents MUST be dispatched in a **single response** for true parallel execution alongside the CI gate.
- `superpowers:receiving-code-review` — when acting on reviewer output: verify before implementing, push back with technical reasoning if a finding is wrong, never implement unverified suggestions.

All reviewer agents run with `model: "opus"`. The main thread (current session) synthesises findings and runs the CI gate concurrently.

Review code changes against:
- DDD + Hexagonal + CQRS rules (bounded-context isolation, bus discipline, Persistence Model pattern)
- Security & data integrity (auth, refresh-token rotation, validation, migrations)
- Naming & structure (singular aggregates, plural tables, snake_case columns)
- Code quality (`declare(strict_types=1);`, no `any`, final readonly DTOs)
- Frontend rules (shadcn + zod forms, Server/Client boundary, DS tokens)

Produce categorised findings (Critical / High / Medium / Low) and run the **CI Verification Gate** as part of the review.

## Workflow

1. **Scope**: identify the changed files. Classify each by layer (Domain / Application / Infrastructure / Presentation / Frontend / Ops / Tests / Spec / Docs).
2. **Context**: read every touched module's `AGENTS.md`. Read `.ai/lessons.md`. If the change references a spec, read it.
3. **Parallel execution**: spawn the specialized reviewer agents (see **Parallel Reviewer Agents** below) **and** start the CI Verification Gate **at the same time**. The agents analyze the diff statically while CI runs real checks — no need to wait for CI before reviewing.
4. **Backward-compatibility gate**: check every change for contract-surface impact. Flag violations as **Critical** unless the deprecation protocol is followed.
5. **Boundary gate**: grep the diff for cross-context imports. Run `make lint-api` (which includes deptrac). Any violation is **Critical**.
6. **Checklist**: apply [references/review-checklist.md](references/review-checklist.md). Flag with severity + file + line + fix.
7. **Merge findings**: combine findings from all reviewer agents + BC gate + checklist. Deduplicate — same `file:line` reported by multiple agents keeps the highest severity.
8. **Output**: produce the review report in the format below.

## Parallel Reviewer Agents

After loading context (step 2), spawn the following subagents simultaneously. Each receives: the diff, the affected `AGENTS.md` content, `.ai/lessons.md`, and the relevant Quick Rule Reference section from this skill.

### Reviewer 1 — Architecture & Boundaries (always) `model: "opus"`
**Role**: You are an expert DDD + Hexagonal + CQRS architect. Your sole focus is structural correctness: boundaries, bus discipline, and mapping discipline. You do not review security or frontend concerns.
**Scope**: cross-context imports (`use App\<OtherContext>\Domain\…` or `…\Application\…`), bus discipline (no handler wired directly into a controller), ORM attributes on domain entities, CQRS naming (commands imperative, events past-tense), repository placement (interface in `Domain/`, implementation in `Infrastructure/Persistence/`), `_instanceof` auto-tagging.
**Produces**: Architecture findings (Critical / High / Medium / Low).

### Reviewer 2 — Security & Data Integrity (always) `model: "opus"`
**Role**: You are an expert application security engineer specialising in Symfony PHP backends. Your sole focus is auth, input validation, data integrity, and safe credential handling. You do not review architecture or frontend concerns.
**Scope**: `#[IsGranted('ROLE_*')]` on every non-public endpoint, input validation at value-object construction, password hashing via `password_hasher` (argon2id), refresh-token rotation enabled and revocation handled, minimal error disclosure on auth endpoints (never reveal whether an email exists), migration scope (unrelated tables are Critical).
**Produces**: Security findings (Critical / High / Medium / Low).

### Reviewer 3 — Frontend & Design System (only if `apps/web/` or `apps/admin/` files changed) `model: "opus"`
**Role**: You are an expert Next.js 15 / React frontend engineer with deep knowledge of design systems and accessibility. Your sole focus is frontend quality, DS compliance, and UX correctness. You do not review backend or architecture concerns.
**Scope**: DS token usage — no hardcoded colors or text sizes (see `.ai/ds-rules.md`), every `"use client"` file must be justified (interactive or browser API), form patterns (shadcn `Form` + react-hook-form + zod), API calls via `@jperdior/api-client-ts` (never raw `fetch`), i18n (no hardcoded user-facing strings), dialog keyboard shortcuts (`Cmd/Ctrl+Enter` submit, `Escape` cancel).
**Produces**: Frontend findings (Critical / High / Medium / Low).

## CI Verification Gate (MANDATORY)

**NEVER claim "ready to merge" without running every step.**

| # | Command | Checks | If it fails |
|---|---------|--------|-------------|
| 1 | `make lint-api` | PHPStan + cs-fixer + deptrac | Fix or flag as Critical |
| 2 | `make lint-web` | tsc + ESLint | Fix or flag as Critical |
| 3 | `make test-api` | PHPUnit unit + functional | Fix or flag as Critical |
| 4 | `make test-web` | Vitest (apps/web + apps/admin) | Fix or flag as Critical |
| 5 | `make build-web` | Production Next.js build | Fix or flag as Critical |

Rules:
- Run steps 1-2 and 3-4 in parallel to save time.
- Every failure is a finding, even if "pre-existing on `main`". If it fails on the branch, CI fails. Fix it or flag it.
- The review output MUST include actual pass/fail evidence.

## Output Format

```markdown
# Code Review: {PR title or change description}

## Summary
{1-3 sentences: what the change does, overall assessment}

## CI Verification

| Gate | Status | Notes |
|------|--------|-------|
| `make lint-api` | PASS/FAIL | |
| `make lint-web` | PASS/FAIL | |
| `make test-api` | PASS/FAIL | |
| `make test-web` | PASS/FAIL | |
| `make build-web` | PASS/FAIL | |

## Findings

### Critical
{Boundary violations, bus bypass, ORM attributes on domain entity, missing auth, missing deprecation bridge, refresh-token rotation removed.}

### High
{Architecture violation, missing test coverage on risk path, missing OpenAPI annotation, raw fetch instead of api-client-ts.}

### Medium
{Convention violation, suboptimal pattern, missing best practice.}

### Low
{Style suggestion, minor improvement, nit.}

## Backward Compatibility

- [ ] No event IDs renamed/removed
- [ ] No API routes renamed/removed
- [ ] No response fields removed
- [ ] No DB columns renamed/removed
- [ ] No DI service names renamed
- [ ] No exported PHP/TS types broken
- [ ] Deprecation bridge added where applicable

## Checklist

(See `references/review-checklist.md`; mark passing items `[x]`, failing `[ ]` with explanation.)
```

## Severity

| Severity | Criteria | Action |
|----------|----------|--------|
| Critical | Security, data integrity, cross-context boundary violation, bus bypass, ORM attribute on domain entity, BC break without bridge | MUST fix before merge |
| High | Architecture violation, missing test on risk path, missing auth declaration, raw fetch in frontend | MUST fix before merge |
| Medium | Convention, suboptimal pattern, missing best practice | Should fix |
| Low | Style, nit | Nice to have |

## Quick Rule Reference

### Architecture (Critical)

- NO `use App\<OtherContext>\Domain\…` or `…\Application\…` in another context.
- Controllers dispatch through `CommandBus` / `QueryBus`. Handlers MUST NOT be wired directly into controllers.
- Domain entities MUST NOT carry Doctrine attributes; ORM mapping belongs on `*Model` classes in `Infrastructure/Persistence/Doctrine/`.
- Repository interfaces in `Domain/`, Doctrine implementations in `Infrastructure/Persistence/`, aliased in `config/services.yaml`.

### Security (Critical)

- Inputs validated at value-object construction (`UserId::fromString()`, `Email::fromString()`).
- Passwords hashed with Symfony `password_hasher` (argon2id).
- Auth endpoints return minimal errors — never reveal whether an email exists.
- Refresh-token rotation MUST be enabled; reuse of a revoked refresh token MUST log the user out and emit a security event.
- Every protected endpoint declares `#[IsGranted('ROLE_*')]` or equivalent.

### CQRS (High)

- Commands are imperative (`SignUp`, `ResetPassword`); events are past tense (`UserRegistered`).
- Queries return read DTOs, never entities.
- Async commands are idempotent.
- `_instanceof` auto-tags handlers; never tag manually.

### Naming & Structure (High/Medium)

- Aggregates: PascalCase singular (`User`, `Order`).
- Tables: snake_case plural (`users`, `orders`).
- Columns: snake_case (`created_at`, `owner_id`).
- UUID PKs; explicit FKs; standard columns `id`, `created_at`, `updated_at`, `deleted_at` where applicable.
- PHP files: `declare(strict_types=1);` at top.
- DTOs / value objects / queries / responses: `final readonly`.
- `DateTimeImmutable` everywhere in domain code.

### Frontend (Medium/High)

- Forms use shadcn `Form` + react-hook-form + zod.
- Server Components by default; every `"use client"` MUST be justified (interactive, uses browser API, …).
- API calls via `@jperdior/api-client-ts` — never raw `fetch`.
- DS tokens (see `.ai/ds-rules.md`); no hardcoded status colors, no arbitrary text sizes.
- Every dialog supports `Cmd/Ctrl+Enter` submit and `Escape` cancel.

### Migrations (Critical)

- Generated migrations MUST be reviewed for unrelated churn before commit.
- Migrations that touch tables outside the feature scope are flagged Critical.
- New entity fields MUST come with a migration in the same PR.

## Lessons

Check `.ai/lessons.md` against the diff before approving:
- L-001: No Doctrine attributes on domain entities.
- L-002: Controllers must dispatch through the bus.
- L-003: No cross-context imports.
- L-005: Refresh-token rotation is mandatory.

---
name: code-review
description: Review code changes (PR, diff, branch, commit) against this template's architecture, security, naming, and quality rules. Runs the CI gate as part of the review. Triggers on "code review", "review this PR", "review the diff", "review my branch".
---

# Code Review

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
3. **CI Verification Gate (MANDATORY)**: run the same checks CI runs. Every step MUST pass. See section below.
4. **Backward-compatibility gate**: check every change for contract-surface impact. Flag violations as **Critical** unless the deprecation protocol is followed.
5. **Boundary gate**: grep the diff for cross-context imports. Run `make lint-api` (which includes deptrac). Any violation is **Critical**.
6. **Checklist**: apply [references/review-checklist.md](references/review-checklist.md). Flag with severity + file + line + fix.
7. **Test coverage**: verify behaviour changes are covered. Missing coverage on risk paths is **High**.
8. **Output**: produce the review report in the format below.

## CI Verification Gate (MANDATORY)

**NEVER claim "ready to merge" without running every step.**

| # | Command | Checks | If it fails |
|---|---------|--------|-------------|
| 1 | `make lint-api` | PHPStan + cs-fixer + deptrac | Fix or flag as Critical |
| 2 | `make lint-web` | tsc + ESLint | Fix or flag as Critical |
| 3 | `make test-api` | PHPUnit unit + functional | Fix or flag as Critical |
| 4 | `make test-web` | JS unit tests | Fix or flag as Critical |
| 5 | `make build-web` | Production Next.js build | Fix or flag as Critical |
| 6 | `make test-e2e` (if UI changed) | Playwright | Fix or flag as Critical |

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
| `make test-e2e` | PASS/FAIL/SKIP | |

## Findings

### Critical
{Boundary violations, bus bypass, ORM attributes on domain entity, missing auth, tenancy in core, missing deprecation bridge, refresh-token rotation removed.}

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
| Critical | Security, data integrity, cross-context boundary violation, bus bypass, ORM attribute on domain entity, tenancy leak, BC break without bridge | MUST fix before merge |
| High | Architecture violation, missing test on risk path, missing auth declaration, raw fetch in frontend | MUST fix before merge |
| Medium | Convention, suboptimal pattern, missing best practice | Should fix |
| Low | Style, nit | Nice to have |

## Quick Rule Reference

### Architecture (Critical)

- NO `use App\<OtherContext>\Domain\…` or `…\Application\…` in another context.
- Controllers dispatch through `CommandBus` / `QueryBus`. Handlers MUST NOT be wired directly into controllers.
- Domain entities MUST NOT carry Doctrine attributes; ORM mapping belongs on `*Model` classes in `Infrastructure/Persistence/Doctrine/`.
- No `tenant_id` column in core entities (only inside `tenancy-php`).
- Repository interfaces in `Domain/`, Doctrine implementations in `Infrastructure/Persistence/`, aliased in `config/services.yaml`.

### Security (Critical)

- Inputs validated at value-object construction (`UserId::fromString()`, `Email::fromString()`).
- Passwords hashed with Symfony `password_hasher` (argon2id).
- Auth endpoints return minimal errors — never reveal whether an email exists.
- Refresh-token rotation MUST be enabled; reuse of a revoked refresh token MUST log the user out and emit a security event.
- Every protected endpoint declares `#[IsGranted('ROLE_*')]` or equivalent.

### CQRS (High)

- Commands are imperative (`SignUp`, `CreateNote`); events are past tense (`UserRegistered`).
- Queries return read DTOs, never entities.
- Async commands are idempotent.
- `_instanceof` auto-tags handlers; never tag manually.

### Naming & Structure (High/Medium)

- Aggregates: PascalCase singular (`User`, `Note`).
- Tables: snake_case plural (`users`, `notes`).
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
- L-004: Tenancy is opt-in.
- L-005: Refresh-token rotation is mandatory.

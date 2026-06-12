---
name: auto-sec-report
description: Paranoid OWASP-oriented security analysis for a PR, spec, or branch diff. Hunts non-obvious attack vectors, flags same-pattern hotspots, emits "go deeper" follow-ups. Writes report under .ai/analysis/. Triggers on "security report", "sec audit", "OWASP review".
---

# Auto Sec Report

Independent, paranoid security audit of a single unit of work (one PR / one spec / one branch diff). Hunts for attack vectors beyond the OWASP top 10 and same-pattern hotspots elsewhere in the codebase.

## Workflow

1. **Pick the unit of work**: a PR (`gh pr diff <N>`), a spec, or a branch diff (`git diff origin/main...HEAD`).
2. **Map the surface**: enumerate every input boundary the diff adds or modifies (HTTP endpoints, console commands, Messenger consumers, JWT claims, deserialisation paths, file uploads, SQL queries, regexes).
3. **Run the OWASP checklist** against each boundary (see below).
4. **Hunt non-obvious vectors**: time-of-check / time-of-use, race conditions, type-juggling (PHP), JWT algorithm confusion, mass-assignment, IDOR, SSRF, prototype pollution (JS), open-redirect, refresh-token rotation gaps, log injection, denial-of-service via expensive queries.
5. **Same-pattern sweep**: grep the rest of the codebase for the same dangerous pattern. If one context has it, the others probably do too.
6. **Write the report** under `.ai/analysis/{YYYY-MM-DD}-sec-{slug}.md`.

## OWASP Checklist (per boundary)

- **Auth & session**: is the endpoint protected? Does it accept anonymous? Refresh-token rotation enforced?
- **Authorization**: does it check ownership (the user can only access their own data)?
- **Input validation**: zod + value-object construction for every field?
- **Injection**: any raw SQL (`DBAL::executeQuery`) with user-supplied parts? Any LDAP / shell exec?
- **Crypto**: hand-rolled crypto anywhere? `random_int` vs `rand`? `password_hash` cost ≥ 10?
- **Sensitive data**: PII / credentials in logs? Cached without encryption?
- **Misconfig**: debug mode disabled in prod? Error handler doesn't leak stack traces?
- **Vulnerable deps**: any dependency added with a known CVE? Run `composer audit` and `pnpm audit`.
- **SSRF**: does the endpoint take a URL/host and dereference it? Allowlist?
- **CSRF**: state-changing endpoints — cookie-only auth? CSRF token enforced?
- **Logging & monitoring**: failed-auth events logged? Rate-limited?

## Output Format

```markdown
# Security Report: {title}

**Unit of work**: PR #{N} / spec / branch
**Date**: {YYYY-MM-DD}
**Reviewer**: auto-sec-report

## Summary
{1-3 sentences. Overall posture. Number of Critical / High / Medium findings.}

## Critical findings
{Exploitable now. Block merge.}

| # | Issue | Where | Mechanism | Mitigation |
|---|-------|-------|-----------|------------|

## High
{Architectural or design weakness. Strong mitigation required before release.}

## Medium
{Defence-in-depth gaps. Should be addressed.}

## Low / Notes
{Hardening suggestions, informational.}

## Same-pattern hotspots elsewhere

| Pattern | Other locations | Suggestion |
|---------|-----------------|------------|

## Next steps — go deeper

1. {Topic the reviewer didn't have time for but could exploit.}
2. {Adjacent risk that warrants its own audit.}
```

## Rules

- Never claim "no findings" without listing the OWASP checklist coverage explicitly.
- Never propose fixes here — hand off to `/fix` with the report.
- Assume the attacker is sophisticated and motivated. The "this wouldn't happen in practice" defence is not accepted.
- Every Critical finding needs a concrete exploit path written in 2-3 sentences. If you can't write the path, downgrade to High.
- Reproduce hotspots locally where feasible (curl, db query).

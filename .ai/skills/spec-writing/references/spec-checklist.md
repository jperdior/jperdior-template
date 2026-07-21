# Spec Review Checklist

## 1. Structure

- [ ] Filename matches `{YYYY-MM-DD}-{kebab-case-title}.md`
- [ ] TLDR present, 2-3 sentences
- [ ] Open Questions block cleared before research phase
- [ ] Phases declared; each phase deliverable is testable
- [ ] Changelog section present

## 2. Architecture

- [ ] Every aggregate placed in a bounded context
- [ ] No cross-context imports of internals proposed (aggregates, repositories, VOs, `*Handler`/`*UseCase`/`*Subscriber`)
- [ ] Cross-context interaction via domain events or bus-dispatched `*Command`/`*Query` (`PublicMessage`)
- [ ] No proposal to add `#[ORM\*]` attributes to domain entities

## 3. Data & Security

- [ ] All inputs validated (route attributes + value-object construction)
- [ ] Sensitive fields (passwords, tokens) hashed/encrypted with the standard helpers
- [ ] Auth requirement declared per endpoint
- [ ] Migration scope is bounded; no unrelated table churn

## 4. CQRS

- [ ] Commands are imperative, past tense for events (`SignUp`, `UserRegistered`)
- [ ] Queries return read DTOs, never entities
- [ ] Handlers depend on domain interfaces, not Doctrine implementations
- [ ] Async commands are idempotent (designed for Messenger retries)

## 5. API & UI

- [ ] OpenAPI annotations on every controller
- [ ] Forms use shadcn `Form` + zod; no raw `<input>` outside Forms
- [ ] Server/Client boundary explicit for App Router pages
- [ ] DS rules respected (see `.ai/ds-rules.md`)

## 6. Tests

- [ ] At least one PHPUnit Functional test per controller action
- [ ] At least one Vitest + RTL test for every non-trivial frontend component, hook, or pure module added
- [ ] Edge cases enumerated (auth required, ownership, validation errors)

## 7. Backward Compatibility

- [ ] If any contract surface (event IDs, API routes, response fields, DB columns) is removed or renamed, the deprecation protocol is followed (bridge + `@deprecated` + migration note)

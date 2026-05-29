# Final Compliance Gate

Run before declaring a spec ready for implementation. Every row MUST pass.

| Gate | Question | Pass criteria |
|------|----------|---------------|
| Boundary | Does any phase import another context's `Domain/` or `Application/`? | No — only via the bus or a public application service. |
| Bus | Do controllers dispatch through `CommandBus` / `QueryBus`? | Yes. No handler is injected directly into a controller. |
| Mapping | Does any domain entity carry `#[ORM\*]` attributes? | No — XML mapping only. |
| Tenancy | Does any new entity carry `tenant_id`? | No — unless explicitly using `tenancy-php`. |
| Validation | Are all inputs validated at value-object construction? | Yes. |
| Idempotency | Are subscribers/workers idempotent under retry? | Yes. |
| Auth | Does every protected endpoint declare its `ROLE_*` requirement? | Yes. |
| Naming | Singular aggregate/command/event names? Plural table names? | Yes. |
| DateTime | `DateTimeImmutable` everywhere in domain code? | Yes. |
| Final readonly | Value objects, DTOs, queries, responses `final readonly`? | Yes. |
| `strict_types` | Every PHP file in `src/` and `tests/`? | Yes. |
| Tests | Coverage planned (PHPUnit + Playwright)? | Yes. |
| BC | Any contract surface removed/renamed without deprecation bridge? | No. |

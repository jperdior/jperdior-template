# Final Compliance Gate

Run before declaring a spec ready for implementation. Every row MUST pass.

| Gate | Question | Pass criteria |
|------|----------|---------------|
| Boundary | Does any phase import another context's aggregates, repositories, VOs, or executable Application (`*Handler`/`*UseCase`/`*Subscriber`)? | No. Cross-context goes through the bus — dispatch its published `*Command`/`*Query` (the `PublicMessage` layer) or react to its domain events. |
| Bus | Do controllers dispatch through `CommandBus` / `QueryBus`? | Yes. No handler is injected directly into a controller. |
| Mapping | Does any domain entity carry `#[ORM\*]` attributes? | No — ORM attributes belong on `*Model` classes in `Infrastructure/Persistence/Doctrine/`. |
| Validation | Are all inputs validated at value-object construction? | Yes. |
| Idempotency | Are subscribers/workers idempotent under retry? | Yes. |
| Auth | Does every protected endpoint declare its `ROLE_*` requirement? | Yes. |
| Naming | Singular aggregate/command/event names? Plural table names? | Yes. |
| DateTime | `DateTimeImmutable` everywhere in domain code? | Yes. |
| Final readonly | Value objects, DTOs, queries, responses `final readonly`? | Yes. |
| `strict_types` | Every PHP file in `src/` and `tests/`? | Yes. |
| Tests | Coverage planned (PHPUnit Functional for API + Vitest + RTL for frontend)? | Yes. |
| BC | Any contract surface removed/renamed without deprecation bridge? | No. |

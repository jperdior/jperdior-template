# Lessons

Institutional memory of mistakes worth not repeating. One entry per lesson. Write the **why**, not just the rule. Keep the list short.

---

## L-001 — Doctrine attributes on domain entities

**Don't.** Domain entities live in `Domain/` and may not import anything framework-specific. If you add `#[ORM\Entity]` to a domain entity, you've coupled the domain to ORM and a future replacement (or a swap to read models) becomes a rewrite. Instead, create a `*Model` class in `Infrastructure/Persistence/Doctrine/` that Doctrine owns, and map primitives there. The repository's `toDomain()` / `toOrm()` methods handle the conversion.

**How to apply**: when a developer or AI agent proposes attribute mapping on a domain entity, point them at `*Model` pattern and `apps/api/AGENTS.md` → Persistence.

---

## L-002 — Controllers must dispatch through the bus

**Don't** inject a handler into a controller. Inject `CommandBus` / `QueryBus`. The bus implementations are the only place that talks to Symfony Messenger; controllers shouldn't know it exists.

**Why**: lets us swap or wrap the bus (logging, validation middleware, async transport) without rewriting every controller. Also keeps the Presentation layer thin and trivially testable.

---

## L-003 — Cross-context imports

**Don't** import another bounded context's `Domain/` or `Application/` classes. Communication between contexts goes through the **event bus** (publish domain events, subscribe in the other context) or a **public Application service** exposed for cross-context use.

**Why**: enforced by `deptrac` in CI. The whole point of bounded contexts is replaceability. A single `use App\User\Domain\User;` inside `Orders\` collapses that boundary.

**How to apply**: if you need data from another context, either subscribe to its events and project a local read-model, or call its public bus.

---

## L-004 — Single-tenant by design

**Don't** add `tenant_id` columns to entities. The template is single-tenant. All entities in `apps/api/src/` are single-tenant by default.

**Why**: multi-tenancy is a significant cross-cutting concern that varies per project. Adding it speculatively creates complexity that most projects never need. If your project requires multi-tenancy, fork the template and add your own implementation — a Doctrine `SQLFilter` + request-scoped `TenantContext` is the standard approach, but the scope and details should be an explicit decision, not a default.

**How to apply**: if you see `tenant_id` anywhere in a generated entity, remove it.

---

## L-005 — Refresh-token rotation

**Don't** ship refresh tokens without rotation. Every successful `/auth/refresh` call MUST issue a *new* refresh token and revoke the previous one in the same transaction. Reuse of a revoked refresh token MUST log the user out everywhere and surface a security event.

**Why**: a leaked refresh token without rotation is a permanent backdoor. With rotation, the attacker and the legitimate user can't both keep using the same chain — whoever rotates last invalidates the other, and the mismatch is detectable.

**How to apply**: Gesdinet enforces this with `single_use: true` in `config/packages/gesdinet_jwt_refresh_token.yaml`. Never disable that flag.

---

## L-006 — Persistence Model pattern (not custom DBAL types)

**Don't** map domain entity properties typed as value objects via custom DBAL types. The old approach (custom `Type` classes registered in `doctrine.yaml`) was needed when Doctrine managed the domain entity directly — now the `*Model` persistence class uses only primitives, so no custom types are required.

**Why**: PHP 8.4 lazy-ghost objects enforce typed property assignment strictly. Doctrine managing a domain entity with value-object properties causes `TypeError`. The Persistence Model pattern sidesteps this entirely: `*Model` has `string $id`, `string $email`, etc., and the repository's `toDomain()` constructs the value objects.

**How to apply**: every new aggregate gets a `<Aggregate>Model.php` in `Infrastructure/Persistence/Doctrine/` with primitive fields and Doctrine PHP attributes. No `dbal.types` registration needed. See `src/User/Infrastructure/Persistence/Doctrine/UserModel.php` for the pattern.

---

## L-007 — Repository alias in context-owned services.yaml

**Don't** put a context's repository alias in the global `config/services.yaml`. Each context owns its DI wiring in `src/<Context>/Infrastructure/Symfony/Resources/config/services.yaml`.

**Why**: the global `services.yaml` imports context-specific files — it doesn't define context internals. This keeps context wiring encapsulated and makes it easy to see what each context wires up.

**How to apply**: when adding a new context, create `src/<Context>/Infrastructure/Symfony/Resources/config/services.yaml` with the alias, then add an `imports:` entry in `config/services.yaml`.

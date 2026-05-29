# Lessons

Institutional memory of mistakes worth not repeating. One entry per lesson. Write the **why**, not just the rule. Keep the list short.

---

## L-001 — Doctrine attributes on domain entities

**Don't.** Domain entities live in `Domain/` and may not import anything framework-specific. Doctrine mapping is XML, kept in `Infrastructure/Persistence/Doctrine/Mapping/<Aggregate>.orm.xml`. If you add `#[ORM\Entity]` to a domain entity, you've coupled the domain to ORM and a future replacement (or a swap to read models) becomes a rewrite.

**How to apply**: when a developer or AI agent proposes attribute mapping, point them at the XML file and `apps/api/AGENTS.md` → Persistence.

---

## L-002 — Controllers must dispatch through the bus

**Don't** inject a handler into a controller. Inject `CommandBus` / `QueryBus`. The bus implementations are the only place that talks to Symfony Messenger; controllers shouldn't know it exists.

**Why**: lets us swap or wrap the bus (logging, validation middleware, async transport) without rewriting every controller. Also keeps the Presentation layer thin and trivially testable.

---

## L-003 — Cross-context imports

**Don't** import another bounded context's `Domain/` or `Application/` classes. Communication between contexts goes through the **event bus** (publish domain events, subscribe in the other context) or a **public Application service** exposed for cross-context use.

**Why**: enforced by `deptrac` in CI. The whole point of bounded contexts is replaceability. A single `use App\User\Domain\User;` inside `Note\` collapses that boundary.

**How to apply**: if you need data from another context, either subscribe to its events and project a local read-model, or call its public bus.

---

## L-004 — Tenancy is opt-in

**Don't** add `tenant_id` columns to entities in `apps/api/src/`. The default template is single-tenant.

**Why**: open-mercato has 337 columns of `tenant_id`/`organization_id` and removing them is a 4-6-week refactor. Starting tenant-agnostic and adding tenancy via `packages/tenancy-php` (Doctrine SQLFilter + `TenantContext`) when a project actually needs it is the cheaper, cleaner default.

**How to apply**: if a project needs tenancy, follow `docs/multitenancy.md` (5-step opt-in). Never sprinkle `tenant_id` ad-hoc.

---

## L-005 — Refresh-token rotation

**Don't** ship refresh tokens without rotation. Every successful `/auth/refresh` call MUST issue a *new* refresh token and revoke the previous one in the same transaction. Reuse of a revoked refresh token MUST log the user out everywhere and surface a security event.

**Why**: a leaked refresh token without rotation is a permanent backdoor. With rotation, the attacker and the legitimate user can't both keep using the same chain — whoever rotates last invalidates the other, and the mismatch is detectable.

**How to apply**: `User\Application\Command\RefreshToken\Handler` enforces this. If you bypass it, write a regression test first.

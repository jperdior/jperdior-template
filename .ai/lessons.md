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

**Don't** import another bounded context's aggregates, repositories, value objects, or its **executable** Application classes (`*Handler` / `*UseCase` / `*Subscriber`). A context's **published contract** *is* cross-importable: its `Domain/Event/` classes **and** its `*Command` / `*Query` / Response DTOs (deptrac's `DomainEvent` + `PublicMessage` layers). Communication between contexts goes through the bus — react to a domain event, or **dispatch** the other context's published `*Command`/`*Query` through the command/query bus (you import the message class, never the handler).

**Why**: enforced by `deptrac` in CI. The whole point of bounded contexts is replaceability. A single `use App\User\Domain\User;` inside `Orders\` collapses that boundary. Messages stay swap-safe because they carry **primitives + shared identifier VOs only** — never a producer's domain VO.

**How to apply**: if you need data from another context, either subscribe to its events and project a local read-model, or dispatch its published `*Query`/`*Command` through the bus (put the dispatch in a use case, not just a controller — L-008). For a cross-context read that feeds a **domain** rule, keep the domain framework-free with the Provider pattern (`.ai/skills/add-command/SKILL.md`).

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

---

## L-008 — HTTP security guards do not apply to console commands

**Don't** assume `#[IsGranted('ROLE_ADMIN')]` on a controller protects the underlying use case from console invocations. The Symfony Security component only fires for HTTP requests; a console command dispatching the same `CommandBus` command bypasses it entirely.

**Why**: an admin-only use case that only checks the caller's role via the HTTP controller's `#[IsGranted]` attribute has no protection at all when invoked from `bin/console` — any console script can call it without restriction. This is intentional for trusted infrastructure scripts (seeders, migrations) but is a gap for anything reachable by an untrusted caller.

**How to apply**: if a use case must enforce a role invariant regardless of caller (HTTP *or* console), add explicit role validation inside the use case itself (inject the repository needed to look up the acting user, check their roles, throw a typed `DomainException`). Do not rely solely on controller-level `#[IsGranted]`.

---

## L-009 — Cross-context `*Model` imports are allowed at the Persistence boundary

At the `<Context>/Infrastructure/Persistence/` boundary, a persistence class MAY import `App\<OtherContext>\Infrastructure\Persistence\Doctrine\*Model::class` at two seams: a **repository** (`Doctrine*Repository.php`) for QueryBuilder JOINs or `em->getReference()`, **and** a `*Model` that maps a cross-context `#[ORM\ManyToOne]`/`ManyToMany` association (a Model→Model FK). The cross-context coupling stays at the Infrastructure layer only — Domain / Application / Presentation cross-context imports remain forbidden (L-003).

**Why**: a mapped association gives a **real, Doctrine-managed foreign key** — Doctrine owns the constraint + index, so `migrate-diff` generates them and stays stable (no perpetual drift). JOINs also avoid N+1-shaped follow-up lookups. Both keep read models populated in a single query.

**How to apply**:
- **`*Model` classes are NOT `final`** — Doctrine proxies (`getReference`, lazy associations) subclass the entity at runtime; a final class throws `Cannot generate lazy ghost … is final`. The `final_class` php-cs-fixer rule exempts `#[ORM\Entity]` classes, so non-final is lint-clean.
- Map the FK as `#[ORM\ManyToOne(targetEntity: OtherModel::class)]` + `#[ORM\JoinColumn(name: '<col>_id', onDelete: 'CASCADE')]`. Set it from the repository via `em->getReference(OtherModel::class, $id)`; filter reads with `IDENTITY(x.assoc) = :id` so the read repository needs no cross-context import.
- Add a `skip_violations` entry in `apps/api/deptrac.yaml` for **each** coupled persistence class (the `*Model` and any repository importing the other `*Model`), listing the specific import.
- Raw-SQL JOINs on table names need **no** `skip_violations` — deptrac sees PHP imports, not SQL strings. Prefer raw SQL when the repository is already DBAL-shaped.

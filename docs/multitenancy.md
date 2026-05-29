# Multi-tenancy

The core template is **single-tenant by design**. No entity in `apps/api/src/` carries a `tenant_id` column. The `User` aggregate is single-instance. Most projects don't need tenancy, so we don't pay the cost.

When a project does need it, it opts in by enabling `packages/tenancy-php`. The package is a Symfony bundle that provides:

- A `TenantId` UUID value object and a `TenantOwned` marker interface.
- A request-scoped `TenantContext`.
- A `TenantResolverInterface` (strategy) with two reference implementations: JWT claim and subdomain.
- A Doctrine `TenantFilter` that appends `tenant_id = :current_tenant_id` to every query touching a `TenantOwned` entity.
- A kernel.request listener that resolves the tenant, fills the context, and enables the filter.

Tenancy is a **cross-cutting concern** applied via a filter + a request-scoped context — *not* a column convention sprinkled across every aggregate. This is the right architectural shape: aggregates stay free of tenancy semantics; the runtime enforces scoping at the persistence boundary.

## Opt-in

See `packages/tenancy-php/README.md` for the canonical 5-step procedure:

1. Add `jperdior/tenancy-php` to `apps/api/composer.json` `require`.
2. Register `TenancyBundle` in `apps/api/config/bundles.php`.
3. Mark relevant entities with `TenantOwned`, add a `tenant_id` column in the XML mapping, and ship a migration.
4. Register the Doctrine SQL filter in `doctrine.yaml`.
5. Configure a resolver (or implement `TenantResolverInterface` yourself).

## What this means for new bounded contexts

When the template's `scaffold-bounded-context` skill creates a new context, it does **not** add a `tenant_id` column. That is correct for the default single-tenant case. If your project has enabled tenancy, modify the generated entity to implement `TenantOwned` and add the column in your own migration. The skill deliberately stays out of that decision because tenancy varies per project.

## What stays out of `packages/tenancy-php`

- Per-tenant database routing. Tenancy here is row-level (`WHERE tenant_id = ?`). Pool-per-tenant or schema-per-tenant strategies require a different runtime (a Doctrine `ConnectionRegistry` swap) and are out of scope.
- Tenant lifecycle (create / suspend / delete). That belongs in your own bounded context — typically called `Tenants` — using the same DDD layout as `User`/`Note`.
- Cross-tenant admin views. Those queries explicitly disable the filter: `$em->getFilters()->disable('tenant');`. Use sparingly and gate behind `ROLE_ADMIN`.

## CI guardrail

`deptrac` enforces that no bounded context in `apps/api/src/` imports `Jperdior\Tenancy\*` directly — the dependency goes the other way (filter inspects entities by interface). The only place a project should reference tenancy types is in its own `Tenants/` context (if it has one) and its `doctrine.yaml`.

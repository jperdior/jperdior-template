# tenancy-php — Agents Guidelines

Optional multi-tenancy add-on. **Not** required by the core template. Opt-in per project — see `README.md` for the 5-step procedure.

## Always

- Keep the domain layer (`src/Domain/`) framework-free. Only `jperdior/shared-kernel-php` and pure PHP allowed.
- Mark every tenant-scoped Doctrine entity with `TenantOwned`. The filter relies on the marker; missing it = silent cross-tenant leak.
- Bind `current_tenant_id` as a string (UUID `value`). The filter renders it through `getParameter()`, which quotes it.
- Use `declare(strict_types=1);` at the top of every file.
- `final readonly` for value objects, listeners, and resolvers.

## Ask First

- Ask before adding a third resolver to `src/Infrastructure/Resolver/` — two reference implementations are intentional. Bespoke strategies belong in the consuming app.
- Ask before broadening the configuration tree in `TenancyBundle`. Every option is an extra path to break.
- Ask before changing the listener priority (4) — it is calibrated to run after the security firewall, before the controller.

## Never

- **Never** depend on `Symfony\Component\Messenger\*`, `Lexik\*`, or any app-level package. Stay framework-light.
- **Never** import this package's `Infrastructure/` from `Domain/`.
- **Never** read or write the filter parameter from inside `Domain/`. That is the listener's job.
- **Never** assume a tenant is set. In Domain/Application code that may execute outside a request (workers, CLI), always go through `TenantContext::tryCurrent()` and handle the null case.

## Structure

```
src/
├── Domain/
│   ├── TenantId.php
│   ├── TenantOwned.php
│   ├── TenantContext.php
│   ├── TenantResolverInterface.php
│   └── Exception/TenantNotResolved.php
├── Infrastructure/
│   ├── Doctrine/TenantFilter.php
│   └── Resolver/
│       ├── JwtClaimTenantResolver.php
│       └── SubdomainTenantResolver.php
└── Symfony/
    ├── TenancyBundle.php
    ├── EventListener/ResolveTenantListener.php
    └── Resources/config/services.yaml
```

## Validation Commands

```bash
cd packages/tenancy-php
composer install
vendor/bin/phpstan analyse -c phpstan.dist.neon
vendor/bin/phpunit
```

## Filter Behaviour

`TenantFilter::addFilterConstraint` checks `implementsInterface(TenantOwned::class)` per entity and returns an empty string for non-tenant-owned entities — those pass through untouched. Doctrine guarantees the filter is invoked per JOIN, so a query that JOINs a `TenantOwned` table to a non-tenant table will scope the former and leave the latter alone.

## Tests

Unit tests cover the domain layer + resolvers. The Doctrine filter integration is exercised in the consuming app (a project that opts in writes the integration test against its actual schema).

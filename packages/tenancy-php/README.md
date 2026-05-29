# @jperdior/tenancy-php

Optional multi-tenancy add-on for the jperdior-template Symfony API.

The core template is **single-tenant**: no entity in `apps/api/code/src/` carries a `tenant_id` column. Projects that need multi-tenancy opt in by following the five steps below — no retrofitting required.

## What's in the box

| Layer | Class | Purpose |
|---|---|---|
| Domain | `Jperdior\Tenancy\Domain\TenantId` | UUID value object for tenant identity |
| Domain | `Jperdior\Tenancy\Domain\TenantOwned` | Marker interface; entities that implement it get filtered |
| Domain | `Jperdior\Tenancy\Domain\TenantContext` | Request-scoped holder for the current tenant |
| Domain | `Jperdior\Tenancy\Domain\TenantResolverInterface` | Strategy interface; one method `resolve(Request): ?TenantId` |
| Infra | `Jperdior\Tenancy\Infrastructure\Doctrine\TenantFilter` | Doctrine SQLFilter — appends `tenant_id = :current_tenant_id` to every entity that implements `TenantOwned` |
| Infra | `Jperdior\Tenancy\Infrastructure\Resolver\JwtClaimTenantResolver` | Reads a claim out of the request's JWT bearer token |
| Infra | `Jperdior\Tenancy\Infrastructure\Resolver\SubdomainTenantResolver` | Strips a base domain and treats the leading label as the tenant UUID |
| Symfony | `Jperdior\Tenancy\Symfony\TenancyBundle` | Wires context + resolver + listener; exposes config tree |
| Symfony | `Jperdior\Tenancy\Symfony\EventListener\ResolveTenantListener` | `kernel.request` listener that fills the context and enables the filter |

## Five-step opt-in

### 1. Require the package

```jsonc
// apps/api/composer.json
"require": {
  "jperdior/tenancy-php": "*"
}
```

The path repository is already declared in the template's `apps/api/composer.json`.

### 2. Register the bundle

```php
// apps/api/code/config/bundles.php
return [
    // ...
    Jperdior\Tenancy\Symfony\TenancyBundle::class => ['all' => true],
];
```

### 3. Mark entities and add the column

Implement `TenantOwned` on every aggregate that is scoped to a tenant, add a `tenant_id` field to the Doctrine XML mapping, and ship a migration that adds the column (UUID type, not-null, indexed):

```xml
<entity name="App\Note\Domain\Note" table="notes">
    <!-- existing fields ... -->
    <field name="tenantId" column="tenant_id" type="string" length="36"/>
    <indexes>
        <index name="idx_notes_tenant" columns="tenant_id"/>
    </indexes>
</entity>
```

```php
final class Note extends AggregateRoot implements TenantOwned
{
    // ...
}
```

### 4. Register the Doctrine SQL filter

```yaml
# apps/api/code/config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            tenant:
                class: Jperdior\Tenancy\Infrastructure\Doctrine\TenantFilter
                enabled: false   # the listener enables it per request
```

### 5. Choose a resolver

```yaml
# apps/api/code/config/packages/tenancy.yaml
tenancy:
    resolver: jwt_claim       # or 'subdomain'
    jwt_claim:
        claim_name: tenant_id
    subdomain:
        base_domain: api.example.com
```

The `JwtClaimTenantResolver` decodes the JWT payload from the `Authorization: Bearer …` header and reads `claim_name`. The `SubdomainTenantResolver` strips `base_domain` from the host and treats the remaining left label as the tenant UUID.

For any other resolution strategy (custom header, path segment, API-key lookup), implement `TenantResolverInterface` in your app and alias the interface to your service.

## Background jobs and admin tooling

The filter is enabled per-request. Long-running workers (Messenger consumers, console commands) typically run with no resolved tenant and the filter stays disabled — that is intentional. Code paths that must explicitly scope to a tenant should call `TenantContext::set()` themselves before running queries, then `disable('tenant')` after.

## Adding more resolvers

The interface contract is a single method:

```php
interface TenantResolverInterface
{
    public function resolve(Request $request): ?TenantId;
}
```

Return `null` to signal "no tenant for this request." Returning a `TenantId` causes the listener to fill the context and bind the filter parameter.

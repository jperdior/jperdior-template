<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Domain;

/**
 * Marker for Doctrine entities that are scoped to a tenant. Implementing this interface causes
 * {@see \Jperdior\Tenancy\Infrastructure\Doctrine\TenantFilter} to append a `tenant_id = :current_tenant_id`
 * predicate to every SELECT, UPDATE, and DELETE on the entity's table.
 *
 * Entities still need a `tenant_id` column and a Doctrine mapping for it. Tenancy is a
 * cross-cutting concern; this interface does not impose a getter signature.
 */
interface TenantOwned
{
}

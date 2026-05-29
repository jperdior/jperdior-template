<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Jperdior\Tenancy\Domain\TenantOwned;

/**
 * Appends `<alias>.tenant_id = :current_tenant_id` to every Doctrine SELECT/UPDATE/DELETE
 * touching an entity that implements {@see TenantOwned}. Entities that do not implement
 * the marker interface are returned unfiltered.
 *
 * Registration (Symfony):
 *   doctrine:
 *     orm:
 *       filters:
 *         tenant:
 *           class: Jperdior\Tenancy\Infrastructure\Doctrine\TenantFilter
 *           enabled: false
 *
 * The kernel.request listener enables the filter and binds `current_tenant_id` after the
 * resolver succeeds. Operations that must escape the filter (background jobs, admin tools)
 * disable it explicitly via `$em->getFilters()->disable('tenant')`.
 *
 * Column name (`tenant_id`) is the project default; override per-entity if needed by
 * subclassing this filter or remapping at the mapping layer.
 */
final class TenantFilter extends SQLFilter
{
    public const string PARAM_TENANT_ID = 'current_tenant_id';

    public const string COLUMN = 'tenant_id';

    /**
     * @param ClassMetadata<object> $targetEntity
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->getReflectionClass()?->implementsInterface(TenantOwned::class)) {
            return '';
        }

        return sprintf('%s.%s = %s', $targetTableAlias, self::COLUMN, $this->getParameter(self::PARAM_TENANT_ID));
    }
}

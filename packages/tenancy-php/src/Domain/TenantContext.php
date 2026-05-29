<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Domain;

use Jperdior\Tenancy\Domain\Exception\TenantNotResolved;

/**
 * Request-scoped holder for the active tenant. Filled by the kernel.request listener after
 * the {@see TenantResolverInterface} returns a TenantId. Consumers (the Doctrine SQLFilter,
 * query handlers that need to scope by tenant, etc.) read from here.
 *
 * Not thread-safe. Symfony request scope is fine.
 */
final class TenantContext
{
    private ?TenantId $current = null;

    public function set(TenantId $tenantId): void
    {
        $this->current = $tenantId;
    }

    public function clear(): void
    {
        $this->current = null;
    }

    public function hasTenant(): bool
    {
        return $this->current !== null;
    }

    public function current(): TenantId
    {
        return $this->current ?? throw TenantNotResolved::forCurrentRequest();
    }

    public function tryCurrent(): ?TenantId
    {
        return $this->current;
    }
}

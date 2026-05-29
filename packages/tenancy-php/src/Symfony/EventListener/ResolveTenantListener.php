<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Symfony\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Jperdior\Tenancy\Domain\TenantContext;
use Jperdior\Tenancy\Domain\TenantResolverInterface;
use Jperdior\Tenancy\Infrastructure\Doctrine\TenantFilter;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Runs at kernel.request (priority 4 — after the security firewall, before the controller) and:
 *
 *  1. Asks the configured {@see TenantResolverInterface} to resolve a TenantId.
 *  2. If resolved, sets it on {@see TenantContext} and enables the Doctrine `tenant` filter
 *     with `current_tenant_id` bound to the UUID string.
 *  3. If not resolved, the filter stays disabled — sub-requests, public endpoints, and admin
 *     tooling pass through untouched.
 *
 * The filter name is injected so projects can rename it via the bundle config.
 */
final readonly class ResolveTenantListener
{
    public function __construct(
        private TenantResolverInterface $resolver,
        private TenantContext $context,
        private ManagerRegistry $managerRegistry,
        private string $filterName,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $tenantId = $this->resolver->resolve($event->getRequest());
        if ($tenantId === null) {
            return;
        }

        $this->context->set($tenantId);

        $manager = $this->managerRegistry->getManager();
        if (!$manager instanceof EntityManagerInterface) {
            return;
        }

        $filters = $manager->getFilters();
        if (!$filters->has($this->filterName)) {
            return;
        }

        $filter = $filters->isEnabled($this->filterName)
            ? $filters->getFilter($this->filterName)
            : $filters->enable($this->filterName);

        $filter->setParameter(TenantFilter::PARAM_TENANT_ID, $tenantId->value);
    }
}

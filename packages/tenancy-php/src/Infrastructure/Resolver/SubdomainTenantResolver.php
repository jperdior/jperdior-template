<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Infrastructure\Resolver;

use Jperdior\Tenancy\Domain\TenantId;
use Jperdior\Tenancy\Domain\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the tenant from the request's host, stripping a configured base domain and using the
 * remaining left-hand label as the tenant identifier.
 *
 * Example with `base_domain = "api.example.com"`:
 *   host = "acme.api.example.com"  ->  label = "acme"
 *   host = "api.example.com"       ->  null   (no tenant subdomain)
 *
 * The label is interpreted as a UUID by default. Projects whose tenants are addressed by slug
 * should compose this resolver with a slug-to-UUID lookup (typically a repository) and emit
 * the resulting TenantId — the easiest way is to write a thin wrapper resolver.
 */
final readonly class SubdomainTenantResolver implements TenantResolverInterface
{
    public function __construct(private string $baseDomain)
    {
    }

    public function resolve(Request $request): ?TenantId
    {
        $host = $request->getHost();
        $suffix = '.'.$this->baseDomain;

        if (!str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));
        if ($label === '' || str_contains($label, '.')) {
            return null;
        }

        try {
            return TenantId::fromString($label);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}

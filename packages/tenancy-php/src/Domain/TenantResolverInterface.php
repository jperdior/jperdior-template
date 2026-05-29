<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Domain;

use Symfony\Component\HttpFoundation\Request;

/**
 * Strategy for resolving the active tenant from an HTTP request. The reference implementations
 * read a JWT claim or a subdomain; projects can implement this interface to plug in any other
 * source (header, path segment, session, database lookup keyed by API key, ...).
 *
 * Return `null` to signal "no tenant for this request" — the caller decides whether that is
 * an auth failure, an admin route, or a public endpoint.
 */
interface TenantResolverInterface
{
    public function resolve(Request $request): ?TenantId;
}

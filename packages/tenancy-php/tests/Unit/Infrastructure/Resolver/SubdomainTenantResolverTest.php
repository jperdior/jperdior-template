<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Tests\Unit\Infrastructure\Resolver;

use Jperdior\Tenancy\Infrastructure\Resolver\SubdomainTenantResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SubdomainTenantResolverTest extends TestCase
{
    public function testItReadsTheLeadingLabelAsTenantUuid(): void
    {
        $resolver = new SubdomainTenantResolver('api.example.com');
        $request  = Request::create('https://550e8400-e29b-41d4-a716-446655440000.api.example.com/');

        $tenant = $resolver->resolve($request);

        self::assertNotNull($tenant);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $tenant->value);
    }

    public function testItReturnsNullOnTheBareBaseDomain(): void
    {
        $resolver = new SubdomainTenantResolver('api.example.com');
        $request  = Request::create('https://api.example.com/');

        self::assertNull($resolver->resolve($request));
    }

    public function testItReturnsNullForAHostThatDoesNotMatchTheBase(): void
    {
        $resolver = new SubdomainTenantResolver('api.example.com');
        $request  = Request::create('https://tenant.other.com/');

        self::assertNull($resolver->resolve($request));
    }

    public function testItReturnsNullWhenTheLeadingLabelIsNotAUuid(): void
    {
        $resolver = new SubdomainTenantResolver('api.example.com');
        $request  = Request::create('https://acme.api.example.com/');

        self::assertNull($resolver->resolve($request));
    }

    public function testItReturnsNullForMultiLevelLeadingLabels(): void
    {
        $resolver = new SubdomainTenantResolver('api.example.com');
        $request  = Request::create('https://foo.bar.api.example.com/');

        self::assertNull($resolver->resolve($request));
    }
}

<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Tests\Unit\Infrastructure\Resolver;

use Jperdior\Tenancy\Infrastructure\Resolver\JwtClaimTenantResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class JwtClaimTenantResolverTest extends TestCase
{
    public function testItReadsTheConfiguredClaim(): void
    {
        $resolver = new JwtClaimTenantResolver('tenant_id');
        $request  = $this->requestWithJwtPayload(['tenant_id' => '550e8400-e29b-41d4-a716-446655440000']);

        $tenant = $resolver->resolve($request);

        self::assertNotNull($tenant);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $tenant->value);
    }

    public function testItReturnsNullWhenHeaderIsMissing(): void
    {
        $resolver = new JwtClaimTenantResolver();
        $request  = new Request();

        self::assertNull($resolver->resolve($request));
    }

    public function testItReturnsNullWhenSchemeIsNotBearer(): void
    {
        $resolver = new JwtClaimTenantResolver();
        $request  = new Request();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        self::assertNull($resolver->resolve($request));
    }

    public function testItReturnsNullWhenClaimIsAbsent(): void
    {
        $resolver = new JwtClaimTenantResolver('tenant_id');
        $request  = $this->requestWithJwtPayload(['sub' => 'someone']);

        self::assertNull($resolver->resolve($request));
    }

    public function testItReturnsNullWhenClaimIsNotAValidUuid(): void
    {
        $resolver = new JwtClaimTenantResolver('tenant_id');
        $request  = $this->requestWithJwtPayload(['tenant_id' => 'not-a-uuid']);

        self::assertNull($resolver->resolve($request));
    }

    public function testItReturnsNullForAMalformedJwt(): void
    {
        $resolver = new JwtClaimTenantResolver();
        $request  = new Request();
        $request->headers->set('Authorization', 'Bearer not.a.jwt.with.too.many.parts');

        self::assertNull($resolver->resolve($request));
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function requestWithJwtPayload(array $claims): Request
    {
        $header  = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $payload = self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $jwt     = $header.'.'.$payload.'.sig';

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.$jwt);

        return $request;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

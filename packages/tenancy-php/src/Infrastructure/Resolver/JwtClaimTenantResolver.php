<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Infrastructure\Resolver;

use Jperdior\Tenancy\Domain\TenantId;
use Jperdior\Tenancy\Domain\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads a configurable claim out of the JWT carried in the `Authorization: Bearer <token>` header.
 *
 * The resolver decodes the JWT payload (base64url) without verifying the signature; the assumption
 * is that the surrounding security firewall has already authenticated the token by the time this
 * resolver runs. Projects that need a different ordering must supply their own resolver.
 *
 * If the claim is absent, the header is missing, or the payload is unparsable, the resolver
 * returns null and the caller decides whether that is acceptable.
 */
final readonly class JwtClaimTenantResolver implements TenantResolverInterface
{
    public function __construct(private string $claim = 'tenant_id')
    {
    }

    public function resolve(Request $request): ?TenantId
    {
        $header = $request->headers->get('Authorization');
        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $jwt = substr($header, 7);
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = self::base64UrlDecode($parts[1]);
        if ($payload === null) {
            return null;
        }

        try {
            /** @var array<string, mixed> $claims */
            $claims = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $value = $claims[$this->claim] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return TenantId::fromString($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}

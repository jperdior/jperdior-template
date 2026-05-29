<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Tests\Unit\Domain;

use Jperdior\Tenancy\Domain\TenantId;
use PHPUnit\Framework\TestCase;

final class TenantIdTest extends TestCase
{
    public function testItAcceptsAValidUuid(): void
    {
        $tenant = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $tenant->value);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $tenant);
    }

    public function testItGeneratesARandomUuid(): void
    {
        $a = TenantId::random();
        $b = TenantId::random();

        self::assertNotSame($a->value, $b->value);
    }

    public function testItRejectsAnInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TenantId::fromString('not-a-uuid');
    }

    public function testTwoIdsWithTheSameValueAreEqual(): void
    {
        $a = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertTrue($a->equals($b));
    }
}

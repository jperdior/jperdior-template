<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Tests\Domain\ValueObject;

use Jperdior\SharedKernel\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final readonly class TestUuid extends Uuid
{
}

final class UuidTest extends TestCase
{
    public function testItAcceptsValidUuids(): void
    {
        $uuid = TestUuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->value);
    }

    public function testItRejectsInvalidUuids(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUuid::fromString('not-a-uuid');
    }

    public function testRandomProducesValidUuid(): void
    {
        $a = TestUuid::random();
        $b = TestUuid::random();

        self::assertNotSame($a->value, $b->value);
    }

    public function testEqualityOnSameClass(): void
    {
        $a = TestUuid::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = TestUuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertTrue($a->equals($b));
    }
}

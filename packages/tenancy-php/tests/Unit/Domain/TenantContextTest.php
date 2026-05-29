<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Tests\Unit\Domain;

use Jperdior\Tenancy\Domain\Exception\TenantNotResolved;
use Jperdior\Tenancy\Domain\TenantContext;
use Jperdior\Tenancy\Domain\TenantId;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function testItStartsEmpty(): void
    {
        $context = new TenantContext();

        self::assertFalse($context->hasTenant());
        self::assertNull($context->tryCurrent());
    }

    public function testCurrentThrowsWhenNoTenantSet(): void
    {
        $context = new TenantContext();

        $this->expectException(TenantNotResolved::class);
        $context->current();
    }

    public function testItRemembersWhatWasSet(): void
    {
        $context = new TenantContext();
        $tenant  = TenantId::random();

        $context->set($tenant);

        self::assertTrue($context->hasTenant());
        self::assertTrue($context->current()->equals($tenant));
    }

    public function testClearResetsTheContext(): void
    {
        $context = new TenantContext();
        $context->set(TenantId::random());
        $context->clear();

        self::assertFalse($context->hasTenant());
    }
}

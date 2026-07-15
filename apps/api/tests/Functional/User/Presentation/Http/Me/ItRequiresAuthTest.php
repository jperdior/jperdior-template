<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\Me;

final class ItRequiresAuthTest extends BaseMeTest
{
    protected function act(): void
    {
        $this->page->me();
    }

    protected function assert(): void
    {
        self::assertSame(401, $this->page->getStatusCode());
    }
}

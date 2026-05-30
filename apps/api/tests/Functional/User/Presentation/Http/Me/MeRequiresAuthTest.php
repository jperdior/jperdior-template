<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\Me;

final class MeRequiresAuthTest extends MeControllerTestCase
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

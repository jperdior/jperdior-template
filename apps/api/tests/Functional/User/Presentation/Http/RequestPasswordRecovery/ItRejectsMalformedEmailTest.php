<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\RequestPasswordRecovery;

final class ItRejectsMalformedEmailTest extends RequestPasswordRecoveryControllerTestCase
{
    protected function act(): void
    {
        $this->page->forgotPassword('not-an-email');
    }

    protected function assert(): void
    {
        self::assertSame(422, $this->page->getStatusCode());
    }
}

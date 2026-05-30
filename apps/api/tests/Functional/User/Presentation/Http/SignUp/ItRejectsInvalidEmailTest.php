<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\SignUp;

final class ItRejectsInvalidEmailTest extends SignUpControllerTestCase
{
    protected function act(): void
    {
        $this->page->signUp('not-an-email', 'secretpass');
    }

    protected function assert(): void
    {
        self::assertSame(422, $this->page->getStatusCode());
    }
}

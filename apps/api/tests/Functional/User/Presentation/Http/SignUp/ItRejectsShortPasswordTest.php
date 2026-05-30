<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\SignUp;

final class ItRejectsShortPasswordTest extends SignUpControllerTestCase
{
    protected function act(): void
    {
        $this->page->signUp('short@example.com', '123');
    }

    protected function assert(): void
    {
        self::assertSame(422, $this->page->getStatusCode());
    }
}

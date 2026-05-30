<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\SignUp;

final class ItRejectsDuplicateEmailTest extends SignUpControllerTestCase
{
    protected function arrange(): void
    {
        $this->userFixture()->createOne('dupe@example.com');
    }

    protected function act(): void
    {
        $this->page->signUp('dupe@example.com', 'secretpass');
    }

    protected function assert(): void
    {
        self::assertSame(409, $this->page->getStatusCode());
    }
}

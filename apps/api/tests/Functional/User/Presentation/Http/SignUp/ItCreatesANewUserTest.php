<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\SignUp;

final class ItCreatesANewUserTest extends BaseSignUpTest
{
    protected function act(): void
    {
        $this->page->signUp('newuser@example.com', 'secretpass');
    }

    protected function assert(): void
    {
        self::assertSame(201, $this->page->getStatusCode());
        self::assertArrayHasKey('id', $this->page->getResponseJson());
    }
}

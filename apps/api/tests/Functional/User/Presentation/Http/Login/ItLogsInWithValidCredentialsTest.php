<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\Login;

final class ItLogsInWithValidCredentialsTest extends LoginControllerTestCase
{
    protected function arrange(): void
    {
        $this->userFixture()->createOne('login@example.com', 'secretpass');
    }

    protected function act(): void
    {
        $this->page->login('login@example.com', 'secretpass');
    }

    protected function assert(): void
    {
        self::assertSame(200, $this->page->getStatusCode());
        $json = $this->page->getResponseJson();
        self::assertArrayHasKey('token', $json);
        self::assertArrayHasKey('refresh_token', $json);
    }
}

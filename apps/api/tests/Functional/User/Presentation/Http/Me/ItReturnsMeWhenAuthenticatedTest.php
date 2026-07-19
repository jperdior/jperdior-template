<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\Me;

final class ItReturnsMeWhenAuthenticatedTest extends BaseMeTest
{
    private string $token = '';

    protected function arrange(): void
    {
        $this->userFixture()->createOne('me@example.com', 'secretpass');
        $this->page->login('me@example.com', 'secretpass');
        $this->token = $this->page->extractToken();
    }

    protected function act(): void
    {
        $this->page->me($this->token);
    }

    protected function assert(): void
    {
        self::assertSame(200, $this->page->getStatusCode());
        $json = $this->page->getResponseJson();
        $roles = $json['roles'];
        \assert(\is_array($roles));
        self::assertSame('me@example.com', $json['email']);
        self::assertContains('ROLE_USER', $roles);
    }
}

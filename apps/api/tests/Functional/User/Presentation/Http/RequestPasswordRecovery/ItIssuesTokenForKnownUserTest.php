<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\RequestPasswordRecovery;

use App\User\Domain\User;

final class ItIssuesTokenForKnownUserTest extends BaseRequestPasswordRecoveryTest
{
    private User $user;

    protected function arrange(): void
    {
        $this->user = $this->userFixture()->createOne(email: 'forgot@example.com');
    }

    protected function act(): void
    {
        $this->page->forgotPassword('forgot@example.com');
    }

    protected function assert(): void
    {
        self::assertSame(204, $this->page->getStatusCode());
        self::assertSame(1, $this->tokens->countForUser($this->user->id()));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\ResetPasswordWithToken;

use App\User\Domain\User;

final class ItRejectsWeakPasswordTest extends ResetPasswordWithTokenControllerTestCase
{
    private User $user;
    private string $plainToken;

    protected function arrange(): void
    {
        $this->user = $this->userFixture()->createOne(email: 'weak@example.com');
        [, $plain] = $this->tokens->issueFor($this->user->id());
        $this->plainToken = $plain;
    }

    protected function act(): void
    {
        $this->page->resetPasswordWithToken($this->plainToken, 'short');
    }

    protected function assert(): void
    {
        self::assertSame(422, $this->page->getStatusCode());
    }
}

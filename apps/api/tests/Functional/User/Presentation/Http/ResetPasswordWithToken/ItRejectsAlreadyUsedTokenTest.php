<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\ResetPasswordWithToken;

use App\User\Domain\User;

final class ItRejectsAlreadyUsedTokenTest extends BaseResetPasswordWithTokenTest
{
    private User $user;
    private string $plainToken;

    protected function arrange(): void
    {
        $this->user = $this->userFixture()->createOne(email: 'used@example.com');
        [, $plain] = $this->tokens->issueUsedFor($this->user->id());
        $this->plainToken = $plain;
    }

    protected function act(): void
    {
        $this->page->resetPasswordWithToken($this->plainToken, 'newpassword1234');
    }

    protected function assert(): void
    {
        self::assertSame(422, $this->page->getStatusCode());
        self::assertSame('password_recovery_token_already_used', $this->page->getResponseJson()['code']);
    }
}

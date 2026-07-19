<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\ResetPasswordWithToken;

final class ItReturnsNotFoundForUnknownTokenTest extends BaseResetPasswordWithTokenTest
{
    protected function act(): void
    {
        $this->page->resetPasswordWithToken(str_repeat('a', 96), 'newpassword1234');
    }

    protected function assert(): void
    {
        self::assertSame(404, $this->page->getStatusCode());
        self::assertSame('password_recovery_token_not_found', $this->page->getResponseJson()['code']);
    }
}

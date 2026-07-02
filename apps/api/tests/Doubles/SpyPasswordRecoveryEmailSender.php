<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\User\Domain\Email;
use App\User\Domain\PasswordRecoveryEmailSender;

final class SpyPasswordRecoveryEmailSender implements PasswordRecoveryEmailSender
{
    /** @var list<array{to: Email, plainToken: string}> */
    public array $sent = [];

    public function send(Email $to, string $plainToken): void
    {
        $this->sent[] = ['to' => $to, 'plainToken' => $plainToken];
    }
}

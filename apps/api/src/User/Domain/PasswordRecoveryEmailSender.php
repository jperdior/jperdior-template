<?php

declare(strict_types=1);

namespace App\User\Domain;

interface PasswordRecoveryEmailSender
{
    public function send(Email $to, string $plainToken): void;
}

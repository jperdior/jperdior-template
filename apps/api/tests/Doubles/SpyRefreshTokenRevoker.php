<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\User\Domain\Email;
use App\User\Domain\RefreshTokenRevoker;

final class SpyRefreshTokenRevoker implements RefreshTokenRevoker
{
    /** @var list<Email> */
    public array $revoked = [];

    public function revokeAllFor(Email $email): void
    {
        $this->revoked[] = $email;
    }
}

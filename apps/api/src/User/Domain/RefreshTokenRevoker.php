<?php

declare(strict_types=1);

namespace App\User\Domain;

interface RefreshTokenRevoker
{
    /**
     * Revoke every stored refresh token bound to this user (their email is the Gesdinet username).
     */
    public function revokeAllFor(Email $email): void;
}

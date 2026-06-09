<?php

declare(strict_types=1);

namespace App\User\Domain;

use DateTimeImmutable;

interface PasswordRecoveryTokenRepository
{
    public function save(PasswordRecoveryToken $token): void;

    /**
     * Pessimistic lock when fetching for redemption to guarantee single-use under concurrency.
     */
    public function findByTokenHashForUpdate(string $tokenHash): ?PasswordRecoveryToken;

    /**
     * Supersede all unused recovery tokens for a user (sets used_at = $now) before a new one is issued.
     */
    public function markAllUnusedAsUsed(UserId $userId, DateTimeImmutable $now): void;
}

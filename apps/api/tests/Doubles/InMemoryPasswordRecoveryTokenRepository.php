<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\User\Domain\PasswordRecoveryToken;
use App\User\Domain\PasswordRecoveryTokenRepository;
use App\User\Domain\UserId;
use DateTimeImmutable;

final class InMemoryPasswordRecoveryTokenRepository implements PasswordRecoveryTokenRepository
{
    /** @var array<string, PasswordRecoveryToken> keyed by PasswordRecoveryTokenId->value */
    private array $tokens = [];

    public function save(PasswordRecoveryToken $token): void
    {
        $this->tokens[$token->id()->value] = $token;
    }

    public function findByTokenHashForUpdate(string $tokenHash): ?PasswordRecoveryToken
    {
        foreach ($this->tokens as $token) {
            if ($token->tokenHash() === $tokenHash) {
                return $token;
            }
        }

        return null;
    }

    public function markAllUnusedAsUsed(UserId $userId, DateTimeImmutable $now): void
    {
        foreach ($this->tokens as $token) {
            if ($token->userId()->equals($userId) && null === $token->usedAt()) {
                $token->markAsUsed($now);
            }
        }
    }

    /**
     * Test-assertion helper — not part of the PasswordRecoveryTokenRepository port.
     *
     * @return list<PasswordRecoveryToken>
     */
    public function allForUser(UserId $userId): array
    {
        return array_values(array_filter(
            $this->tokens,
            static fn (PasswordRecoveryToken $t) => $t->userId()->equals($userId),
        ));
    }
}

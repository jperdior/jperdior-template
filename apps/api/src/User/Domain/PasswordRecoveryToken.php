<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\User\Domain\Exception\PasswordRecoveryTokenAlreadyUsed;
use App\User\Domain\Exception\PasswordRecoveryTokenExpired;
use DateTimeImmutable;

final class PasswordRecoveryToken
{
    private function __construct(
        private readonly PasswordRecoveryTokenId $id,
        private readonly UserId $userId,
        private readonly string $tokenHash,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $usedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array{0: self, 1: string} [token, plainText] — plain returned once, never stored
     */
    public static function issue(UserId $userId, DateTimeImmutable $now): array
    {
        $plain = bin2hex(random_bytes(48));
        $token = new self(
            PasswordRecoveryTokenId::random(),
            $userId,
            hash('sha256', $plain),
            $now->modify('+1 hour'),
            null,
            $now,
        );

        return [$token, $plain];
    }

    public static function rehydrate(
        PasswordRecoveryTokenId $id,
        UserId $userId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $userId, $tokenHash, $expiresAt, $usedAt, $createdAt);
    }

    public function id(): PasswordRecoveryTokenId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function validate(DateTimeImmutable $now): void
    {
        if (null !== $this->usedAt) {
            throw new PasswordRecoveryTokenAlreadyUsed();
        }
        if ($now > $this->expiresAt) {
            throw new PasswordRecoveryTokenExpired();
        }
    }

    public function markAsUsed(DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }
}

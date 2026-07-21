<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'password_recovery_tokens')]
#[ORM\Index(name: 'idx_password_recovery_tokens_token_hash', columns: ['token_hash'])]
class PasswordRecoveryTokenModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    public string $id;

    #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
    public string $userId;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
    public string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    public DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;
}

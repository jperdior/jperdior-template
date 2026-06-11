<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Domain\Email;
use App\User\Domain\RefreshTokenRevoker;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GesdinetRefreshTokenRevoker implements RefreshTokenRevoker
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function revokeAllFor(Email $email): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(RefreshToken::class, 'rt')
            ->where('rt.username = :username')
            ->setParameter('username', $email->value)
            ->getQuery()
            ->execute();
    }
}

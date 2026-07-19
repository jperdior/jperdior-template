<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures;

use App\User\Domain\PasswordRecoveryToken;
use App\User\Domain\PasswordRecoveryTokenId;
use App\User\Domain\PasswordRecoveryTokenRepository;
use App\User\Domain\UserId;
use App\User\Infrastructure\Persistence\Doctrine\PasswordRecoveryTokenModel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class PasswordRecoveryTokenFixture
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordRecoveryTokenRepository $repository,
    ) {
    }

    /** @return array{0: PasswordRecoveryToken, 1: string} [token, plain] */
    public function issueFor(UserId $userId, ?DateTimeImmutable $issuedAt = null): array
    {
        [$token, $plain] = PasswordRecoveryToken::issue($userId, $issuedAt ?? new DateTimeImmutable());
        $this->repository->save($token);

        return [$token, $plain];
    }

    /** @return array{0: PasswordRecoveryToken, 1: string} [token, plain] — token already expired */
    public function issueExpiredFor(UserId $userId): array
    {
        $oneDayAgo = new DateTimeImmutable()->modify('-1 day');

        return $this->issueFor($userId, $oneDayAgo);
    }

    /** @return array{0: PasswordRecoveryToken, 1: string} [token, plain] — already used */
    public function issueUsedFor(UserId $userId): array
    {
        [$token, $plain] = $this->issueFor($userId);
        $token->markAsUsed(new DateTimeImmutable());
        $this->repository->save($token);

        return [$token, $plain];
    }

    public function countForUser(UserId $userId): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(PasswordRecoveryTokenModel::class, 't')
            ->where('t.userId = :uid')
            ->setParameter('uid', $userId->value);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findById(PasswordRecoveryTokenId $id): ?PasswordRecoveryTokenModel
    {
        return $this->em->find(PasswordRecoveryTokenModel::class, $id->value);
    }
}

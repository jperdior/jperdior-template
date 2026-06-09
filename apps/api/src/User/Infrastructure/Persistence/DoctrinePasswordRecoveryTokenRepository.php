<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence;

use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use App\User\Domain\PasswordRecoveryToken;
use App\User\Domain\PasswordRecoveryTokenId;
use App\User\Domain\PasswordRecoveryTokenRepository;
use App\User\Domain\UserId;
use App\User\Infrastructure\Persistence\Doctrine\PasswordRecoveryTokenModel;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;

final class DoctrinePasswordRecoveryTokenRepository extends DoctrineRepository implements PasswordRecoveryTokenRepository
{
    public function save(PasswordRecoveryToken $token): void
    {
        $existing = $this->entityManager()->find(PasswordRecoveryTokenModel::class, $token->id()->value);
        $this->persist($this->toOrm($token, $existing));
    }

    public function findByTokenHashForUpdate(string $tokenHash): ?PasswordRecoveryToken
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('t')
            ->from(PasswordRecoveryTokenModel::class, 't')
            ->where('t.tokenHash = :hash')
            ->setParameter('hash', $tokenHash)
            ->setMaxResults(1);

        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);

        /** @var PasswordRecoveryTokenModel|null $model */
        $model = $query->getOneOrNullResult();

        return null !== $model ? $this->toDomain($model) : null;
    }

    public function markAllUnusedAsUsed(UserId $userId, DateTimeImmutable $now): void
    {
        $this->entityManager()->createQueryBuilder()
            ->update(PasswordRecoveryTokenModel::class, 't')
            ->set('t.usedAt', ':now')
            ->where('t.userId = :userId')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('userId', $userId->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    private function toDomain(PasswordRecoveryTokenModel $m): PasswordRecoveryToken
    {
        return PasswordRecoveryToken::rehydrate(
            PasswordRecoveryTokenId::fromString($m->id),
            UserId::fromString($m->userId),
            $m->tokenHash,
            $m->expiresAt,
            $m->usedAt,
            $m->createdAt,
        );
    }

    private function toOrm(PasswordRecoveryToken $token, ?PasswordRecoveryTokenModel $existing = null): PasswordRecoveryTokenModel
    {
        $model = $existing ?? new PasswordRecoveryTokenModel();
        $model->id = $token->id()->value;
        $model->userId = $token->userId()->value;
        $model->tokenHash = $token->tokenHash();
        $model->expiresAt = $token->expiresAt();
        $model->usedAt = $token->usedAt();
        $model->createdAt = $token->createdAt();

        return $model;
    }
}

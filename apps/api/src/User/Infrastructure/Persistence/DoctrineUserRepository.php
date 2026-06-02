<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence;

use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use App\User\Domain\Email;
use App\User\Domain\HashedPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;
use App\User\Infrastructure\Persistence\Doctrine\UserModel;

final class DoctrineUserRepository extends DoctrineRepository implements UserRepository
{
    public function save(User $user): void
    {
        $existing = $this->entityManager()->find(UserModel::class, $user->id()->value);
        $this->persist($this->toOrm($user, $existing));
    }

    public function findById(UserId $id): ?User
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(UserModel::class, 'u')
            ->where('u.id = :id')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var UserModel|null $model */
        $model = $qb->getQuery()->getOneOrNullResult();

        return null !== $model ? $this->toDomain($model) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(UserModel::class, 'u')
            ->where('u.email = :email')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('email', $email->value)
            ->setMaxResults(1);

        /** @var UserModel|null $model */
        $model = $qb->getQuery()->getOneOrNullResult();

        return null !== $model ? $this->toDomain($model) : null;
    }

    public function findAll(int $limit, int $offset): array
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(UserModel::class, 'u')
            ->where('u.deletedAt IS NULL')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<UserModel> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map($this->toDomain(...), $rows);
    }

    public function countAll(): int
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('COUNT(u.id)')
            ->from(UserModel::class, 'u')
            ->where('u.deletedAt IS NULL');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByIdIncludingDeleted(UserId $id): ?User
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(UserModel::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var UserModel|null $model */
        $model = $qb->getQuery()->getOneOrNullResult();

        return null !== $model ? $this->toDomain($model) : null;
    }

    public function findAllIncludingDeleted(int $limit, int $offset): array
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(UserModel::class, 'u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<UserModel> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map($this->toDomain(...), $rows);
    }

    public function countAllIncludingDeleted(): int
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('COUNT(u.id)')
            ->from(UserModel::class, 'u');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function toDomain(UserModel $m): User
    {
        return User::rehydrate(
            UserId::fromString($m->id),
            new Email($m->email),
            new HashedPassword($m->password),
            array_map(Role::from(...), $m->roles),
            $m->createdAt,
            $m->mustResetPassword,
            $m->deletedAt,
        );
    }

    private function toOrm(User $user, ?UserModel $existing = null): UserModel
    {
        $model = $existing ?? new UserModel();
        $model->id = $user->id()->value;
        $model->email = $user->email()->value;
        $model->password = $user->password()->value;
        $model->roles = $user->roleStrings();
        $model->createdAt = $user->createdAt();
        $model->mustResetPassword = $user->mustResetPassword();
        $model->deletedAt = $user->deletedAt();

        return $model;
    }
}

<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence;

use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use App\User\Domain\Email;
use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

final class DoctrineUserRepository extends DoctrineRepository implements UserRepository
{
    public function save(User $user): void
    {
        $this->persist($user);
    }

    public function findById(UserId $id): ?User
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.id = :id')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByEmail(Email $email): ?User
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :email')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('email', $email->value)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAll(int $limit, int $offset): array
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.deletedAt IS NULL')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<User> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function countAll(): int
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.deletedAt IS NULL');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByIdIncludingDeleted(UserId $id): ?User
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllIncludingDeleted(int $limit, int $offset): array
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<User> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function countAllIncludingDeleted(): int
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('COUNT(u.id)')
            ->from(User::class, 'u');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}

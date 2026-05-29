<?php

declare(strict_types=1);

namespace App\Note\Infrastructure\Persistence;

use App\Note\Domain\Note;
use App\Note\Domain\NoteId;
use App\Note\Domain\NoteRepository;
use App\Note\Domain\OwnerId;
use App\Shared\Infrastructure\Doctrine\DoctrineRepository;

final class DoctrineNoteRepository extends DoctrineRepository implements NoteRepository
{
    public function save(Note $note): void
    {
        $this->persist($note);
    }

    public function remove(object $entity): void
    {
        parent::remove($entity);
    }

    public function findById(NoteId $id): ?Note
    {
        return $this->repository(Note::class)->find($id->value);
    }

    public function findByOwner(OwnerId $owner, int $limit, int $offset): array
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('n')
            ->from(Note::class, 'n')
            ->where('n.ownerId = :owner')
            ->orderBy('n.createdAt', 'DESC')
            ->setParameter('owner', $owner->value)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<Note> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function findAll(int $limit, int $offset): array
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('n')
            ->from(Note::class, 'n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var list<Note> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function countAll(): int
    {
        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('COUNT(n.id)')
            ->from(Note::class, 'n');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}

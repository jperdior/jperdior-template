<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Jperdior\SharedKernel\Domain\Repository\TransactionInterface;

final readonly class DoctrineTransaction implements TransactionInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function begin(): void
    {
        $this->em->getConnection()->beginTransaction();
    }

    public function commit(): void
    {
        $this->em->getConnection()->commit();
    }

    public function rollback(): void
    {
        $this->em->getConnection()->rollBack();
    }

    public function clear(): void
    {
        $this->em->clear();
    }
}

<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Repository;

interface TransactionInterface
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function clear(): void;
}

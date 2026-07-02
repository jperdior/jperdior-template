<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use Jperdior\SharedKernel\Domain\Repository\TransactionInterface;

/** No-op transaction for use-case unit tests — the in-memory repositories need no atomicity. */
final class NullTransaction implements TransactionInterface
{
    public function begin(): void
    {
    }

    public function commit(): void
    {
    }

    public function rollback(): void
    {
    }

    public function clear(): void
    {
    }
}

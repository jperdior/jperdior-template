<?php

declare(strict_types=1);

namespace App\User\Application\ListUsers;

use Jperdior\SharedKernel\Domain\Bus\Query\Query;

final readonly class ListUsersQuery implements Query
{
    public function __construct(
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}

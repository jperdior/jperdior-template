<?php

declare(strict_types=1);

namespace App\User\Application\ListUsers;

use Jperdior\SharedKernel\Domain\Bus\Query\QueryResponse;

final readonly class UserListResponse implements QueryResponse
{
    /** @param list<UserSummary> $users */
    public function __construct(
        public array $users,
        public int $total,
    ) {
    }
}

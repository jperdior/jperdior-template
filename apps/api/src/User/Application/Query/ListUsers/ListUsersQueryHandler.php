<?php

declare(strict_types=1);

namespace App\User\Application\Query\ListUsers;

use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class ListUsersQueryHandler implements QueryHandler
{
    public function __construct(private ListUsersUseCase $useCase)
    {
    }

    public function __invoke(ListUsersQuery $query): UserListResponse
    {
        return ($this->useCase)($query);
    }
}

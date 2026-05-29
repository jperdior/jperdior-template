<?php

declare(strict_types=1);

namespace App\User\Application\Query\ListUsers;

use App\User\Domain\UserRepository;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class ListUsersQueryHandler implements QueryHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(ListUsersQuery $query): UserListResponse
    {
        $rows  = $this->users->findAll(max(1, min(100, $query->limit)), max(0, $query->offset));
        $total = $this->users->countAll();

        $items = array_map(fn ($user) => new UserSummary(
            id: $user->id()->value,
            email: $user->email()->value,
            roles: $user->roleStrings(),
            createdAt: $user->createdAt()->format(\DateTimeInterface::ATOM),
        ), $rows);

        return new UserListResponse($items, $total);
    }
}

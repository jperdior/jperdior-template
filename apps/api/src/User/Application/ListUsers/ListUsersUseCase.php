<?php

declare(strict_types=1);

namespace App\User\Application\ListUsers;

use App\User\Domain\UserRepository;
use DateTimeInterface;

final class ListUsersUseCase
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(ListUsersQuery $query): UserListResponse
    {
        $rows = $this->users->findAllIncludingDeleted(max(1, min(100, $query->limit)), max(0, $query->offset));
        $total = $this->users->countAllIncludingDeleted();

        $items = array_map(static fn ($user) => new UserSummary(
            id: $user->id()->value,
            email: $user->email()->value,
            roles: $user->roleStrings(),
            createdAt: $user->createdAt()->format(DateTimeInterface::ATOM),
            mustResetPassword: $user->mustResetPassword(),
            deletedAt: $user->deletedAt()?->format(DateTimeInterface::ATOM),
        ), $rows);

        return new UserListResponse($items, $total);
    }
}

<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUserById;

use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;
use DateTimeInterface;

final class GetUserByIdUseCase
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(GetUserByIdQuery $query): UserDetailResponse
    {
        $user = $this->users->findByIdIncludingDeleted(UserId::fromString($query->id));

        if (null === $user) {
            throw UserNotFound::withId($query->id);
        }

        return new UserDetailResponse(
            id: $user->id()->value,
            email: $user->email()->value,
            roles: $user->roleStrings(),
            createdAt: $user->createdAt()->format(DateTimeInterface::ATOM),
            mustResetPassword: $user->mustResetPassword(),
            deletedAt: $user->deletedAt()?->format(DateTimeInterface::ATOM),
        );
    }
}

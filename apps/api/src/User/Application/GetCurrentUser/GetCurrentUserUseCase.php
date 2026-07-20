<?php

declare(strict_types=1);

namespace App\User\Application\GetCurrentUser;

use App\User\Domain\Email;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;
use DateTimeInterface;

final class GetCurrentUserUseCase
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(GetCurrentUserQuery $query): CurrentUserResponse
    {
        $email = new Email($query->email);
        $user = $this->users->findByEmail($email);

        if (null === $user) {
            throw UserNotFound::withEmail($email->value);
        }

        return new CurrentUserResponse(
            id: $user->id()->value,
            email: $user->email()->value,
            roles: $user->roleStrings(),
            createdAt: $user->createdAt()->format(DateTimeInterface::ATOM),
            mustResetPassword: $user->mustResetPassword(),
        );
    }
}

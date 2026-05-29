<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetCurrentUser;

use App\User\Domain\Email;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class GetCurrentUserQueryHandler implements QueryHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(GetCurrentUserQuery $query): CurrentUserResponse
    {
        $email = new Email($query->email);
        $user  = $this->users->findByEmail($email);

        if ($user === null) {
            throw UserNotFound::withEmail($email->value);
        }

        return new CurrentUserResponse(
            id: $user->id()->value,
            email: $user->email()->value,
            roles: $user->roleStrings(),
            createdAt: $user->createdAt()->format(\DateTimeInterface::ATOM),
        );
    }
}

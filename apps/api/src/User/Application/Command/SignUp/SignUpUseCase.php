<?php

declare(strict_types=1);

namespace App\User\Application\Command\SignUp;

use App\User\Domain\Email;
use App\User\Domain\Exception\UserAlreadyExists;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PlainPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final class SignUpUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasherInterface $hasher,
        private readonly EventBus $eventBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SignUpCommand $command): void
    {
        $email = new Email($command->email);

        if (null !== $this->users->findByEmail($email)) {
            throw UserAlreadyExists::withEmail($email->value);
        }

        $user = User::register(
            id: UserId::fromString($command->id),
            email: $email,
            password: $this->hasher->hash(new PlainPassword($command->plainPassword)),
            roles: [Role::USER],
            createdAt: $this->clock->now(),
        );

        $this->users->save($user);
        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}

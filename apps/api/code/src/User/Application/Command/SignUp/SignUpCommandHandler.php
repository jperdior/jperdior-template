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
use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final readonly class SignUpCommandHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasherInterface $hasher,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SignUpCommand $command): void
    {
        $email = new Email($command->email);

        if ($this->users->findByEmail($email) !== null) {
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

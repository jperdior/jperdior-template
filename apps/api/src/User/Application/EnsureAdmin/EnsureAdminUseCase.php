<?php

declare(strict_types=1);

namespace App\User\Application\EnsureAdmin;

use App\User\Domain\Email;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PlainPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

/**
 * Idempotent "make sure an admin exists": promote the user if present (leaving their
 * password untouched), otherwise create one with ROLE_USER + ROLE_ADMIN. Backs the
 * dev-only first-boot seeder.
 */
final class EnsureAdminUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasherInterface $hasher,
        private readonly EventBus $eventBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(EnsureAdminCommand $command): void
    {
        $email = new Email($command->email);

        $existing = $this->users->findByEmail($email);
        if (null !== $existing) {
            $existing->promoteToAdmin();
            $this->users->save($existing);

            return;
        }

        $user = User::register(
            id: UserId::random(),
            email: $email,
            password: $this->hasher->hash(new PlainPassword($command->plainPassword)),
            roles: [Role::USER, Role::ADMIN],
            createdAt: $this->clock->now(),
        );

        $this->users->save($user);
        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}

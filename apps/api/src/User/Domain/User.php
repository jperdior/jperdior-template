<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\User\Domain\Event\UserRegistered;
use Jperdior\SharedKernel\Domain\Aggregate\AggregateRoot;

final class User extends AggregateRoot
{
    /**
     * @param list<Role> $roles
     */
    private function __construct(
        private readonly UserId $id,
        private Email $email,
        private HashedPassword $password,
        private array $roles,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<Role> $roles
     */
    public static function register(
        UserId $id,
        Email $email,
        HashedPassword $password,
        array $roles,
        \DateTimeImmutable $createdAt,
    ): self {
        $user = new self($id, $email, $password, $roles ?: [Role::USER], $createdAt);
        $user->record(new UserRegistered(
            $id->value,
            $email->value,
            array_map(fn (Role $r) => $r->value, $user->roles),
            $createdAt->format(\DateTimeInterface::ATOM),
        ));

        return $user;
    }

    /**
     * Rehydration from persistence. Bypasses domain event recording.
     *
     * @param list<Role> $roles
     */
    public static function rehydrate(
        UserId $id,
        Email $email,
        HashedPassword $password,
        array $roles,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $email, $password, $roles, $createdAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    /** @return list<Role> */
    public function roles(): array
    {
        return $this->roles;
    }

    /** @return list<string> */
    public function roleStrings(): array
    {
        return array_map(static fn (Role $r) => $r->value, $this->roles);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function promoteToAdmin(): void
    {
        if (!in_array(Role::ADMIN, $this->roles, true)) {
            $this->roles[] = Role::ADMIN;
        }
    }

    public function changePassword(HashedPassword $newPassword): void
    {
        $this->password = $newPassword;
    }
}

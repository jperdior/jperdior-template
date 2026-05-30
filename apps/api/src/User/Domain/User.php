<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\User\Domain\Event\UserRegistered;
use DateTimeImmutable;
use DateTimeInterface;
use Jperdior\SharedKernel\Domain\Aggregate\AggregateRoot;

final class User extends AggregateRoot
{
    /**
     * Backing fields are primitive types so Doctrine can hydrate them directly.
     * Getters wrap them in value objects.
     *
     * @param list<string> $roles Role values e.g. ['ROLE_USER']
     */
    private function __construct(
        private readonly UserId $id,
        private Email $email,
        private HashedPassword $password,
        private array $roles,
        private readonly DateTimeImmutable $createdAt,
        private bool $mustResetPassword = false,
        private ?DateTimeImmutable $deletedAt = null,
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
        DateTimeImmutable $createdAt,
    ): self {
        $roleStrings = array_map(static fn (Role $r) => $r->value, $roles ?: [Role::USER]);
        $user = new self($id, $email, $password, $roleStrings, $createdAt);
        $user->record(new UserRegistered(
            $id->value,
            $email->value,
            $user->roles,
            $createdAt->format(DateTimeInterface::ATOM),
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
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $email, $password, array_map(static fn (Role $r) => $r->value, $roles), $createdAt);
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
        return array_map(static fn (string $r) => Role::from($r), $this->roles);
    }

    /** @return list<string> */
    public function roleStrings(): array
    {
        return $this->roles;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function mustResetPassword(): bool
    {
        return $this->mustResetPassword;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function promoteToAdmin(): void
    {
        if (!\in_array(Role::ADMIN->value, $this->roles, true)) {
            $this->roles[] = Role::ADMIN->value;
        }
    }

    public function demoteFromAdmin(): void
    {
        $this->roles = array_values(array_filter($this->roles, fn (string $r) => $r !== Role::ADMIN->value));
    }

    public function forcePasswordReset(): void
    {
        $this->mustResetPassword = true;
    }

    public function clearPasswordReset(): void
    {
        $this->mustResetPassword = false;
    }

    public function changePassword(HashedPassword $newPassword): void
    {
        $this->password = $newPassword;
        $this->mustResetPassword = false;
    }

    public function softDelete(DateTimeImmutable $at): void
    {
        $this->deletedAt = $at;
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }
}

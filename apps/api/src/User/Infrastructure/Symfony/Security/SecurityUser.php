<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Symfony\Security;

use App\User\Domain\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Symfony Security adapter that wraps a snapshot of an immutable identity. NOT the domain User.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly string $id,
        private readonly string $email,
        private readonly string $password,
        private readonly array $roles,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self($user->id()->value, $user->email()->value, $user->password()->value, $user->roleStrings());
    }

    public static function placeholder(): self
    {
        return new self('00000000-0000-0000-0000-000000000000', 'placeholder@example.com', '', ['ROLE_USER']);
    }

    public static function placeholderWithHash(string $hash): self
    {
        return new self('00000000-0000-0000-0000-000000000000', 'placeholder@example.com', $hash, ['ROLE_USER']);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }
}

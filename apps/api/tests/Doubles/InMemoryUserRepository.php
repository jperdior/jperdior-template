<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\User\Domain\Email;
use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

/**
 * Array-backed adapter for use-case unit tests. Replicates the Doctrine adapter's
 * query invariants: find/findAll/countAll exclude soft-deleted users (the
 * *IncludingDeleted variants do not), and listing orders by createdAt DESC.
 */
final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> keyed by UserId->value */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->value] = $user;
    }

    public function findById(UserId $id): ?User
    {
        $user = $this->users[$id->value] ?? null;

        return null === $user || $user->isDeleted() ? null : $user;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email) && !$user->isDeleted()) {
                return $user;
            }
        }

        return null;
    }

    public function findAll(int $limit, int $offset): array
    {
        $active = array_values(array_filter($this->users, static fn (User $u) => !$u->isDeleted()));

        return \array_slice($this->sortByCreatedAtDesc($active), $offset, $limit);
    }

    public function countAll(): int
    {
        return \count(array_filter($this->users, static fn (User $u) => !$u->isDeleted()));
    }

    public function findByIdIncludingDeleted(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    public function findAllIncludingDeleted(int $limit, int $offset): array
    {
        return \array_slice($this->sortByCreatedAtDesc(array_values($this->users)), $offset, $limit);
    }

    public function countAllIncludingDeleted(): int
    {
        return \count($this->users);
    }

    /**
     * @param list<User> $users
     *
     * @return list<User>
     */
    private function sortByCreatedAtDesc(array $users): array
    {
        usort($users, static fn (User $a, User $b) => $b->createdAt() <=> $a->createdAt());

        return $users;
    }
}

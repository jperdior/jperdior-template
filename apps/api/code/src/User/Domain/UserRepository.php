<?php

declare(strict_types=1);

namespace App\User\Domain;

interface UserRepository
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    /** @return list<User> */
    public function findAll(int $limit, int $offset): array;

    public function countAll(): int;
}

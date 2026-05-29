<?php

declare(strict_types=1);

namespace App\User\Application\Query\ListUsers;

final readonly class UserSummary
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public string $createdAt,
    ) {
    }
}

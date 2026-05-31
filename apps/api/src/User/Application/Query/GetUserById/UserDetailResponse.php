<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUserById;

use Jperdior\SharedKernel\Domain\Bus\Query\QueryResponse;

final readonly class UserDetailResponse implements QueryResponse
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public string $createdAt,
        public bool $mustResetPassword,
        public ?string $deletedAt,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\User\Presentation\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateUserRolesRequest
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[Assert\NotNull]
        public array $roles,
    ) {
    }
}

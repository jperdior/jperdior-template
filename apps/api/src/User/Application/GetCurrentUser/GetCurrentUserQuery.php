<?php

declare(strict_types=1);

namespace App\User\Application\GetCurrentUser;

use Jperdior\SharedKernel\Domain\Bus\Query\Query;

final readonly class GetCurrentUserQuery implements Query
{
    public function __construct(public string $email)
    {
    }
}

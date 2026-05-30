<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUserById;

use Jperdior\SharedKernel\Domain\Bus\Query\Query;

final readonly class GetUserByIdQuery implements Query
{
    public function __construct(public string $id)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\User\Application\GetCurrentUser;

use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class GetCurrentUserQueryHandler implements QueryHandler
{
    public function __construct(private GetCurrentUserUseCase $useCase)
    {
    }

    public function __invoke(GetCurrentUserQuery $query): CurrentUserResponse
    {
        return ($this->useCase)($query);
    }
}

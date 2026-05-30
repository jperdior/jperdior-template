<?php

declare(strict_types=1);

namespace App\User\Application\Query\GetUserById;

use Jperdior\SharedKernel\Domain\Bus\Query\QueryHandler;

final readonly class GetUserByIdQueryHandler implements QueryHandler
{
    public function __construct(private GetUserByIdUseCase $useCase)
    {
    }

    public function __invoke(GetUserByIdQuery $query): UserDetailResponse
    {
        return ($this->useCase)($query);
    }
}

<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Bus\Query;

interface QueryBus
{
    public function ask(Query $query): ?QueryResponse;
}

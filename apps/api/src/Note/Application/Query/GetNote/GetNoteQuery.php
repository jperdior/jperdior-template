<?php

declare(strict_types=1);

namespace App\Note\Application\Query\GetNote;

use Jperdior\SharedKernel\Domain\Bus\Query\Query;

final readonly class GetNoteQuery implements Query
{
    public function __construct(
        public string $id,
        public string $requesterId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Note\Application\Query\ListAllNotes;

use Jperdior\SharedKernel\Domain\Bus\Query\Query;

final readonly class ListAllNotesQuery implements Query
{
    public function __construct(
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}

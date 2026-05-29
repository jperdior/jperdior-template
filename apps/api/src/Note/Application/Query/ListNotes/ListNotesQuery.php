<?php

declare(strict_types=1);

namespace App\Note\Application\Query\ListNotes;

use Jperdior\SharedKernel\Domain\Bus\Query\Query;

final readonly class ListNotesQuery implements Query
{
    public function __construct(
        public string $ownerId,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}

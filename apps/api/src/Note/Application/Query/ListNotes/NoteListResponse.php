<?php

declare(strict_types=1);

namespace App\Note\Application\Query\ListNotes;

use App\Note\Application\Query\GetNote\NoteResponse;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryResponse;

final readonly class NoteListResponse implements QueryResponse
{
    /** @param list<NoteResponse> $notes */
    public function __construct(
        public array $notes,
        public int $total,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Note\Application\Query\GetNote;

use Jperdior\SharedKernel\Domain\Bus\Query\QueryResponse;

final readonly class NoteResponse implements QueryResponse
{
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $title,
        public string $body,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}

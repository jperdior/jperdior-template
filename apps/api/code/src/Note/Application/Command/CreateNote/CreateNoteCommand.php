<?php

declare(strict_types=1);

namespace App\Note\Application\Command\CreateNote;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class CreateNoteCommand implements Command
{
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $title,
        public string $body,
    ) {
    }
}

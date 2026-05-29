<?php

declare(strict_types=1);

namespace App\Note\Application\Command\UpdateNote;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class UpdateNoteCommand implements Command
{
    public function __construct(
        public string $id,
        public string $editorId,
        public string $title,
        public string $body,
    ) {
    }
}

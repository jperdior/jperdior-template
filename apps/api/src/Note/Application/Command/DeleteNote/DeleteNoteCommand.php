<?php

declare(strict_types=1);

namespace App\Note\Application\Command\DeleteNote;

use Jperdior\SharedKernel\Domain\Bus\Command\Command;

final readonly class DeleteNoteCommand implements Command
{
    public function __construct(
        public string $id,
        public string $editorId,
    ) {
    }
}

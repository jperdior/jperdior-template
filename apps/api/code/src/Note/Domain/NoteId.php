<?php

declare(strict_types=1);

namespace App\Note\Domain;

use Jperdior\SharedKernel\Domain\ValueObject\Uuid;

final readonly class NoteId extends Uuid
{
}

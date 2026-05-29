<?php

declare(strict_types=1);

namespace App\Note\Domain;

use Jperdior\SharedKernel\Domain\ValueObject\Uuid;

/**
 * Identity of the user who owns the note. Modelled inside the Note context to keep the
 * boundary intact — Note never imports App\User\Domain\UserId.
 */
final readonly class OwnerId extends Uuid
{
}

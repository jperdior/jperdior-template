<?php

declare(strict_types=1);

namespace App\Note\Domain\Exception;

final class NoteNotOwnedByUser extends \DomainException
{
    public function __construct(string $noteId, string $userId)
    {
        parent::__construct(sprintf('Note %s is not owned by user %s.', $noteId, $userId));
    }
}

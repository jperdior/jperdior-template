<?php

declare(strict_types=1);

namespace App\Note\Domain\Exception;

final class NoteNotFound extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Note %s not found.', $id));
    }
}

<?php

declare(strict_types=1);

namespace App\Note\Domain;

use Jperdior\SharedKernel\Domain\ValueObject\StringValueObject;

final readonly class NoteTitle extends StringValueObject
{
    public const int MAX_LENGTH = 200;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Note title cannot be empty.');
        }
        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Note title cannot exceed %d characters.', self::MAX_LENGTH));
        }
        parent::__construct($trimmed);
    }
}

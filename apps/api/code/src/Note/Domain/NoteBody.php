<?php

declare(strict_types=1);

namespace App\Note\Domain;

use Jperdior\SharedKernel\Domain\ValueObject\StringValueObject;

final readonly class NoteBody extends StringValueObject
{
    public const int MAX_LENGTH = 10_000;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Note body cannot be empty.');
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Note body cannot exceed %d characters.', self::MAX_LENGTH));
        }
        parent::__construct($value);
    }
}

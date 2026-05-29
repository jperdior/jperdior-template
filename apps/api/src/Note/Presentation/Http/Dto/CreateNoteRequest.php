<?php

declare(strict_types=1);

namespace App\Note\Presentation\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateNoteRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,

        #[Assert\NotBlank]
        #[Assert\Length(max: 10_000)]
        public string $body,
    ) {
    }
}

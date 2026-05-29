<?php

declare(strict_types=1);

namespace App\User\Presentation\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SignUpRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 4096)]
        public string $password,
    ) {
    }
}

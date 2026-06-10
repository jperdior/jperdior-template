<?php

declare(strict_types=1);

namespace App\User\Presentation\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResetPasswordWithTokenRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-f0-9]{96}$/')]
        public string $token,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 4096)]
        public string $newPassword,
    ) {
    }
}

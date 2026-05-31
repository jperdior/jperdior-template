<?php

declare(strict_types=1);

namespace App\User\Presentation\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SelfResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public string $newPassword,
    ) {
    }
}

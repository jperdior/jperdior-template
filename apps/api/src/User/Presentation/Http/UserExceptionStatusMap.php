<?php

declare(strict_types=1);

namespace App\User\Presentation\Http;

use App\Shared\Presentation\Http\ExceptionStatusMapProvider;
use App\User\Domain\Exception\PasswordRecoveryTokenAlreadyUsed;
use App\User\Domain\Exception\PasswordRecoveryTokenExpired;
use App\User\Domain\Exception\PasswordRecoveryTokenNotFound;

/**
 * Statuses that differ from the generic DomainException→409 fallback. The 404/422 split
 * is safe here because the recovery token is a 96-hex-char bearer secret behind a
 * 10/min/IP rate limit — do not copy this split for low-entropy identifiers.
 */
final readonly class UserExceptionStatusMap implements ExceptionStatusMapProvider
{
    public function map(): array
    {
        return [
            PasswordRecoveryTokenNotFound::class => [
                'status' => 404,
                'code' => 'password_recovery_token_not_found',
                'message' => 'Token not found.',
            ],
            PasswordRecoveryTokenExpired::class => [
                'status' => 422,
                'code' => 'password_recovery_token_expired',
                'message' => 'Token expired.',
            ],
            PasswordRecoveryTokenAlreadyUsed::class => [
                'status' => 422,
                'code' => 'password_recovery_token_already_used',
                'message' => 'Token already used.',
            ],
        ];
    }
}

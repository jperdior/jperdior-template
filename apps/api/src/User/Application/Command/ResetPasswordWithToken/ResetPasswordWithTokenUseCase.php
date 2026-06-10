<?php

declare(strict_types=1);

namespace App\User\Application\Command\ResetPasswordWithToken;

use App\User\Domain\Exception\PasswordRecoveryTokenNotFound;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PasswordRecoveryTokenRepository;
use App\User\Domain\PlainPassword;
use App\User\Domain\RefreshTokenRevoker;
use App\User\Domain\UserRepository;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final class ResetPasswordWithTokenUseCase
{
    public function __construct(
        private readonly PasswordRecoveryTokenRepository $tokens,
        private readonly UserRepository $users,
        private readonly PasswordHasherInterface $hasher,
        private readonly RefreshTokenRevoker $refreshTokenRevoker,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ResetPasswordWithTokenCommand $command): void
    {
        $hash = hash('sha256', $command->token);
        $token = $this->tokens->findByTokenHashForUpdate($hash);
        if (null === $token) {
            throw PasswordRecoveryTokenNotFound::create();
        }

        $now = $this->clock->now();
        $token->validate($now);

        $user = $this->users->findById($token->userId());
        if (null === $user) {
            // Orphaned token (user was deleted between issue and redemption).
            throw PasswordRecoveryTokenNotFound::create();
        }

        $user->changePassword($this->hasher->hash(new PlainPassword($command->newPassword)));
        $token->markAsUsed($now);

        $this->users->save($user);
        $this->tokens->save($token);

        // Defence in depth: any refresh token bound to this user (e.g. phished prior to recovery)
        // must be invalidated. Gesdinet refresh tokens are keyed by username (= email).
        $this->refreshTokenRevoker->revokeAllFor($user->email());
    }
}

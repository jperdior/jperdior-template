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
use Jperdior\SharedKernel\Domain\Repository\TransactionInterface;
use Throwable;

final class ResetPasswordWithTokenUseCase
{
    public function __construct(
        private readonly PasswordRecoveryTokenRepository $tokens,
        private readonly UserRepository $users,
        private readonly PasswordHasherInterface $hasher,
        private readonly RefreshTokenRevoker $refreshTokenRevoker,
        private readonly ClockInterface $clock,
        private readonly TransactionInterface $transaction,
    ) {
    }

    public function __invoke(ResetPasswordWithTokenCommand $command): void
    {
        // PESSIMISTIC_WRITE inside findByTokenHashForUpdate requires an active transaction.
        // In tests the FunctionalTestCase wraps each test in one — in dev/prod we open our own.
        $this->transaction->begin();
        try {
            $hash = hash('sha256', $command->token);
            $token = $this->tokens->findByTokenHashForUpdate($hash);
            if (null === $token) {
                throw PasswordRecoveryTokenNotFound::create();
            }

            $now = $this->clock->now();
            $token->validate($now);

            $user = $this->users->findById($token->userId());
            if (null === $user) {
                throw PasswordRecoveryTokenNotFound::create();
            }

            $user->changePassword($this->hasher->hash(new PlainPassword($command->newPassword)));
            $token->markAsUsed($now);

            $this->users->save($user);
            $this->tokens->save($token);

            // Defence in depth: any refresh token bound to this user (e.g. phished prior to
            // recovery) must be invalidated. Gesdinet refresh tokens are keyed by username
            // (= email).
            $this->refreshTokenRevoker->revokeAllFor($user->email());

            $this->transaction->commit();
        } catch (Throwable $e) {
            $this->transaction->rollback();
            throw $e;
        }
    }
}

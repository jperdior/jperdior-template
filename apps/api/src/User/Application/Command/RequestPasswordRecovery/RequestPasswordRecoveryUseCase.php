<?php

declare(strict_types=1);

namespace App\User\Application\Command\RequestPasswordRecovery;

use App\User\Domain\Email;
use App\User\Domain\PasswordRecoveryEmailSender;
use App\User\Domain\PasswordRecoveryToken;
use App\User\Domain\PasswordRecoveryTokenRepository;
use App\User\Domain\UserRepository;
use InvalidArgumentException;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final class RequestPasswordRecoveryUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordRecoveryTokenRepository $tokens,
        private readonly PasswordRecoveryEmailSender $emailSender,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestPasswordRecoveryCommand $command): void
    {
        // Wrap Email construction so a malformed value never breaks BR-U05 (silent success).
        // DTO validation should already reject this — this is the defence-in-depth case.
        try {
            $email = new Email($command->email);
        } catch (InvalidArgumentException) {
            return;
        }

        $user = $this->users->findByEmail($email);
        if (null === $user) {
            return;
        }

        $now = $this->clock->now();

        // BR-U04: at most one active token per user — supersede prior unused tokens.
        $this->tokens->markAllUnusedAsUsed($user->id(), $now);

        [$token, $plain] = PasswordRecoveryToken::issue($user->id(), $now);
        $this->tokens->save($token);

        $this->emailSender->send($email, $plain);
    }
}

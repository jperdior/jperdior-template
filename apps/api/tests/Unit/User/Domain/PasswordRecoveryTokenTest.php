<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain;

use App\User\Domain\Exception\PasswordRecoveryTokenAlreadyUsed;
use App\User\Domain\Exception\PasswordRecoveryTokenExpired;
use App\User\Domain\PasswordRecoveryToken;
use App\User\Domain\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PasswordRecoveryTokenTest extends TestCase
{
    private const NOW = '2026-07-02T10:00:00+00:00';

    public function testIssueReturnsHashedTokenAndPlainTextWithOneHourTtl(): void
    {
        $userId = UserId::random();
        $now = new DateTimeImmutable(self::NOW);

        [$token, $plain] = PasswordRecoveryToken::issue($userId, $now);

        self::assertMatchesRegularExpression('/^[a-f0-9]{96}$/', $plain);
        self::assertSame(hash('sha256', $plain), $token->tokenHash());
        self::assertTrue($userId->equals($token->userId()));
        self::assertNull($token->usedAt());
        self::assertEquals($now->modify('+1 hour'), $token->expiresAt());
    }

    public function testValidatePassesForFreshToken(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        [$token] = PasswordRecoveryToken::issue(UserId::random(), $now);

        $token->validate($now->modify('+59 minutes'));

        self::assertNull($token->usedAt());
    }

    public function testValidateThrowsWhenExpired(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        [$token] = PasswordRecoveryToken::issue(UserId::random(), $now);

        $this->expectException(PasswordRecoveryTokenExpired::class);
        $token->validate($now->modify('+61 minutes'));
    }

    public function testValidateThrowsWhenAlreadyUsed(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        [$token] = PasswordRecoveryToken::issue(UserId::random(), $now);
        $token->markAsUsed($now->modify('+5 minutes'));

        $this->expectException(PasswordRecoveryTokenAlreadyUsed::class);
        $token->validate($now->modify('+10 minutes'));
    }
}

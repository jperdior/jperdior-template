<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Tests\Doubles\FakePasswordHasher;
use App\Tests\Doubles\InMemoryPasswordRecoveryTokenRepository;
use App\Tests\Doubles\InMemoryUserRepository;
use App\Tests\Doubles\NullTransaction;
use App\Tests\Doubles\SpyRefreshTokenRevoker;
use App\User\Application\Command\ResetPasswordWithToken\ResetPasswordWithTokenCommand;
use App\User\Application\Command\ResetPasswordWithToken\ResetPasswordWithTokenUseCase;
use App\User\Domain\Email;
use App\User\Domain\Exception\PasswordRecoveryTokenAlreadyUsed;
use App\User\Domain\Exception\PasswordRecoveryTokenExpired;
use App\User\Domain\Exception\PasswordRecoveryTokenNotFound;
use App\User\Domain\PasswordRecoveryToken;
use App\User\Domain\PlainPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use DateInterval;
use DateTimeImmutable;
use Jperdior\SharedKernel\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class ResetPasswordWithTokenUseCaseTest extends TestCase
{
    private const NOW = '2026-07-02T10:00:00+00:00';

    private InMemoryUserRepository $users;
    private InMemoryPasswordRecoveryTokenRepository $tokens;
    private SpyRefreshTokenRevoker $revoker;
    private FrozenClock $clock;
    private FakePasswordHasher $hasher;
    private ResetPasswordWithTokenUseCase $useCase;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->tokens = new InMemoryPasswordRecoveryTokenRepository();
        $this->revoker = new SpyRefreshTokenRevoker();
        $this->clock = new FrozenClock(new DateTimeImmutable(self::NOW));
        $this->hasher = new FakePasswordHasher();
        $this->useCase = new ResetPasswordWithTokenUseCase(
            $this->tokens,
            $this->users,
            $this->hasher,
            $this->revoker,
            $this->clock,
            new NullTransaction(),
        );
    }

    public function testHappyPathChangesPasswordMarksTokenUsedAndRevokesRefreshTokens(): void
    {
        [$user, $plain] = $this->userWithIssuedToken();

        ($this->useCase)(new ResetPasswordWithTokenCommand(token: $plain, newPassword: 'brand-new-pass'));

        self::assertTrue($this->hasher->verify($user->password(), new PlainPassword('brand-new-pass')));
        self::assertNotNull($this->tokens->allForUser($user->id())[0]->usedAt());
        self::assertCount(1, $this->revoker->revoked);
        self::assertSame($user->email()->value, $this->revoker->revoked[0]->value);
    }

    public function testUnknownTokenThrowsNotFound(): void
    {
        $this->expectException(PasswordRecoveryTokenNotFound::class);
        ($this->useCase)(new ResetPasswordWithTokenCommand(token: str_repeat('a', 96), newPassword: 'brand-new-pass'));
    }

    public function testExpiredTokenThrows(): void
    {
        [, $plain] = $this->userWithIssuedToken();
        $this->clock->travel(new DateInterval('PT2H'));

        $this->expectException(PasswordRecoveryTokenExpired::class);
        ($this->useCase)(new ResetPasswordWithTokenCommand(token: $plain, newPassword: 'brand-new-pass'));
    }

    public function testAlreadyUsedTokenThrows(): void
    {
        [$user, $plain] = $this->userWithIssuedToken();
        ($this->useCase)(new ResetPasswordWithTokenCommand(token: $plain, newPassword: 'brand-new-pass'));

        $this->expectException(PasswordRecoveryTokenAlreadyUsed::class);
        ($this->useCase)(new ResetPasswordWithTokenCommand(token: $plain, newPassword: 'other-new-pass'));
    }

    public function testTokenForMissingUserThrowsNotFound(): void
    {
        [$token, $plain] = PasswordRecoveryToken::issue(UserId::random(), new DateTimeImmutable(self::NOW));
        $this->tokens->save($token);

        $this->expectException(PasswordRecoveryTokenNotFound::class);
        ($this->useCase)(new ResetPasswordWithTokenCommand(token: $plain, newPassword: 'brand-new-pass'));
    }

    /** @return array{0: User, 1: string} */
    private function userWithIssuedToken(): array
    {
        $user = User::register(
            UserId::random(),
            new Email('reset@example.com'),
            $this->hasher->hash(new PlainPassword('old-password')),
            [Role::USER],
            new DateTimeImmutable(self::NOW),
        );
        $this->users->save($user);

        [$token, $plain] = PasswordRecoveryToken::issue($user->id(), new DateTimeImmutable(self::NOW));
        $this->tokens->save($token);

        return [$user, $plain];
    }
}

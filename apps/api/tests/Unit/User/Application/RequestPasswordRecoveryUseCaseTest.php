<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Tests\Doubles\FakePasswordHasher;
use App\Tests\Doubles\InMemoryPasswordRecoveryTokenRepository;
use App\Tests\Doubles\InMemoryUserRepository;
use App\Tests\Doubles\SpyPasswordRecoveryEmailSender;
use App\User\Application\RequestPasswordRecovery\RequestPasswordRecoveryCommand;
use App\User\Application\RequestPasswordRecovery\RequestPasswordRecoveryUseCase;
use App\User\Domain\Email;
use App\User\Domain\PasswordRecoveryToken;
use App\User\Domain\PlainPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use DateTimeImmutable;
use Jperdior\SharedKernel\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class RequestPasswordRecoveryUseCaseTest extends TestCase
{
    private const NOW = '2026-07-02T10:00:00+00:00';

    private InMemoryUserRepository $users;
    private InMemoryPasswordRecoveryTokenRepository $tokens;
    private SpyPasswordRecoveryEmailSender $emails;
    private RequestPasswordRecoveryUseCase $useCase;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->tokens = new InMemoryPasswordRecoveryTokenRepository();
        $this->emails = new SpyPasswordRecoveryEmailSender();
        $this->useCase = new RequestPasswordRecoveryUseCase(
            $this->users,
            $this->tokens,
            $this->emails,
            new FrozenClock(new DateTimeImmutable(self::NOW)),
        );
    }

    public function testKnownEmailIssuesTokenAndSendsIt(): void
    {
        $user = $this->aUser('known@example.com');

        ($this->useCase)(new RequestPasswordRecoveryCommand(email: 'known@example.com'));

        $issued = $this->tokens->allForUser($user->id());
        self::assertCount(1, $issued);
        self::assertNull($issued[0]->usedAt());

        self::assertCount(1, $this->emails->sent);
        self::assertSame('known@example.com', $this->emails->sent[0]['to']->value);
        self::assertSame(hash('sha256', $this->emails->sent[0]['plainToken']), $issued[0]->tokenHash());
    }

    public function testPriorUnusedTokensAreSupersededOnReissue(): void
    {
        $user = $this->aUser('known@example.com');
        [$prior] = PasswordRecoveryToken::issue($user->id(), new DateTimeImmutable(self::NOW));
        $this->tokens->save($prior);

        ($this->useCase)(new RequestPasswordRecoveryCommand(email: 'known@example.com'));

        self::assertNotNull($prior->usedAt(), 'prior unused token is superseded (BR-U04)');
        $active = array_filter($this->tokens->allForUser($user->id()), static fn ($t) => null === $t->usedAt());
        self::assertCount(1, $active);
    }

    public function testUnknownEmailIsSilentlyIgnored(): void
    {
        ($this->useCase)(new RequestPasswordRecoveryCommand(email: 'nobody@example.com'));

        self::assertSame([], $this->emails->sent, 'BR-U05: no signal about account existence');
    }

    public function testMalformedEmailIsSilentlyIgnored(): void
    {
        ($this->useCase)(new RequestPasswordRecoveryCommand(email: 'not-an-email'));

        self::assertSame([], $this->emails->sent);
    }

    private function aUser(string $email): User
    {
        $user = User::register(
            UserId::random(),
            new Email($email),
            new FakePasswordHasher()->hash(new PlainPassword('password123')),
            [Role::USER],
            new DateTimeImmutable(self::NOW),
        );
        $this->users->save($user);

        return $user;
    }
}

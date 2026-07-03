<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Tests\Doubles\FakePasswordHasher;
use App\Tests\Doubles\InMemoryUserRepository;
use App\Tests\Doubles\SpyEventBus;
use App\User\Application\Command\EnsureAdmin\EnsureAdminCommand;
use App\User\Application\Command\EnsureAdmin\EnsureAdminUseCase;
use App\User\Domain\Email;
use App\User\Domain\Event\UserRegistered;
use App\User\Domain\HashedPassword;
use App\User\Domain\PlainPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use DateTimeImmutable;
use Jperdior\SharedKernel\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class EnsureAdminUseCaseTest extends TestCase
{
    private InMemoryUserRepository $users;
    private FakePasswordHasher $hasher;
    private SpyEventBus $eventBus;
    private EnsureAdminUseCase $useCase;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->hasher = new FakePasswordHasher();
        $this->eventBus = new SpyEventBus();
        $this->useCase = new EnsureAdminUseCase(
            $this->users,
            $this->hasher,
            $this->eventBus,
            new FrozenClock(new DateTimeImmutable('2026-07-03T10:00:00+00:00')),
        );
    }

    public function testCreatesAdminWithBothRolesAndEmitsRegisteredWhenMissing(): void
    {
        ($this->useCase)(new EnsureAdminCommand('admin@example.com', '!pw4template'));

        $user = $this->users->findByEmail(new Email('admin@example.com'));
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->roleStrings());
        self::assertTrue($this->hasher->verify($user->password(), new PlainPassword('!pw4template')));

        self::assertCount(1, $this->eventBus->published);
        self::assertInstanceOf(UserRegistered::class, $this->eventBus->published[0]);
    }

    public function testPromotesExistingUserAndEmitsNothing(): void
    {
        $this->users->save(User::register(
            UserId::random(),
            new Email('admin@example.com'),
            new HashedPassword('their-own-hash'),
            [Role::USER],
            new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
        ));

        ($this->useCase)(new EnsureAdminCommand('admin@example.com', 'ignored-when-existing'));

        $user = $this->users->findByEmail(new Email('admin@example.com'));
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->roleStrings());
        // Existing password is left untouched — we only add the role.
        self::assertSame('their-own-hash', $user->password()->value);
        self::assertSame([], $this->eventBus->published, 'promote path emits no event');
    }

    public function testIsIdempotentOnRepeat(): void
    {
        $command = new EnsureAdminCommand('admin@example.com', '!pw4template');
        ($this->useCase)($command);
        ($this->useCase)($command);

        $user = $this->users->findByEmail(new Email('admin@example.com'));
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->roleStrings());
    }
}

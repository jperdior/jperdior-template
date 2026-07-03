<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Tests\Doubles\FakePasswordHasher;
use App\Tests\Doubles\InMemoryUserRepository;
use App\Tests\Doubles\SpyEventBus;
use App\User\Application\Command\SignUp\SignUpCommand;
use App\User\Application\Command\SignUp\SignUpUseCase;
use App\User\Domain\Email;
use App\User\Domain\Event\UserRegistered;
use App\User\Domain\Exception\UserAlreadyExists;
use App\User\Domain\HashedPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use DateTimeImmutable;
use Jperdior\SharedKernel\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class SignUpUseCaseTest extends TestCase
{
    private InMemoryUserRepository $users;
    private SpyEventBus $eventBus;
    private SignUpUseCase $useCase;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->eventBus = new SpyEventBus();
        $this->useCase = new SignUpUseCase(
            $this->users,
            new FakePasswordHasher(),
            $this->eventBus,
            new FrozenClock(new DateTimeImmutable('2026-07-02T10:00:00+00:00')),
        );
    }

    public function testSignUpPersistsUserAndPublishesUserRegistered(): void
    {
        $id = UserId::random()->value;

        ($this->useCase)(new SignUpCommand(id: $id, email: 'new@example.com', plainPassword: 'password123'));

        $saved = $this->users->findByEmail(new Email('new@example.com'));
        self::assertNotNull($saved);
        self::assertSame($id, $saved->id()->value);
        self::assertSame('hashed::password123', $saved->password()->value);

        self::assertCount(1, $this->eventBus->published);
        $event = $this->eventBus->published[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertSame($id, $event->aggregateId);
    }

    public function testSignUpWithExistingEmailThrows(): void
    {
        $this->users->save(User::register(
            UserId::random(),
            new Email('taken@example.com'),
            new HashedPassword('h'),
            [Role::USER],
            new DateTimeImmutable(),
        ));

        $this->expectException(UserAlreadyExists::class);
        ($this->useCase)(new SignUpCommand(id: UserId::random()->value, email: 'taken@example.com', plainPassword: 'password123'));
    }
}

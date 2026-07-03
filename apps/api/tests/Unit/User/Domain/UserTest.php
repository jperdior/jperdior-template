<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain;

use App\User\Domain\Email;
use App\User\Domain\Event\UserRegistered;
use App\User\Domain\HashedPassword;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterRecordsUserRegisteredEvent(): void
    {
        $id = UserId::random();
        $createdAt = new DateTimeImmutable('2026-07-02T10:00:00+00:00');

        $user = User::register($id, new Email('new@example.com'), new HashedPassword('h'), [Role::USER], $createdAt);

        $events = $user->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertSame('user.account.registered', $event::eventName());
        self::assertSame($id->value, $event->aggregateId);
        self::assertSame('new@example.com', $event->email);
        self::assertSame(['ROLE_USER'], $event->roles);
        self::assertSame([], $user->pullDomainEvents(), 'events are drained on pull');
    }

    public function testRegisterWithNoRolesDefaultsToUser(): void
    {
        $user = User::register(UserId::random(), new Email('a@b.co'), new HashedPassword('h'), [], new DateTimeImmutable());

        self::assertSame([Role::USER], $user->roles());
    }

    public function testChangePasswordReplacesHashAndClearsForcedReset(): void
    {
        $user = $this->aUser();
        $user->forcePasswordReset();
        self::assertTrue($user->mustResetPassword());

        $user->changePassword(new HashedPassword('new-hash'));

        self::assertSame('new-hash', $user->password()->value);
        self::assertFalse($user->mustResetPassword());
    }

    public function testSoftDeleteIsIdempotentAndKeepsFirstTimestamp(): void
    {
        $user = $this->aUser();
        $first = new DateTimeImmutable('2026-07-01T00:00:00+00:00');
        $later = new DateTimeImmutable('2026-07-02T00:00:00+00:00');

        $user->softDelete($first);
        $user->softDelete($later);

        self::assertTrue($user->isDeleted());
        self::assertSame($first, $user->deletedAt());
    }

    public function testRestoreClearsDeletion(): void
    {
        $user = $this->aUser();
        $user->softDelete(new DateTimeImmutable());

        $user->restore();

        self::assertFalse($user->isDeleted());
        self::assertNull($user->deletedAt());
    }

    public function testPromoteToAdminIsIdempotent(): void
    {
        $user = $this->aUser();

        $user->promoteToAdmin();
        $user->promoteToAdmin();

        self::assertSame([Role::USER, Role::ADMIN], $user->roles());
    }

    public function testDemoteFromAdminRemovesOnlyAdminRole(): void
    {
        $user = $this->aUser();
        $user->promoteToAdmin();

        $user->demoteFromAdmin();

        self::assertSame([Role::USER], $user->roles());
    }

    private function aUser(): User
    {
        $user = User::register(
            UserId::random(),
            new Email('user@example.com'),
            new HashedPassword('hash'),
            [Role::USER],
            new DateTimeImmutable('2026-07-02T09:00:00+00:00'),
        );
        $user->pullDomainEvents();

        return $user;
    }
}

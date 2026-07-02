<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\ValueObject;

use App\User\Domain\Email;
use App\User\Domain\HashedPassword;
use App\User\Domain\PlainPassword;
use App\User\Domain\Role;
use App\User\Domain\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ValueError;

final class ValueObjectValidationTest extends TestCase
{
    public function testEmailIsNormalisedToLowercaseAndTrimmed(): void
    {
        self::assertSame('user@example.com', new Email('  User@Example.COM ')->value);
    }

    public function testEmailRejectsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email('not-an-email');
    }

    public function testPlainPasswordRejectsFewerThanEightCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainPassword('short7!');
    }

    public function testPlainPasswordRejectsMoreThan4096Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlainPassword(str_repeat('x', 4097));
    }

    public function testHashedPasswordRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HashedPassword('');
    }

    public function testUserIdRejectsNonUuidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UserId::fromString('not-a-uuid');
    }

    public function testRoleRejectsUnknownValue(): void
    {
        $this->expectException(ValueError::class);
        Role::from('ROLE_SUPERHERO');
    }
}

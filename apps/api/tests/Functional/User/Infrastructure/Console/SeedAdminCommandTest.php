<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Infrastructure\Console;

use App\Tests\Functional\FunctionalTestCase;
use App\User\Domain\Email;
use App\User\Domain\UserRepository;
use App\User\Infrastructure\Symfony\Console\SeedAdminCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedAdminCommandTest extends FunctionalTestCase
{
    public function testItCreatesAndPromotesTheAdminWhenMissing(): void
    {
        $exitCode = $this->runSeed('seeded-admin@example.com', '!pw4template');

        self::assertSame(Command::SUCCESS, $exitCode);
        $user = $this->users()->findByEmail(new Email('seeded-admin@example.com'));
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->roleStrings());
    }

    public function testItIsIdempotent(): void
    {
        self::assertSame(Command::SUCCESS, $this->runSeed('seeded-admin@example.com', '!pw4template'));
        self::assertSame(Command::SUCCESS, $this->runSeed('seeded-admin@example.com', '!pw4template'));

        $user = $this->users()->findByEmail(new Email('seeded-admin@example.com'));
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->roleStrings());
    }

    public function testItPromotesAnExistingNonAdminUser(): void
    {
        $this->userFixture()->createOne('existing@example.com', 'their-own-password');

        $exitCode = $this->runSeed('existing@example.com', 'ignored-password');

        self::assertSame(Command::SUCCESS, $exitCode);
        $user = $this->users()->findByEmail(new Email('existing@example.com'));
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->roleStrings());
    }

    private function runSeed(string $email, string $password): int
    {
        /** @var SeedAdminCommand $command */
        $command = static::getContainer()->get(SeedAdminCommand::class);

        return new CommandTester($command)->execute(['email' => $email, 'password' => $password]);
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);

        return $repo;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Infrastructure\Console\SeedAdmin;

use App\Tests\Functional\FunctionalTestCase;
use App\User\Domain\UserRepository;
use App\User\Infrastructure\Symfony\Console\SeedAdminCommand;
use Symfony\Component\Console\Tester\CommandTester;

abstract class BaseSeedAdminTest extends FunctionalTestCase
{
    protected function arrange(): void
    {
    }

    protected function runSeed(string $email, string $password): int
    {
        /** @var SeedAdminCommand $command */
        $command = static::getContainer()->get(SeedAdminCommand::class);

        return new CommandTester($command)->execute(['email' => $email, 'password' => $password]);
    }

    protected function users(): UserRepository
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);

        return $repo;
    }
}

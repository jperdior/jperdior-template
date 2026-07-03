<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Console;

use App\User\Infrastructure\Symfony\Console\SeedAdminCommand;
use Jperdior\SharedKernel\Domain\Bus\Command\Command as BusCommand;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedAdminCommandProdGuardTest extends TestCase
{
    public function testItRefusesToRunInProdAndDispatchesNothing(): void
    {
        $bus = new class implements CommandBus {
            /** @var list<BusCommand> */
            public array $dispatched = [];

            public function dispatch(BusCommand $command): void
            {
                $this->dispatched[] = $command;
            }
        };

        $tester = new CommandTester(new SeedAdminCommand($bus, 'prod'));
        $exitCode = $tester->execute(['email' => 'admin@example.com', 'password' => '!pw4template']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame([], $bus->dispatched);
        self::assertStringContainsString('refuses to run in prod', $tester->getDisplay());
    }
}

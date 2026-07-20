<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Symfony\Console;

use App\User\Application\EnsureAdmin\EnsureAdminCommand;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:user:seed-admin',
    description: 'Create or promote a dev admin (create-or-promote, idempotent). Dev/test only.',
)]
final class SeedAdminCommand extends Command
{
    private const DEFAULT_EMAIL = 'admin@example.com';
    private const DEFAULT_PASSWORD = '!pw4template';

    public function __construct(
        private readonly CommandBus $commandBus,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Admin email', self::DEFAULT_EMAIL)
            ->addArgument('password', InputArgument::OPTIONAL, 'Admin password', self::DEFAULT_PASSWORD);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // A known-password admin must never be seeded into production.
        if ('prod' === $this->environment) {
            $io->error('app:user:seed-admin refuses to run in prod (it seeds a known-password admin).');

            return Command::FAILURE;
        }

        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');

        $this->commandBus->dispatch(new EnsureAdminCommand($email, $password));
        $io->success(\sprintf('%s is ready as an admin.', $email));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Symfony\Console;

use App\User\Application\Command\PromoteToAdmin\PromoteToAdminCommand;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:promote-admin', description: 'Grant ROLE_ADMIN to a user by email.')]
final class PromoteAdminCommand extends Command
{
    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Target user email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $this->commandBus->dispatch(new PromoteToAdminCommand($email));
        $io->success(sprintf('%s is now an admin.', $email));

        return Command::SUCCESS;
    }
}

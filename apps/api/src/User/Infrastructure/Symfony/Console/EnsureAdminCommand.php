<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Symfony\Console;

use App\User\Application\Command\PromoteToAdmin\PromoteToAdminCommand;
use App\User\Application\Command\SignUp\SignUpCommand;
use App\User\Domain\Exception\UserAlreadyExists;
use App\User\Domain\UserId;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:ensure-admin', description: 'Create a user if not exists and grant ROLE_ADMIN. Idempotent.')]
final class EnsureAdminCommand extends Command
{
    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin user email')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin user password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');

        try {
            $this->commandBus->dispatch(new SignUpCommand(UserId::random()->value, $email, $password));
            $io->text(\sprintf('Created user %s.', $email));
        } catch (UserAlreadyExists) {
            $io->text(\sprintf('User %s already exists, skipping creation.', $email));
        }

        $this->commandBus->dispatch(new PromoteToAdminCommand($email));
        $io->success(\sprintf('%s is now an admin.', $email));

        return Command::SUCCESS;
    }
}

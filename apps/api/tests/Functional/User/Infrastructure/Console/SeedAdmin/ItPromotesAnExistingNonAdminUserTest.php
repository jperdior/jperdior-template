<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Infrastructure\Console\SeedAdmin;

use App\User\Domain\Email;
use Symfony\Component\Console\Command\Command;

final class ItPromotesAnExistingNonAdminUserTest extends BaseSeedAdminTest
{
    private int $exitCode;

    protected function arrange(): void
    {
        $this->userFixture()->createOne('existing@example.com', 'their-own-password');
    }

    protected function act(): void
    {
        $this->exitCode = $this->runSeed('existing@example.com', 'ignored-password');
    }

    protected function assert(): void
    {
        self::assertSame(Command::SUCCESS, $this->exitCode);
        $user = $this->users()->findByEmail(new Email('existing@example.com'));
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->roleStrings());
    }
}

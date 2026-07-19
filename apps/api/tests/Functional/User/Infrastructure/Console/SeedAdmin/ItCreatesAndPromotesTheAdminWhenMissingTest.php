<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Infrastructure\Console\SeedAdmin;

use App\User\Domain\Email;
use Symfony\Component\Console\Command\Command;

final class ItCreatesAndPromotesTheAdminWhenMissingTest extends BaseSeedAdminTest
{
    private int $exitCode;

    protected function act(): void
    {
        $this->exitCode = $this->runSeed('seeded-admin@example.com', '!pw4template');
    }

    protected function assert(): void
    {
        self::assertSame(Command::SUCCESS, $this->exitCode);
        $user = $this->users()->findByEmail(new Email('seeded-admin@example.com'));
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->roleStrings());
    }
}

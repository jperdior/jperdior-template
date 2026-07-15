<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\RequestPasswordRecovery;

use App\User\Domain\User;
use App\User\Infrastructure\Persistence\Doctrine\PasswordRecoveryTokenModel;

final class ItSupersedesPriorUnusedTokensTest extends BaseRequestPasswordRecoveryTest
{
    private User $user;

    protected function arrange(): void
    {
        $this->user = $this->userFixture()->createOne(email: 'serial@example.com');
        // First request issues a token directly via the fixture so we can later inspect it.
        $this->page->forgotPassword('serial@example.com');
        self::assertSame(204, $this->page->getStatusCode());
        self::assertSame(1, $this->tokens->countForUser($this->user->id()));
    }

    protected function act(): void
    {
        // Second request — should supersede the prior unused token.
        $this->page->forgotPassword('serial@example.com');
    }

    protected function assert(): void
    {
        self::assertSame(204, $this->page->getStatusCode());
        // Two rows exist now, but only one with used_at IS NULL (BR-U04, partial unique index).
        $unused = (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(PasswordRecoveryTokenModel::class, 't')
            ->where('t.userId = :uid')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('uid', $this->user->id()->value)
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(1, $unused, 'Only the newest token should remain unused — older tokens are superseded.');

        self::assertSame(2, $this->tokens->countForUser($this->user->id()));
    }
}

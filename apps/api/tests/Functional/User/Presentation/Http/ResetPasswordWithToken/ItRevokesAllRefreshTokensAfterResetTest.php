<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\ResetPasswordWithToken;

use App\User\Domain\User;
use App\User\Infrastructure\Security\RefreshToken;
use DateTime;

final class ItRevokesAllRefreshTokensAfterResetTest extends BaseResetPasswordWithTokenTest
{
    private User $user;
    private string $plainToken;

    protected function arrange(): void
    {
        $this->user = $this->userFixture()->createOne(email: 'revoke@example.com', password: 'oldpassword');

        // Plant two refresh-token rows for this user.
        $em = $this->entityManager();
        $rt1 = new RefreshToken()
            ->setUsername('revoke@example.com')
            ->setRefreshToken('rt-existing-1')
            ->setValid(new DateTime('+30 days'));
        $rt2 = new RefreshToken()
            ->setUsername('revoke@example.com')
            ->setRefreshToken('rt-existing-2')
            ->setValid(new DateTime('+30 days'));
        $em->persist($rt1);
        $em->persist($rt2);
        $em->flush();

        [, $plain] = $this->tokens->issueFor($this->user->id());
        $this->plainToken = $plain;
    }

    protected function act(): void
    {
        $this->page->resetPasswordWithToken($this->plainToken, 'newpassword1234');
    }

    protected function assert(): void
    {
        self::assertSame(204, $this->page->getStatusCode());

        $count = (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(rt.id)')
            ->from(RefreshToken::class, 'rt')
            ->where('rt.username = :u')
            ->setParameter('u', 'revoke@example.com')
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(0, $count, 'All refresh tokens for the user must be revoked after password reset.');
    }
}

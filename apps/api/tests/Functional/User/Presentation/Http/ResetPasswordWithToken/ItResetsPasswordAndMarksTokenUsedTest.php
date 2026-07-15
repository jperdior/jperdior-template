<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\ResetPasswordWithToken;

use App\User\Domain\HashedPassword;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\PlainPassword;
use App\User\Domain\User;

final class ItResetsPasswordAndMarksTokenUsedTest extends BaseResetPasswordWithTokenTest
{
    private User $user;
    private string $plainToken;
    private HashedPassword $oldHash;

    protected function arrange(): void
    {
        $this->user = $this->userFixture()->createOne(email: 'reset@example.com', password: 'oldpassword');
        $this->oldHash = $this->user->password();
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

        // Reload user, verify password changed and verify hash now matches the new password.
        $this->entityManager()->clear();
        /** @var \App\User\Domain\UserRepository $users */
        $users = static::getContainer()->get(\App\User\Domain\UserRepository::class);
        $reloaded = $users->findById($this->user->id());
        self::assertNotNull($reloaded);
        self::assertNotSame($this->oldHash->value, $reloaded->password()->value, 'Password hash must change.');

        /** @var PasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);
        self::assertTrue($hasher->verify($reloaded->password(), new PlainPassword('newpassword1234')));

        // Token is marked used.
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t.usedAt')
            ->from(\App\User\Infrastructure\Persistence\Doctrine\PasswordRecoveryTokenModel::class, 't')
            ->where('t.userId = :uid')
            ->setParameter('uid', $this->user->id()->value)
            ->getQuery()
            ->getSingleResult();
        self::assertNotNull($rows['usedAt']);
    }
}

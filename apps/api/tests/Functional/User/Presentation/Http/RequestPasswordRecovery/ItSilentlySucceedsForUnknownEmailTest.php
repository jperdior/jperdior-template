<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\RequestPasswordRecovery;

use App\User\Infrastructure\Persistence\Doctrine\PasswordRecoveryTokenModel;

final class ItSilentlySucceedsForUnknownEmailTest extends RequestPasswordRecoveryControllerTestCase
{
    protected function act(): void
    {
        $this->page->forgotPassword('nobody@example.com');
    }

    protected function assert(): void
    {
        self::assertSame(204, $this->page->getStatusCode(), 'Must always return 204 to avoid user enumeration (BR-U05).');

        $count = (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(PasswordRecoveryTokenModel::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(0, $count, 'No token row should be created for unknown emails.');
    }
}

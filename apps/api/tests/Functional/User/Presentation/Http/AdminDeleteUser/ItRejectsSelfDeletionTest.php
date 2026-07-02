<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\AdminDeleteUser;

use App\Tests\Functional\FunctionalTestCase;

final class ItRejectsSelfDeletionTest extends FunctionalTestCase
{
    public function testAdminCannotDeleteTheirOwnAccount(): void
    {
        $admin = $this->userFixture()->createAdmin('admin@example.com', 'secretpass');
        $token = $this->loginAs('admin@example.com', 'secretpass');

        $this->client->request(
            'DELETE',
            '/api/admin/users/'.$admin->id()->value,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        $body = $this->jsonResponse();
        self::assertSame('CONFLICT', $body['code']);
        self::assertSame('An admin cannot delete their own account.', $body['message']);
    }
}

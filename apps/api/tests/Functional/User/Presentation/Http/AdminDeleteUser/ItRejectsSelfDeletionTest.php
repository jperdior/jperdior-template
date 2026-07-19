<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\AdminDeleteUser;

final class ItRejectsSelfDeletionTest extends BaseAdminDeleteUserTest
{
    private string $adminId;
    private string $token;

    protected function arrange(): void
    {
        $admin = $this->userFixture()->createAdmin('admin@example.com', 'secretpass');
        $this->adminId = $admin->id()->value;
        $this->token = $this->loginAs('admin@example.com', 'secretpass');
    }

    protected function act(): void
    {
        $this->page->adminDeleteUser($this->adminId, $this->token);
    }

    protected function assert(): void
    {
        self::assertSame(409, $this->page->getStatusCode());
        $body = $this->page->getResponseJson();
        self::assertSame('CONFLICT', $body['code']);
        self::assertSame('An admin cannot delete their own account.', $body['message']);
    }
}

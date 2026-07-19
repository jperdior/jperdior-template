<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\AdminDeleteUser;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Pages\UserPage;

abstract class BaseAdminDeleteUserTest extends FunctionalTestCase
{
    protected UserPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = $this->userPage();
    }

    protected function arrange(): void
    {
    }
}

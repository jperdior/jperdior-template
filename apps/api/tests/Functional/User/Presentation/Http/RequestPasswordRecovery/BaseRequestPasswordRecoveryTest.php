<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\RequestPasswordRecovery;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Fixtures\PasswordRecoveryTokenFixture;
use App\Tests\Support\Pages\UserPage;

abstract class BaseRequestPasswordRecoveryTest extends FunctionalTestCase
{
    protected UserPage $page;
    protected PasswordRecoveryTokenFixture $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = $this->userPage();
        $this->tokens = $this->passwordRecoveryTokenFixture();
    }

    protected function arrange(): void
    {
    }
}

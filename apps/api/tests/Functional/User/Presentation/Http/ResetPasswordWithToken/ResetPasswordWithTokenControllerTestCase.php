<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\ResetPasswordWithToken;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\Support\Fixture\PasswordRecoveryTokenFixture;
use App\Tests\Functional\Support\Page\UserPage;
use PHPUnit\Framework\Attributes\Test;

abstract class ResetPasswordWithTokenControllerTestCase extends FunctionalTestCase
{
    protected UserPage $page;
    protected PasswordRecoveryTokenFixture $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = $this->userPage();
        $this->tokens = $this->passwordRecoveryTokenFixture();
    }

    #[Test]
    final public function test(): void
    {
        $this->arrange();
        $this->act();
        $this->assert();
    }

    protected function arrange(): void
    {
    }

    abstract protected function act(): void;

    abstract protected function assert(): void;
}

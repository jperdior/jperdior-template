<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http\Me;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\Support\Page\UserPage;
use PHPUnit\Framework\Attributes\Test;

abstract class MeControllerTestCase extends FunctionalTestCase
{
    protected UserPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = $this->userPage();
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

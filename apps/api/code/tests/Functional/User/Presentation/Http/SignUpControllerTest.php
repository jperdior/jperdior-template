<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http;

use App\Tests\Functional\FunctionalTestCase;

final class SignUpControllerTest extends FunctionalTestCase
{
    public function testItCreatesANewUser(): void
    {
        $this->postJson('/auth/signup', [
            'email'    => 'newuser@example.com',
            'password' => 'secretpass',
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonResponse();
        self::assertArrayHasKey('id', $body);
    }

    public function testItRejectsDuplicateEmail(): void
    {
        $this->postJson('/auth/signup', ['email' => 'dupe@example.com', 'password' => 'secretpass']);
        self::assertResponseStatusCodeSame(201);

        $this->postJson('/auth/signup', ['email' => 'dupe@example.com', 'password' => 'secretpass']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testItRejectsShortPassword(): void
    {
        $this->postJson('/auth/signup', ['email' => 'short@example.com', 'password' => '123']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsInvalidEmail(): void
    {
        $this->postJson('/auth/signup', ['email' => 'not-an-email', 'password' => 'secretpass']);
        self::assertResponseStatusCodeSame(422);
    }
}

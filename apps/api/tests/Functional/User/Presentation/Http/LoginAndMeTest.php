<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Presentation\Http;

use App\Tests\Functional\FunctionalTestCase;

final class LoginAndMeTest extends FunctionalTestCase
{
    public function testSignUpThenLoginThenMe(): void
    {
        // sign up
        $this->postJson('/auth/signup', ['email' => 'me@example.com', 'password' => 'secretpass']);
        self::assertResponseStatusCodeSame(201);

        // login
        $this->postJson('/auth/login', ['email' => 'me@example.com', 'password' => 'secretpass']);
        self::assertResponseStatusCodeSame(200);
        $login = $this->jsonResponse();
        self::assertArrayHasKey('token', $login);
        self::assertArrayHasKey('refresh_token', $login);

        // /api/me
        $this->getJson('/api/me', $login['token']);
        self::assertResponseStatusCodeSame(200);
        $me = $this->jsonResponse();
        self::assertSame('me@example.com', $me['email']);
        self::assertContains('ROLE_USER', $me['roles']);
    }

    public function testMeRequiresAuth(): void
    {
        $this->getJson('/api/me');
        self::assertResponseStatusCodeSame(401);
    }
}

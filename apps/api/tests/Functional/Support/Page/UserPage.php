<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support\Page;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UserPage
{
    public function __construct(
        private readonly KernelBrowser $client,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function signUp(string $email, string $password): void
    {
        $url = $this->router->generate('api_user_signup');
        $this->client->request(
            'POST',
            $url,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR),
        );
    }

    public function login(string $email, string $password): void
    {
        $url = $this->router->generate('api_user_login');
        $this->client->request(
            'POST',
            $url,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR),
        );
    }

    public function me(?string $token = null): void
    {
        $url = $this->router->generate('api_user_me');
        $server = [];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request('GET', $url, server: $server);
    }

    public function getStatusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    /** @return array<string, mixed> */
    public function getResponseJson(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }

    public function extractToken(): string
    {
        $json = $this->getResponseJson();
        $token = $json['token'] ?? null;
        if (!\is_string($token)) {
            throw new RuntimeException('Login response did not contain a token.');
        }

        return $token;
    }
}

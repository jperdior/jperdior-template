<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    protected ?KernelBrowser $client = null;
    protected ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em !== null && $this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        $this->em     = null;
        $this->client = null;

        parent::tearDown();
    }

    protected function postJson(string $uri, array $body = [], ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client?->request('POST', $uri, server: $server, content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function getJson(string $uri, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client?->request('GET', $uri, server: $server);
    }

    protected function jsonResponse(): array
    {
        $content = (string) $this->client?->getResponse()->getContent();

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    protected function loginAs(string $email, string $password = 'secret'): string
    {
        $this->postJson('/auth/login', ['email' => $email, 'password' => $password]);
        $payload = $this->jsonResponse();

        return $payload['token'] ?? throw new \RuntimeException('Login did not return a token.');
    }
}

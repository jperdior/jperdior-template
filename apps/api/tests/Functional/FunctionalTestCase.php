<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Functional\Support\Fixture\UserFixture;
use App\Tests\Functional\Support\Page\UserPage;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em?->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        $this->em = null;
        parent::tearDown();
    }

    /** @param array<string, mixed> $body */
    protected function postJson(string $uri, array $body = [], ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request('POST', $uri, server: $server, content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    protected function jsonResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }

    protected function loginAs(string $email, string $password = 'secret'): string
    {
        $this->postJson('/auth/login', ['email' => $email, 'password' => $password]);
        $payload = $this->jsonResponse();

        return $payload['token'] ?? throw new RuntimeException('Login did not return a token.');
    }

    protected function userPage(): UserPage
    {
        /** @var UrlGeneratorInterface $router */
        $router = static::getContainer()->get(UrlGeneratorInterface::class);

        return new UserPage($this->client, $router);
    }

    protected function userFixture(): UserFixture
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);

        return new UserFixture($repo, $hasher);
    }
}

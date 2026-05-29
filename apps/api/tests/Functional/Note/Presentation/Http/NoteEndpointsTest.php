<?php

declare(strict_types=1);

namespace App\Tests\Functional\Note\Presentation\Http;

use App\Tests\Functional\FunctionalTestCase;

final class NoteEndpointsTest extends FunctionalTestCase
{
    private function authenticatedToken(string $email = 'notes-user@example.com'): string
    {
        $this->postJson('/auth/signup', ['email' => $email, 'password' => 'secretpass']);
        assert($this->client !== null);
        self::assertContains($this->client->getResponse()->getStatusCode(), [201, 409]);

        return $this->loginAs($email);
    }

    public function testCreateListGetUpdateDelete(): void
    {
        $token = $this->authenticatedToken();
        assert($this->client !== null);

        // create
        $this->postJson('/api/notes', ['title' => 'first', 'body' => 'hello world'], $token);
        self::assertResponseStatusCodeSame(201);
        $created = $this->jsonResponse();
        $id      = $created['id'];

        // list
        $this->getJson('/api/notes', $token);
        self::assertResponseStatusCodeSame(200);
        $list = $this->jsonResponse();
        self::assertGreaterThanOrEqual(1, $list['total']);

        // get
        $this->getJson('/api/notes/'.$id, $token);
        self::assertResponseStatusCodeSame(200);
        $note = $this->jsonResponse();
        self::assertSame('first', $note['title']);

        // update
        $this->client->request(
            'PATCH',
            '/api/notes/'.$id,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            content: json_encode(['title' => 'updated', 'body' => 'still here'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(204);

        // delete
        $this->client->request('DELETE', '/api/notes/'.$id, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseStatusCodeSame(204);

        // get after delete
        $this->getJson('/api/notes/'.$id, $token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotReadAnotherUsersNote(): void
    {
        $alice = $this->authenticatedToken('alice@example.com');
        $this->postJson('/api/notes', ['title' => 'alice note', 'body' => 'private'], $alice);
        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonResponse()['id'];

        $bob = $this->authenticatedToken('bob@example.com');
        $this->getJson('/api/notes/'.$id, $bob);
        self::assertResponseStatusCodeSame(409);
    }

    public function testListRequiresAuth(): void
    {
        $this->getJson('/api/notes');
        self::assertResponseStatusCodeSame(401);
    }
}

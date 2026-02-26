<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskApiTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        static::getContainer()
            ->get('doctrine')
            ->getManager()
            ->getConnection()
            ->executeStatement('TRUNCATE TABLE task RESTART IDENTITY CASCADE');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function json(string $method, string $uri, array $body = []): array
    {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body ? json_encode($body) : null
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function statusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    // ------------------------------------------------------------------
    // POST /api/tasks
    // ------------------------------------------------------------------

    public function testCreateTask(): void
    {
        $data = $this->json('POST', '/api/tasks', [
            'title'       => 'Write tests',
            'description' => 'PHPUnit API tests',
        ]);

        self::assertSame(201, $this->statusCode());
        self::assertIsInt($data['id']);
        self::assertSame('Write tests', $data['title']);
        self::assertSame('PHPUnit API tests', $data['description']);
        self::assertSame('todo', $data['status']);
        self::assertArrayHasKey('created_at', $data);
        self::assertArrayHasKey('updated_at', $data);
    }

    public function testCreateTaskWithoutDescription(): void
    {
        $data = $this->json('POST', '/api/tasks', ['title' => 'No description']);

        self::assertSame(201, $this->statusCode());
        self::assertNull($data['description']);
    }

    public function testCreateTaskRequiresTitle(): void
    {
        $data = $this->json('POST', '/api/tasks', ['description' => 'No title']);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('title', $data['errors']);
    }

    public function testCreateTaskRejectsBlankTitle(): void
    {
        $data = $this->json('POST', '/api/tasks', ['title' => '   ']);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('title', $data['errors']);
    }

    // ------------------------------------------------------------------
    // GET /api/tasks
    // ------------------------------------------------------------------

    public function testListTasks(): void
    {
        $this->json('POST', '/api/tasks', ['title' => 'First']);
        $this->json('POST', '/api/tasks', ['title' => 'Second']);

        $data = $this->json('GET', '/api/tasks');

        self::assertSame(200, $this->statusCode());
        self::assertIsArray($data);
        self::assertCount(2, $data);
    }

    public function testListTasksFilterByStatus(): void
    {
        $this->json('POST', '/api/tasks', ['title' => 'Stay todo']);

        $created = $this->json('POST', '/api/tasks', ['title' => 'Go in progress']);
        $this->json('PATCH', '/api/tasks/' . $created['id'], ['status' => 'in_progress']);

        $data = $this->json('GET', '/api/tasks?status=todo');

        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $data);
        self::assertSame('todo', $data[0]['status']);
    }

    // ------------------------------------------------------------------
    // GET /api/tasks/{id}
    // ------------------------------------------------------------------

    public function testGetTask(): void
    {
        $task = $this->json('POST', '/api/tasks', ['title' => 'A task']);

        $data = $this->json('GET', '/api/tasks/' . $task['id']);

        self::assertSame(200, $this->statusCode());
        self::assertSame($task['id'], $data['id']);
        self::assertSame('A task', $data['title']);
    }

    public function testGetTaskNotFound(): void
    {
        $data = $this->json('GET', '/api/tasks/99999');

        self::assertSame(404, $this->statusCode());
        self::assertArrayHasKey('error', $data);
    }

    // ------------------------------------------------------------------
    // PATCH /api/tasks/{id}
    // ------------------------------------------------------------------

    public function testUpdateTitle(): void
    {
        $created = $this->json('POST', '/api/tasks', ['title' => 'Old title']);

        $data = $this->json('PATCH', '/api/tasks/' . $created['id'], [
            'title' => 'New title',
        ]);

        self::assertSame(200, $this->statusCode());
        self::assertSame('New title', $data['title']);
    }

    public function testUpdateStatus(): void
    {
        $created = $this->json('POST', '/api/tasks', ['title' => 'Start me']);

        $data = $this->json('PATCH', '/api/tasks/' . $created['id'], [
            'status' => 'in_progress',
        ]);

        self::assertSame(200, $this->statusCode());
        self::assertSame('in_progress', $data['status']);
        self::assertSame('Start me', $data['title']);
    }

    public function testUpdateRejectsInvalidStatus(): void
    {
        $created = $this->json('POST', '/api/tasks', ['title' => 'A task']);

        $data = $this->json('PATCH', '/api/tasks/' . $created['id'], [
            'status' => 'banana',
        ]);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('status', $data['errors']);
    }

    public function testUpdateNotFound(): void
    {
        $data = $this->json('PATCH', '/api/tasks/99999', ['title' => 'Ghost']);

        self::assertSame(404, $this->statusCode());
        self::assertArrayHasKey('error', $data);
    }

    // ------------------------------------------------------------------
    // DELETE /api/tasks/{id}
    // ------------------------------------------------------------------

    public function testDeleteTask(): void
    {
        $created = $this->json('POST', '/api/tasks', ['title' => 'Delete me']);

        $this->client->request('DELETE', '/api/tasks/' . $created['id']);
        self::assertSame(204, $this->statusCode());

        $this->json('GET', '/api/tasks/' . $created['id']);
        self::assertSame(404, $this->statusCode());
    }

    public function testDeleteTaskNotFound(): void
    {
        $this->client->request('DELETE', '/api/tasks/99999');

        self::assertSame(404, $this->statusCode());
    }
}

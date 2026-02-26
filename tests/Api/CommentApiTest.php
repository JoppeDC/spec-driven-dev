<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommentApiTest extends WebTestCase
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

    private function createTask(string $title = 'A task'): array
    {
        return $this->json('POST', '/api/tasks', ['title' => $title]);
    }

    // ------------------------------------------------------------------
    // POST /api/tasks/{id}/comments
    // ------------------------------------------------------------------

    public function testCreateComment(): void
    {
        $task = $this->createTask();

        $data = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', [
            'body' => 'This looks good to me.',
        ]);

        self::assertSame(201, $this->statusCode());
        self::assertIsInt($data['id']);
        self::assertSame($task['id'], $data['task_id']);
        self::assertSame('This looks good to me.', $data['body']);
        self::assertArrayHasKey('created_at', $data);
    }

    public function testCreateCommentRequiresBody(): void
    {
        $task = $this->createTask();

        $data = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', []);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('body', $data['errors']);
    }

    public function testCreateCommentRejectsBlankBody(): void
    {
        $task = $this->createTask();

        $data = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', [
            'body' => '   ',
        ]);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('body', $data['errors']);
    }

    public function testCreateCommentRejectsTooLongBody(): void
    {
        $task = $this->createTask();

        $data = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', [
            'body' => str_repeat('x', 2001),
        ]);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('body', $data['errors']);
    }

    public function testCreateCommentForNonExistentTask(): void
    {
        $data = $this->json('POST', '/api/tasks/99999/comments', [
            'body' => 'Nobody home.',
        ]);

        self::assertSame(404, $this->statusCode());
        self::assertArrayHasKey('error', $data);
    }

    // ------------------------------------------------------------------
    // GET /api/tasks/{id}/comments
    // ------------------------------------------------------------------

    public function testListComments(): void
    {
        $task = $this->createTask();
        $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => 'First']);
        $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => 'Second']);

        $data = $this->json('GET', '/api/tasks/' . $task['id'] . '/comments');

        self::assertSame(200, $this->statusCode());
        self::assertIsArray($data);
        self::assertCount(2, $data);
    }

    public function testListCommentsReturnsEmptyArray(): void
    {
        $task = $this->createTask();

        $data = $this->json('GET', '/api/tasks/' . $task['id'] . '/comments');

        self::assertSame(200, $this->statusCode());
        self::assertSame([], $data);
    }

    public function testListCommentsOrderedByCreatedAtAscending(): void
    {
        $task = $this->createTask();
        $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => 'First']);
        $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => 'Second']);

        $data = $this->json('GET', '/api/tasks/' . $task['id'] . '/comments');

        self::assertSame(200, $this->statusCode());
        self::assertSame('First', $data[0]['body']);
        self::assertSame('Second', $data[1]['body']);
    }

    public function testListCommentsForNonExistentTask(): void
    {
        $data = $this->json('GET', '/api/tasks/99999/comments');

        self::assertSame(404, $this->statusCode());
        self::assertArrayHasKey('error', $data);
    }

    // ------------------------------------------------------------------
    // DELETE /api/tasks/{taskId}/comments/{commentId}
    // ------------------------------------------------------------------

    public function testDeleteComment(): void
    {
        $task    = $this->createTask();
        $comment = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => 'Delete me']);

        $this->client->request('DELETE', '/api/tasks/' . $task['id'] . '/comments/' . $comment['id']);
        self::assertSame(204, $this->statusCode());

        $data = $this->json('GET', '/api/tasks/' . $task['id'] . '/comments');
        self::assertSame([], $data);
    }

    public function testDeleteCommentNotFound(): void
    {
        $task = $this->createTask();

        $this->client->request('DELETE', '/api/tasks/' . $task['id'] . '/comments/99999');

        self::assertSame(404, $this->statusCode());
    }

    public function testDeleteCommentTaskNotFound(): void
    {
        $this->client->request('DELETE', '/api/tasks/99999/comments/1');

        self::assertSame(404, $this->statusCode());
    }

    // ------------------------------------------------------------------
    // Cascade delete
    // ------------------------------------------------------------------

    public function testDeleteTaskCascadesToComments(): void
    {
        $task    = $this->createTask();
        $comment = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => 'Will be gone']);

        $this->client->request('DELETE', '/api/tasks/' . $task['id']);
        self::assertSame(204, $this->statusCode());

        // The task is gone — comments should be too (cascade at DB level)
        $this->json('GET', '/api/tasks/' . $task['id'] . '/comments');
        self::assertSame(404, $this->statusCode());
    }
}

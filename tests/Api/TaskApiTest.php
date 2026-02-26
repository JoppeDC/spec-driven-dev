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
        self::assertSame([], $data['subtasks']);
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

    public function testListTasksReturnsTopLevelOnly(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);
        $this->json('POST', '/api/tasks', ['title' => 'Another parent']);
        $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Child']);

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

    public function testGetTaskIncludesSubtasks(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);
        $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Child']);

        $data = $this->json('GET', '/api/tasks/' . $parent['id']);

        self::assertSame(200, $this->statusCode());
        self::assertSame($parent['id'], $data['id']);
        self::assertArrayHasKey('subtasks', $data);
        self::assertCount(1, $data['subtasks']);
        self::assertSame('Child', $data['subtasks'][0]['title']);
    }

    public function testGetTaskReturnsEmptySubtasksArray(): void
    {
        $task = $this->json('POST', '/api/tasks', ['title' => 'No children']);

        $data = $this->json('GET', '/api/tasks/' . $task['id']);

        self::assertSame(200, $this->statusCode());
        self::assertSame([], $data['subtasks']);
    }

    public function testGetTaskNotFound(): void
    {
        $data = $this->json('GET', '/api/tasks/99999');

        self::assertSame(404, $this->statusCode());
        self::assertArrayHasKey('error', $data);
    }

    // ------------------------------------------------------------------
    // POST /api/tasks/{id}/subtasks
    // ------------------------------------------------------------------

    public function testCreateSubtask(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);

        $data = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', [
            'title'       => 'Child task',
            'description' => 'A subtask',
        ]);

        self::assertSame(201, $this->statusCode());
        self::assertIsInt($data['id']);
        self::assertSame('Child task', $data['title']);
        self::assertSame('todo', $data['status']);
        self::assertArrayNotHasKey('subtasks', $data);
    }

    public function testCreateSubtaskForNonExistentParent(): void
    {
        $data = $this->json('POST', '/api/tasks/99999/subtasks', ['title' => 'Orphan']);

        self::assertSame(404, $this->statusCode());
        self::assertArrayHasKey('error', $data);
    }

    public function testCreateSubtaskOfSubtaskIsRejected(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);
        $child  = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Child']);

        $data = $this->json('POST', '/api/tasks/' . $child['id'] . '/subtasks', ['title' => 'Grandchild']);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
    }

    public function testCreateSubtaskRequiresTitle(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);

        $data = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['description' => 'No title']);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('title', $data['errors']);
    }

    // ------------------------------------------------------------------
    // GET /api/tasks/{id}/subtasks
    // ------------------------------------------------------------------

    public function testListSubtasks(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);
        $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Child one']);
        $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Child two']);

        $data = $this->json('GET', '/api/tasks/' . $parent['id'] . '/subtasks');

        self::assertSame(200, $this->statusCode());
        self::assertIsArray($data);
        self::assertCount(2, $data);
    }

    public function testListSubtasksForNonExistentParent(): void
    {
        $data = $this->json('GET', '/api/tasks/99999/subtasks');

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

    public function testCannotMarkParentDoneWithOpenSubtasks(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);
        $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Open child']);

        $data = $this->json('PATCH', '/api/tasks/' . $parent['id'], ['status' => 'done']);

        self::assertSame(422, $this->statusCode());
        self::assertArrayHasKey('errors', $data);
    }

    public function testCanMarkParentDoneWhenAllSubtasksDone(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);
        $child  = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Child']);
        $this->json('PATCH', '/api/tasks/' . $child['id'], ['status' => 'done']);

        $data = $this->json('PATCH', '/api/tasks/' . $parent['id'], ['status' => 'done']);

        self::assertSame(200, $this->statusCode());
        self::assertSame('done', $data['status']);
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

    public function testDeleteParentCascadesToSubtasks(): void
    {
        $parent = $this->json('POST', '/api/tasks', ['title' => 'Parent']);
        $child  = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', ['title' => 'Child']);

        $this->client->request('DELETE', '/api/tasks/' . $parent['id']);
        self::assertSame(204, $this->statusCode());

        $this->json('GET', '/api/tasks/' . $child['id']);
        self::assertSame(404, $this->statusCode());
    }

    public function testDeleteTaskNotFound(): void
    {
        $this->client->request('DELETE', '/api/tasks/99999');

        self::assertSame(404, $this->statusCode());
    }
}

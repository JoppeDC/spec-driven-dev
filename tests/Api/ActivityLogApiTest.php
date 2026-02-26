<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ActivityLogApiTest extends WebTestCase
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

    private function getActivity(int $taskId): array
    {
        return $this->json('GET', '/api/tasks/' . $taskId . '/activity');
    }

    // ------------------------------------------------------------------
    // GET /api/tasks/{id}/activity
    // ------------------------------------------------------------------

    public function testActivityEndpointReturns200(): void
    {
        $task = $this->createTask();

        $data = $this->getActivity($task['id']);

        self::assertSame(200, $this->statusCode());
        self::assertIsArray($data);
    }

    public function testActivityNotFoundForNonExistentTask(): void
    {
        $this->getActivity(99999);

        self::assertSame(404, $this->statusCode());
    }

    // ------------------------------------------------------------------
    // task_created
    // ------------------------------------------------------------------

    public function testTaskCreatedIsLogged(): void
    {
        $task = $this->createTask('Build feature');

        $data = $this->getActivity($task['id']);

        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $data);
        self::assertSame('task_created', $data[0]['action']);
        self::assertSame($task['id'], $data[0]['task_id']);
        self::assertSame('Build feature', $data[0]['changes']['title']);
        self::assertArrayHasKey('created_at', $data[0]);
    }

    // ------------------------------------------------------------------
    // task_updated
    // ------------------------------------------------------------------

    public function testTaskUpdatedLogsChangedFields(): void
    {
        $task = $this->createTask('Old title');
        $this->json('PATCH', '/api/tasks/' . $task['id'], ['title' => 'New title']);

        $data = $this->getActivity($task['id']);

        self::assertSame(200, $this->statusCode());
        self::assertCount(2, $data); // task_created + task_updated
        self::assertSame('task_updated', $data[1]['action']);
        self::assertSame('Old title', $data[1]['changes']['title']['old']);
        self::assertSame('New title', $data[1]['changes']['title']['new']);
    }

    public function testTaskUpdatedLogsStatusChange(): void
    {
        $task = $this->createTask();
        $this->json('PATCH', '/api/tasks/' . $task['id'], ['status' => 'in_progress']);

        $data = $this->getActivity($task['id']);

        $updateEntry = $data[1];
        self::assertSame('task_updated', $updateEntry['action']);
        self::assertSame('todo', $updateEntry['changes']['status']['old']);
        self::assertSame('in_progress', $updateEntry['changes']['status']['new']);
    }

    public function testTaskUpdatedOnlyLogsChangedFields(): void
    {
        $task = $this->createTask('Keep this');
        $this->json('PATCH', '/api/tasks/' . $task['id'], [
            'title'  => 'Keep this',  // unchanged
            'status' => 'in_progress' // changed
        ]);

        $data = $this->getActivity($task['id']);

        $updateEntry = $data[1];
        self::assertSame('task_updated', $updateEntry['action']);
        self::assertArrayHasKey('status', $updateEntry['changes']);
        self::assertArrayNotHasKey('title', $updateEntry['changes']);
    }

    public function testNoLogEntryWhenNothingChanges(): void
    {
        $task = $this->createTask('Same title');
        $this->json('PATCH', '/api/tasks/' . $task['id'], ['title' => 'Same title']);

        $data = $this->getActivity($task['id']);

        self::assertCount(1, $data); // only task_created, no update entry
    }

    // ------------------------------------------------------------------
    // subtask_created
    // ------------------------------------------------------------------

    public function testSubtaskCreatedIsLoggedOnParent(): void
    {
        $parent = $this->createTask('Parent');
        $child = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', [
            'title' => 'Child task',
        ]);

        $data = $this->getActivity($parent['id']);

        self::assertSame(200, $this->statusCode());
        self::assertCount(2, $data); // task_created + subtask_created
        self::assertSame('subtask_created', $data[1]['action']);
        self::assertSame($parent['id'], $data[1]['task_id']);
        self::assertSame($child['id'], $data[1]['changes']['subtask_id']);
        self::assertSame('Child task', $data[1]['changes']['title']);
    }

    // ------------------------------------------------------------------
    // Subtask update logged on parent
    // ------------------------------------------------------------------

    public function testSubtaskUpdateIsLoggedOnParent(): void
    {
        $parent = $this->createTask('Parent');
        $child = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', [
            'title' => 'Child',
        ]);
        $this->json('PATCH', '/api/tasks/' . $child['id'], ['status' => 'done']);

        $data = $this->getActivity($parent['id']);

        // task_created + subtask_created + task_updated (for subtask status change)
        self::assertCount(3, $data);
        self::assertSame('task_updated', $data[2]['action']);
        self::assertSame($parent['id'], $data[2]['task_id']);
    }

    // ------------------------------------------------------------------
    // comment_added
    // ------------------------------------------------------------------

    public function testCommentAddedIsLogged(): void
    {
        $task = $this->createTask();
        $comment = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', [
            'body' => 'Looks good!',
        ]);

        $data = $this->getActivity($task['id']);

        self::assertCount(2, $data); // task_created + comment_added
        self::assertSame('comment_added', $data[1]['action']);
        self::assertSame($comment['id'], $data[1]['changes']['comment_id']);
        self::assertSame('Looks good!', $data[1]['changes']['body']);
    }

    public function testCommentOnSubtaskIsLoggedOnParent(): void
    {
        $parent = $this->createTask('Parent');
        $child = $this->json('POST', '/api/tasks/' . $parent['id'] . '/subtasks', [
            'title' => 'Child',
        ]);
        $this->json('POST', '/api/tasks/' . $child['id'] . '/comments', [
            'body' => 'Comment on subtask',
        ]);

        $data = $this->getActivity($parent['id']);

        // task_created + subtask_created + comment_added
        self::assertCount(3, $data);
        self::assertSame('comment_added', $data[2]['action']);
        self::assertSame($parent['id'], $data[2]['task_id']);
    }

    // ------------------------------------------------------------------
    // comment_deleted
    // ------------------------------------------------------------------

    public function testCommentDeletedIsLogged(): void
    {
        $task = $this->createTask();
        $comment = $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', [
            'body' => 'Delete me',
        ]);
        $this->client->request('DELETE', '/api/tasks/' . $task['id'] . '/comments/' . $comment['id']);

        $data = $this->getActivity($task['id']);

        // task_created + comment_added + comment_deleted
        self::assertCount(3, $data);
        self::assertSame('comment_deleted', $data[2]['action']);
        self::assertSame($comment['id'], $data[2]['changes']['comment_id']);
    }

    // ------------------------------------------------------------------
    // Ordering
    // ------------------------------------------------------------------

    public function testActivityIsOrderedByCreatedAtAscending(): void
    {
        $task = $this->createTask('First action');
        $this->json('PATCH', '/api/tasks/' . $task['id'], ['status' => 'in_progress']);
        $this->json('POST', '/api/tasks/' . $task['id'] . '/comments', ['body' => 'Third action']);

        $data = $this->getActivity($task['id']);

        self::assertSame('task_created', $data[0]['action']);
        self::assertSame('task_updated', $data[1]['action']);
        self::assertSame('comment_added', $data[2]['action']);
    }

    // ------------------------------------------------------------------
    // Cascade delete
    // ------------------------------------------------------------------

    public function testDeleteTaskCascadesToActivityLog(): void
    {
        $task = $this->createTask();
        $this->json('PATCH', '/api/tasks/' . $task['id'], ['status' => 'in_progress']);

        $this->client->request('DELETE', '/api/tasks/' . $task['id']);
        self::assertSame(204, $this->statusCode());

        $this->getActivity($task['id']);
        self::assertSame(404, $this->statusCode());
    }
}

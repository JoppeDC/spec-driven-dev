# Feature Spec: Activity Log

## Purpose

An append-only audit trail for tasks. Activity log entries are created
automatically when certain actions occur. They cannot be created, edited, or
deleted through the API — only listed. Deleting a task cascades to its
activity log entries.

## Entities

### ActivityLog

| Field        | Type      | Notes                                                        |
|--------------|-----------|--------------------------------------------------------------|
| `id`         | integer   | Auto-incremented primary key                                 |
| `task_id`    | integer   | FK to `task.id`; the top-level task the activity belongs to   |
| `action`     | enum      | One of the tracked actions (see below)                       |
| `changes`    | json\|null| Action-specific payload (see below)                          |
| `created_at` | timestamp | Set automatically when the entry is recorded                 |

## Tracked Actions

| Action             | Trigger                              | `changes` payload                                                              |
|--------------------|--------------------------------------|--------------------------------------------------------------------------------|
| `task_created`     | `POST /api/tasks`                    | `{ "title": "<title>" }`                                                      |
| `task_updated`     | `PATCH /api/tasks/{id}`              | `{ "<field>": { "old": "<old>", "new": "<new>" } }` for each changed field    |
| `subtask_created`  | `POST /api/tasks/{id}/subtasks`      | `{ "subtask_id": <id>, "title": "<title>" }`                                  |
| `comment_added`    | `POST /api/tasks/{id}/comments`      | `{ "comment_id": <id>, "body": "<body>" }`                                    |
| `comment_deleted`  | `DELETE /api/tasks/{id}/comments/{id}` | `{ "comment_id": <id> }`                                                    |

### Scoping rules

- All activity is logged against the **top-level task**. When a subtask is
  updated, the log entry is recorded on the subtask's parent task.
- When a subtask is created, the entry is logged on the parent task.
- Comment activity is logged on the task the comment belongs to. If that task
  is a subtask, the entry is logged on the subtask's parent task.

## Endpoints

### `GET /api/tasks/{id}/activity`

Returns all activity log entries for the given task, ordered by `created_at`
ascending.

**Response 200:**
```json
[
  {
    "id": 1,
    "task_id": 42,
    "action": "task_created",
    "changes": { "title": "Write tests" },
    "created_at": "2026-02-26T10:00:00+00:00"
  },
  {
    "id": 2,
    "task_id": 42,
    "action": "task_updated",
    "changes": {
      "status": { "old": "todo", "new": "in_progress" }
    },
    "created_at": "2026-02-26T10:01:00+00:00"
  }
]
```

**Response 404:** task not found.

---

## Business Rules

- Activity log entries are read-only — no POST, PATCH, or DELETE endpoints
- Entries are created automatically as a side effect of other API operations
- All activity is scoped to the top-level task
- Deleting a task cascades to all its activity log entries
- `task_updated` entries only include fields that actually changed
- If a PATCH request results in no actual changes (same values), no log entry
  is created

# Feature Spec: Tasks

## Purpose

A task management API. Users can create and manage tasks, track their progress
through a defined lifecycle, organise work into subtasks, and retrieve or
remove tasks as needed.

## Entities

### Task

| Field         | Type          | Notes                                      |
|---------------|---------------|--------------------------------------------|
| `id`          | integer       | Auto-incremented primary key               |
| `parent_id`   | integer\|null | FK to `task.id`; null for top-level tasks  |
| `title`       | string        | Required, max 255 characters               |
| `description` | string\|null  | Optional, max 1000 characters              |
| `status`      | enum          | `todo`, `in_progress`, `done`; default `todo` |
| `created_at`  | timestamp     | Set automatically on creation              |
| `updated_at`  | timestamp     | Updated automatically on every change      |

## Endpoints

### `GET /api/tasks`

Returns all top-level tasks (tasks with no parent). Subtasks are excluded.

Optional query parameter:
- `status` — filter by status (`todo`, `in_progress`, `done`)

**Response 200:**
```json
[
  {
    "id": 1,
    "title": "Write tests",
    "description": null,
    "status": "todo",
    "created_at": "2026-02-26T10:00:00+00:00",
    "updated_at": "2026-02-26T10:00:00+00:00"
  }
]
```

---

### `POST /api/tasks`

Creates a new top-level task.

**Request body:**
```json
{
  "title": "Write tests",
  "description": "Optional longer description"
}
```

**Response 201:**
```json
{
  "id": 1,
  "title": "Write tests",
  "description": "Optional longer description",
  "status": "todo",
  "subtasks": [],
  "created_at": "2026-02-26T10:00:00+00:00",
  "updated_at": "2026-02-26T10:00:00+00:00"
}
```

**Response 422** (validation failure):
```json
{
  "errors": {
    "title": "This value should not be blank."
  }
}
```

---

### `GET /api/tasks/{id}`

Returns a single task including its subtasks.

**Response 200:**
```json
{
  "id": 1,
  "title": "Write tests",
  "description": null,
  "status": "todo",
  "subtasks": [
    {
      "id": 2,
      "title": "Write unit tests",
      "description": null,
      "status": "todo",
      "created_at": "2026-02-26T10:00:00+00:00",
      "updated_at": "2026-02-26T10:00:00+00:00"
    }
  ],
  "created_at": "2026-02-26T10:00:00+00:00",
  "updated_at": "2026-02-26T10:00:00+00:00"
}
```

**Response 404:**
```json
{ "error": "Task not found." }
```

---

### `POST /api/tasks/{id}/subtasks`

Creates a subtask under the given task.

The parent must exist and must not itself be a subtask — only one level of
nesting is allowed.

**Request body:** same as `POST /api/tasks`

**Response 201:** the created subtask in flat shape (no `subtasks` key).

**Response 404:** parent task not found.

**Response 422:** parent is itself a subtask, or validation fails.

---

### `GET /api/tasks/{id}/subtasks`

Returns all subtasks of the given task.

**Response 200:** array of subtasks in flat shape (no `subtasks` key).

**Response 404:** parent task not found.

---

### `PATCH /api/tasks/{id}`

Partially updates a task. Only provided fields are changed.

**Request body** (all fields optional):
```json
{
  "title": "Updated title",
  "description": "Updated description",
  "status": "in_progress"
}
```

**Response 200:** updated task in full shape (includes `subtasks`).

**Response 404:** task not found.

**Response 422:** validation failure, or attempting to set status `done` on a
task that has subtasks not yet in `done` status.

---

### `DELETE /api/tasks/{id}`

Deletes a task and all its subtasks.

**Response 204:** no body.

**Response 404:** task not found.

---

## Validation Rules

| Field         | Rules                                          |
|---------------|------------------------------------------------|
| `title`       | Required, non-blank, max 255 characters        |
| `description` | Optional, max 1000 characters                  |
| `status`      | Must be one of: `todo`, `in_progress`, `done`  |

## Business Rules

- `GET /api/tasks` returns top-level tasks only — tasks where `parent_id` is null
- `GET /api/tasks/{id}` embeds a `subtasks` array (full shape)
- Subtasks use a flat response shape — no `subtasks` key
- A task cannot be marked `done` if any subtask has a status other than `done`
- Subtasks cannot have subtasks — one level of nesting only
- Deleting a parent task cascades to its subtasks

## Response Shapes

**Full shape** (used on single-task GET, POST, PATCH):
```
id, title, description, status, subtasks[], created_at, updated_at
```

**Flat shape** (used on list endpoints and subtask responses):
```
id, title, description, status, created_at, updated_at
```

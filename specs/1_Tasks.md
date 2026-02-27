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

Returns all top-level tasks (tasks with no parent) in flat shape. Subtasks are
excluded. Optional query parameter `status` filters by status.

### `POST /api/tasks`

Creates a new top-level task. Accepts `title` (required) and `description`
(optional). Returns 201 with the created task in full shape.

### `GET /api/tasks/{id}`

Returns a single task in full shape (includes subtasks). Returns 404 if not
found.

### `POST /api/tasks/{id}/subtasks`

Creates a subtask under the given task. Same request body as `POST /api/tasks`.
Returns 201 with the created subtask in flat shape. Returns 404 if parent not
found, 422 if parent is itself a subtask or validation fails.

### `GET /api/tasks/{id}/subtasks`

Returns all subtasks of the given task in flat shape. Returns 404 if parent not
found.

### `PATCH /api/tasks/{id}`

Partially updates a task. Only provided fields are changed. Accepts `title`,
`description`, and `status` — all optional. Returns 200 with updated task in
full shape. Returns 404 if not found, 422 on validation failure or if setting
status to `done` while subtasks are not all `done`.

### `DELETE /api/tasks/{id}`

Deletes a task and all its subtasks. Returns 204. Returns 404 if not found.

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

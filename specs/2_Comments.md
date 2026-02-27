# Feature Spec: Comments

## Purpose

Users can leave comments on tasks. Comments are append-only — they can be
created, listed, and deleted, but not edited. Deleting a task deletes all its
comments.

## Entities

### Comment

| Field        | Type      | Notes                                    |
|--------------|-----------|------------------------------------------|
| `id`         | integer   | Auto-incremented primary key             |
| `task_id`    | integer   | FK to `task.id`; required                |
| `body`       | string    | Required, non-blank, max 2000 characters |
| `created_at` | timestamp | Set automatically on creation            |

## Endpoints

### `POST /api/tasks/{id}/comments`

Creates a comment on the given task. Accepts `body` (required). Returns 201
with the created comment. Returns 404 if task not found, 422 on validation
failure.

### `GET /api/tasks/{id}/comments`

Returns all comments for the given task, ordered by `created_at` ascending.
Returns 404 if task not found.

### `DELETE /api/tasks/{taskId}/comments/{commentId}`

Deletes a single comment. Returns 204. Returns 404 if task or comment not
found.

## Validation Rules

| Field  | Rules                                    |
|--------|------------------------------------------|
| `body` | Required, non-blank, max 2000 characters |

## Business Rules

- Comments belong to a task; a comment cannot exist without a task
- Deleting a task cascades to all its comments
- Comments are ordered by `created_at` ascending when listed
- Comments cannot be edited — no PATCH endpoint

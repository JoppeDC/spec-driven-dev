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

Creates a comment on the given task.

**Request body:**
```json
{
  "body": "This looks good to me."
}
```

**Response 201:**
```json
{
  "id": 1,
  "task_id": 42,
  "body": "This looks good to me.",
  "created_at": "2026-02-26T10:00:00+00:00"
}
```

**Response 404:** task not found.

**Response 422** (validation failure):
```json
{
  "errors": {
    "body": "This value should not be blank."
  }
}
```

---

### `GET /api/tasks/{id}/comments`

Returns all comments for the given task, ordered by `created_at` ascending.

**Response 200:**
```json
[
  {
    "id": 1,
    "task_id": 42,
    "body": "This looks good to me.",
    "created_at": "2026-02-26T10:00:00+00:00"
  }
]
```

**Response 404:** task not found.

---

### `DELETE /api/tasks/{taskId}/comments/{commentId}`

Deletes a single comment.

**Response 204:** no body.

**Response 404:** task not found, or comment not found on that task.

---

## Validation Rules

| Field  | Rules                                    |
|--------|------------------------------------------|
| `body` | Required, non-blank, max 2000 characters |

## Business Rules

- Comments belong to a task; a comment cannot exist without a task
- Deleting a task cascades to all its comments
- Comments are ordered by `created_at` ascending when listed
- Comments cannot be edited — no PATCH endpoint

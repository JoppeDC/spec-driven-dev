# Feature Spec: Tasks

## Purpose

A task management API. Users can create and manage tasks, track their progress
through a defined lifecycle, and retrieve or remove tasks as needed.

## Entities

### Task

| Field         | Type          | Notes                                      |
|---------------|---------------|--------------------------------------------|
| `id`          | integer       | Auto-incremented primary key               |
| `title`       | string        | Required, max 255 characters               |
| `description` | string\|null  | Optional, max 1000 characters              |
| `status`      | enum          | `todo`, `in_progress`, `done`; default `todo` |
| `created_at`  | timestamp     | Set automatically on creation              |
| `updated_at`  | timestamp     | Updated automatically on every change      |

## Endpoints

### `GET /api/tasks`

Returns all tasks.

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

Creates a new task.

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

Returns a single task.

**Response 200:**
```json
{
  "id": 1,
  "title": "Write tests",
  "description": null,
  "status": "todo",
  "created_at": "2026-02-26T10:00:00+00:00",
  "updated_at": "2026-02-26T10:00:00+00:00"
}
```

**Response 404:**
```json
{ "error": "Task not found." }
```

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

**Response 200:** updated task.

**Response 404:** task not found.

**Response 422:** validation failure.

---

### `DELETE /api/tasks/{id}`

Deletes a task.

**Response 204:** no body.

**Response 404:** task not found.

---

## Validation Rules

| Field         | Rules                                          |
|---------------|------------------------------------------------|
| `title`       | Required, non-blank, max 255 characters        |
| `description` | Optional, max 1000 characters                  |
| `status`      | Must be one of: `todo`, `in_progress`, `done`  |

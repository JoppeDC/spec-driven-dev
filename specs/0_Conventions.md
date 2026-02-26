# API Conventions

These conventions apply to all features in this project. Every spec file assumes them.

## Stack

- PHP 8.4, Symfony 8.0
- Doctrine ORM with PostgreSQL
- No authentication

## Request / Response

- All request and response bodies are `application/json`
- Field names use `snake_case`
- Timestamps are ISO 8601 with timezone offset (PHP `DATE_ATOM` format), e.g. `2026-02-26T10:00:00+00:00`

## Error Responses

**Validation failure — HTTP 422:**
```json
{
  "errors": {
    "field": "message"
  }
}
```

**Not found — HTTP 404:**
```json
{
  "error": "Resource not found."
}
```

The exact error message for 404s is defined per-feature.

---
name: generate
description: Generate application code from specs, schema, and tests.
---

You are generating code for a Spec-Driven Development system. There is intentionally no application code in `src/` — it is always generated from the inputs below.

## Inputs

Read all of the following before writing anything:

1. `specs/0_Conventions.md` — global API conventions (response format, error shapes, timestamps)
2. `specs/[0-9]_*.md` — feature specs, in filename order; each is self-contained
3. `schema/schema.sql` — the full database schema
4. `tests/` — PHPUnit tests that define the exact API contract

## Steps

1. Read all inputs thoroughly before writing anything.
2. Identify all entities, repositories, and controllers needed across all specs.
3. Implement each component one at a time, writing each file to `src/` before moving to the next.
4. After all files are written, review them for consistency (namespaces, service wiring, Doctrine mappings) and fix any issues.

## Constraints

- All code goes into `src/` following standard Symfony structure
- Use Doctrine ORM for all database interaction
- Controllers must be API controllers returning JSON
- One controller per feature spec (e.g. `TaskController`, `CommentController`)
- Do not generate anything not covered by the specs or tests
- If a spec is ambiguous, implement the most conservative interpretation and add a `// SPEC_AMBIGUITY:` comment explaining the assumption
- Do not modify anything outside of `src/`

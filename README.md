# Spec-Driven Development PoC

A proof-of-concept for spec-driven development: feature specs and tests are written by ~hand~ AI, and an AI agent generates the application code from them.

This repository goes hand-in-hand with [this blogpost](https://joppe.dev/2026/02/26/i-deleted-my-source-code/).

## How it works

1. **Specs** (`specs/`) define the API — entities, endpoints, validation rules, and business rules
2. **Tests** (`tests/`) encode the expected behavior
3. **Schema** (`schema/`) defines the database structure
4. An AI agent reads these artifacts and generates all code in `src/`

The generated code is disposable — never committed, always regenerated from scratch.

## Tech stack

- PHP 8.4, Symfony 8.0
- Doctrine ORM
- PHPUnit
- Docker Compose for local development

## Project structure

```
specs/          Feature specs (source of truth for behavior)
tests/          PHPUnit tests (source of truth for correctness)
schema/         SQL schema (source of truth for data structure)
src/            Generated application code (not committed)
```

CREATE TYPE task_status AS ENUM ('todo', 'in_progress', 'done');

CREATE TABLE task (
    id          SERIAL PRIMARY KEY,
    title       VARCHAR(255)  NOT NULL,
    description VARCHAR(1000) NULL,
    status      task_status   NOT NULL DEFAULT 'todo',
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

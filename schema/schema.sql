CREATE TYPE task_status AS ENUM ('todo', 'in_progress', 'done');

CREATE TABLE task (
    id          SERIAL PRIMARY KEY,
    parent_id   INTEGER       NULL REFERENCES task(id) ON DELETE CASCADE,
    title       VARCHAR(255)  NOT NULL,
    description VARCHAR(1000) NULL,
    status      task_status   NOT NULL DEFAULT 'todo',
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE TABLE comment (
    id         SERIAL PRIMARY KEY,
    task_id    INTEGER       NOT NULL REFERENCES task(id) ON DELETE CASCADE,
    body       VARCHAR(2000) NOT NULL,
    created_at TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE TYPE activity_action AS ENUM (
    'task_created',
    'task_updated',
    'subtask_created',
    'comment_added',
    'comment_deleted'
);

CREATE TABLE activity_log (
    id         SERIAL PRIMARY KEY,
    task_id    INTEGER         NOT NULL REFERENCES task(id) ON DELETE CASCADE,
    action     activity_action NOT NULL,
    changes    JSONB           NULL,
    created_at TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS lms_courses (
    id           CHAR(26)      NOT NULL PRIMARY KEY,
    title        VARCHAR(255)  NOT NULL,
    slug         VARCHAR(255)  NOT NULL UNIQUE,
    description  TEXT          NULL,
    status       VARCHAR(32)   NOT NULL DEFAULT 'draft',
    created_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_lms_courses_status_slug (status, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

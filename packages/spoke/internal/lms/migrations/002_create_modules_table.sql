CREATE TABLE IF NOT EXISTS lms_modules (
    id           CHAR(26)      NOT NULL PRIMARY KEY,
    course_id    CHAR(26)      NOT NULL,
    title        VARCHAR(255)  NOT NULL,
    sort_order   INT           NOT NULL DEFAULT 0,
    created_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_lms_modules_course FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
    INDEX idx_lms_modules_course (course_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

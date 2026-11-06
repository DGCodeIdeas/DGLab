CREATE TABLE IF NOT EXISTS lms_course_versions (
    course_id       CHAR(26)      NOT NULL,
    version         INT UNSIGNED  NOT NULL,
    requirements_snapshot JSON NOT NULL,
    created_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (course_id, version),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
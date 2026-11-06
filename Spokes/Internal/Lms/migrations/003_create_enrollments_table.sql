CREATE TABLE IF NOT EXISTS lms_enrollments (
    id             CHAR(26)      NOT NULL PRIMARY KEY,
    course_id      CHAR(26)      NOT NULL,
    learner_id     CHAR(26)      NOT NULL,
    course_version INT UNSIGNED  NOT NULL DEFAULT 1,
    status         VARCHAR(32)   NOT NULL DEFAULT 'active',
    enrolled_at    TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at   TIMESTAMP(6)  NULL,
    _active_flag   SMALLINT      GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) VIRTUAL,
    CONSTRAINT fk_lms_enrollments_course FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_active_learner_course (learner_id, course_id, _active_flag),
    INDEX idx_lms_enrollments_learner (learner_id, status),
    INDEX idx_lms_enrollments_course_version (course_id, course_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
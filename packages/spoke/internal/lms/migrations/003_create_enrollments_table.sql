CREATE TABLE IF NOT EXISTS lms_enrollments (
    id           CHAR(26)      NOT NULL PRIMARY KEY,
    course_id    CHAR(26)      NOT NULL,
    learner_id   CHAR(26)      NOT NULL,
    status       VARCHAR(32)   NOT NULL DEFAULT 'active',
    enrolled_at  TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at TIMESTAMP(6)  NULL,
    CONSTRAINT fk_lms_enrollments_course FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE CASCADE,
    UNIQUE KEY uq_lms_enrollments_course_learner (course_id, learner_id),
    INDEX idx_lms_enrollments_learner (learner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lms_progress (
    enrollment_id CHAR(26)     NOT NULL,
    module_id     CHAR(26)     NOT NULL,
    status        VARCHAR(32)  NOT NULL DEFAULT 'not_started',
    started_at    TIMESTAMP(6) NULL,
    completed_at  TIMESTAMP(6) NULL,
    PRIMARY KEY (enrollment_id, module_id),
    CONSTRAINT fk_lms_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES lms_enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_progress_module FOREIGN KEY (module_id) REFERENCES lms_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

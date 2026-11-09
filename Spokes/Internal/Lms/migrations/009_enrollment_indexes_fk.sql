-- Migration 009b: Add indexes and foreign key in correct order
-- Step 1: Drop foreign key first (required before dropping index)
ALTER TABLE lms_enrollments
DROP FOREIGN KEY fk_lms_enrollments_course;

-- Step 2: Drop old index
ALTER TABLE lms_enrollments
DROP INDEX uq_lms_enrollments_course_learner;

-- Step 2: Add new unique index with generated column
ALTER TABLE lms_enrollments
ADD UNIQUE KEY uq_active_learner_course (learner_id, course_id, _active_flag);

-- Step 3: Re-add foreign key with RESTRICT
ALTER TABLE lms_enrollments
ADD CONSTRAINT fk_lms_enrollments_course FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE RESTRICT;

-- Step 4: Add index for course_version queries
ALTER TABLE lms_enrollments
ADD INDEX idx_lms_enrollments_course_version (course_id, course_version);
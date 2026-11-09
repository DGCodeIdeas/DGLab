-- Migration 008: Add course_version and active flag to enrollments
-- Step 1: Add columns
ALTER TABLE lms_enrollments
ADD COLUMN course_version INT UNSIGNED NOT NULL DEFAULT 1,
ADD COLUMN _active_flag SMALLINT GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) VIRTUAL;
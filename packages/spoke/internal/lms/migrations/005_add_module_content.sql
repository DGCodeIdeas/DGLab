-- Migration 005: Add content column to lms_modules for text-based module content.
-- Per SAAI directive: "For the first demonstration, text content stored in the database may be sufficient."

ALTER TABLE lms_modules ADD COLUMN content MEDIUMTEXT NULL AFTER sort_order;

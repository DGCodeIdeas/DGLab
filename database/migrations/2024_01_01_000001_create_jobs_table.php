<?php
/**
 * Migration: Create Jobs Table
 * 
 * Stores async job information for service processing.
 */

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;

class CreateJobsTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `jobs` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `service_id` VARCHAR(100) NOT NULL,
            `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            `input_data` JSON,
            `output_data` JSON,
            `progress` TINYINT UNSIGNED DEFAULT 0,
            `message` TEXT,
            `started_at` TIMESTAMP NULL,
            `completed_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_service_status` (`service_id`, `status`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->statement($sql);
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `jobs`');
    }
}

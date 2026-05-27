<?php
/**
 * Migration: Create Service Logs Table
 * 
 * Stores service processing logs for debugging and monitoring.
 */

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;

class CreateServiceLogsTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $driver = $this->db->getDriver();

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS `service_logs` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `service_id` VARCHAR(100) NOT NULL,
                `job_id` INTEGER NULL,
                `level` TEXT DEFAULT 'info',
                `message` TEXT NOT NULL,
                `context` TEXT,
                `ip_address` VARCHAR(45),
                `user_agent` VARCHAR(255),
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `service_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `service_id` VARCHAR(100) NOT NULL,
                `job_id` BIGINT UNSIGNED NULL,
                `level` ENUM('debug', 'info', 'warning', 'error', 'critical') DEFAULT 'info',
                `message` TEXT NOT NULL,
                `context` JSON,
                `ip_address` VARCHAR(45),
                `user_agent` VARCHAR(255),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_service_level` (`service_id`, `level`),
                INDEX `idx_job_id` (`job_id`),
                INDEX `idx_created_at` (`created_at`),
                INDEX `idx_level` (`level`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        $this->db->statement($sql);
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `service_logs`');
    }
}

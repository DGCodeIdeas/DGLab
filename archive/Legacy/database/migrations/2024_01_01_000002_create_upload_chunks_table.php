<?php
/**
 * Migration: Create Upload Chunks Table
 * 
 * Stores chunked upload session information.
 */

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;

class CreateUploadChunksTable implements MigrationInterface
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
            $sql = "CREATE TABLE IF NOT EXISTS `upload_chunks` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `session_id` VARCHAR(64) NOT NULL,
                `service_id` VARCHAR(100) NOT NULL,
                `filename` VARCHAR(255) NOT NULL,
                `file_size` INTEGER NOT NULL,
                `chunk_size` INTEGER NOT NULL,
                `total_chunks` INTEGER NOT NULL,
                `received_chunks` INTEGER DEFAULT 0,
                `chunks` TEXT,
                `metadata` TEXT,
                `status` TEXT DEFAULT 'active',
                `expires_at` DATETIME NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (`session_id`)
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `upload_chunks` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(64) NOT NULL,
                `service_id` VARCHAR(100) NOT NULL,
                `filename` VARCHAR(255) NOT NULL,
                `file_size` BIGINT UNSIGNED NOT NULL,
                `chunk_size` INT UNSIGNED NOT NULL,
                `total_chunks` INT UNSIGNED NOT NULL,
                `received_chunks` INT UNSIGNED DEFAULT 0,
                `chunks` JSON,
                `metadata` JSON,
                `status` ENUM('active', 'completed', 'expired', 'cancelled') DEFAULT 'active',
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_session` (`session_id`),
                INDEX `idx_status_expires` (`status`, `expires_at`),
                INDEX `idx_service_id` (`service_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        $this->db->statement($sql);
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `upload_chunks`');
    }
}

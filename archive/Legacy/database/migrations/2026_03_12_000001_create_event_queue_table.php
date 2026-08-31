<?php
/**
 * Migration: Create Event Queue Table
 *
 * Stores serialized async events and listeners for background execution.
 */

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;

class CreateEventQueueTable implements MigrationInterface
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
            $sql = "CREATE TABLE IF NOT EXISTS `event_queue` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `event_alias` TEXT NOT NULL,
                `payload` TEXT NOT NULL,
                `status` TEXT DEFAULT 'pending',
                `attempts` INTEGER DEFAULT 0,
                `error` TEXT,
                `available_at` DATETIME,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `event_queue` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `event_alias` VARCHAR(100) NOT NULL,
                `payload` LONGTEXT NOT NULL,
                `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                `attempts` TINYINT UNSIGNED DEFAULT 0,
                `error` TEXT,
                `available_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_status_available` (`status`, `available_at`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        $this->db->statement($sql);
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `event_queue`');
    }
}

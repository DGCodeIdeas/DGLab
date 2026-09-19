<?php
/**
 * Migration: Create Event Audit Tables
 *
 * Stores high-level event metadata and granular listener execution details.
 */

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;

class CreateEventAuditTables implements MigrationInterface
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
            // event_audit_logs
            $this->db->statement("CREATE TABLE IF NOT EXISTS `event_audit_logs` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `event_class` TEXT NOT NULL,
                `event_alias` TEXT NOT NULL,
                `dispatch_id` TEXT NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // listener_execution_logs
            $this->db->statement("CREATE TABLE IF NOT EXISTS `listener_execution_logs` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `audit_id` INTEGER NOT NULL,
                `listener` TEXT NOT NULL,
                `driver` TEXT NOT NULL,
                `status` TEXT DEFAULT 'pending',
                `latency_ms` INTEGER DEFAULT 0,
                `error_message` TEXT,
                `stack_trace` TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`audit_id`) REFERENCES `event_audit_logs` (`id`) ON DELETE CASCADE
            )");
        } else {
            // event_audit_logs
            $this->db->statement("CREATE TABLE IF NOT EXISTS `event_audit_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `event_class` VARCHAR(255) NOT NULL,
                `event_alias` VARCHAR(100) NOT NULL,
                `dispatch_id` VARCHAR(64) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_event_alias` (`event_alias`),
                INDEX `idx_dispatch_id` (`dispatch_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // listener_execution_logs
            $this->db->statement("CREATE TABLE IF NOT EXISTS `listener_execution_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `audit_id` BIGINT UNSIGNED NOT NULL,
                `listener` VARCHAR(255) NOT NULL,
                `driver` VARCHAR(50) NOT NULL,
                `status` ENUM('success', 'failed', 'retrying', 'dead_letter') DEFAULT 'success',
                `latency_ms` INT UNSIGNED DEFAULT 0,
                `error_message` TEXT,
                `stack_trace` LONGTEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_audit_id` FOREIGN KEY (`audit_id`) REFERENCES `event_audit_logs` (`id`) ON DELETE CASCADE,
                INDEX `idx_audit_status` (`audit_id`, `status`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `listener_execution_logs`');
        $this->db->statement('DROP TABLE IF EXISTS `event_audit_logs`');
    }
}

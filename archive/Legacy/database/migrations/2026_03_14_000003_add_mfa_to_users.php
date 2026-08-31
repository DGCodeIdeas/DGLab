<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;

class AddMfaToUsers implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $this->db->statement("ALTER TABLE `users` ADD COLUMN `mfa_secret` TEXT NULL");
        $this->db->statement("ALTER TABLE `users` ADD COLUMN `mfa_backup_codes` TEXT NULL"); // JSON stored as text
    }

    public function down(): void
    {
        // SQLite doesn't support DROP COLUMN easily in older versions
        // but for migration completeness:
        $this->db->statement("ALTER TABLE `users` DROP COLUMN `mfa_backup_codes`") ;
        $this->db->statement("ALTER TABLE `users` DROP COLUMN `mfa_secret`") ;
    }
}

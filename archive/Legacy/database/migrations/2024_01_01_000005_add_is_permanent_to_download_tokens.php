<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;

class AddIsPermanentToDownloadTokens implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $this->db->statement("ALTER TABLE `download_tokens` ADD COLUMN `is_permanent` TINYINT(1) DEFAULT 0");
    }

    public function down(): void
    {
        $this->db->statement("ALTER TABLE `download_tokens` DROP COLUMN `is_permanent`");
    }
}

<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateDownloadLogsTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $blueprint = new MigrationBlueprint('download_logs');

        $blueprint->id();
        $blueprint->string('file_path');
        $blueprint->string('driver', 50);
        $blueprint->integer('status_code');
        $blueprint->text('error_message')->nullable();
        $blueprint->string('ip_address', 45)->nullable();
        $blueprint->text('user_agent')->nullable();
        $blueprint->integer('download_time_ms')->default(0);
        $blueprint->bigInteger('bytes_served')->default(0);
        $blueprint->timestamp('created_at');

        $blueprint->index('status_code');
        $blueprint->index('created_at');

        $this->db->statement($blueprint->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `download_logs`');
    }
}

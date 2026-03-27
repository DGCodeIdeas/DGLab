<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateDownloadTokensTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $blueprint = new MigrationBlueprint('download_tokens');

        $blueprint->id();
        $blueprint->string('token', 64)->unique();
        $blueprint->string('file_path');
        $blueprint->string('driver', 50);
        $blueprint->timestamp('expires_at');
        $blueprint->integer('max_uses', true)->default(1);
        $blueprint->integer('use_count', true)->default(0);
        $blueprint->string('ip_address', 45)->nullable();
        $blueprint->boolean('enforce_ip')->default(0);
        $blueprint->string('user_agent')->nullable();
        $blueprint->timestamps();

        $this->db->statement($blueprint->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `download_tokens`');
    }
}

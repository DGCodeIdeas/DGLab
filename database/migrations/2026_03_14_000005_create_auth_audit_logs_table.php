<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateAuthAuditLogsTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $al = new MigrationBlueprint('auth_audit_logs');
        $al->id();
        $al->bigInteger('user_id', true)->nullable();
        $al->string('event_type', 50);
        $al->string('identifier')->nullable();
        $al->string('ip_address', 45)->nullable();
        $al->text('user_agent')->nullable();
        $al->text('metadata')->nullable(); // JSON stored as text
        $al->timestamps();

        $this->db->statement($al->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `auth_audit_logs`');
    }
}

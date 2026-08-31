<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateAuditLogsTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $al = new MigrationBlueprint('audit_logs');
        $al->id();
        $al->bigInteger('tenant_id', true)->nullable();
        $al->bigInteger('user_id', true)->nullable();
        $al->string('category', 50);
        $al->string('event_type', 50);
        $al->string('identifier')->nullable();
        $al->integer('status_code')->nullable();
        $al->string('ip_address', 45)->nullable();
        $al->text('user_agent')->nullable();
        $al->text('metadata')->nullable();
        $al->integer('latency_ms')->nullable();
        $al->timestamps();

        $this->db->statement($al->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `audit_logs`');
    }
}

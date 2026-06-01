<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateTenantsTables implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        // tenants
        $t = new MigrationBlueprint('tenants');
        $t->id();
        $t->string('identifier')->unique();
        $t->string('domain')->nullable()->unique();
        $t->text('config')->nullable(); // JSON stored as text
        $t->string('status', 20)->default('active');
        $t->timestamps();
        $this->db->statement($t->toSql());

        // tenant_data
        $td = new MigrationBlueprint('tenant_data');
        $td->id();
        $td->bigInteger('tenant_id', true);
        $td->string('key');
        $td->text('value')->nullable();
        $td->string('scope', 50)->default('system');
        $td->timestamps();
        $td->unique(['tenant_id', 'key']);
        $this->db->statement($td->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `tenant_data`');
        $this->db->statement('DROP TABLE IF EXISTS `tenants`');
    }
}

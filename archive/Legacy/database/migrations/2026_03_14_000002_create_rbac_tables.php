<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateRbacTables implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        // permissions
        $p = new MigrationBlueprint('permissions');
        $p->id();
        $p->string('name', 100)->unique();
        $p->text('description')->nullable();
        $p->timestamps();
        $this->db->statement($p->toSql());

        // roles
        $r = new MigrationBlueprint('roles');
        $r->id();
        $r->string('name', 100)->unique();
        $r->text('description')->nullable();
        $r->timestamps();
        $this->db->statement($r->toSql());

        // role_permissions
        $rp = new MigrationBlueprint('role_permissions');
        $rp->bigInteger('role_id', true);
        $rp->bigInteger('permission_id', true);
        // SQLite doesn't support named primary keys or multiple primary keys well in simple blueprints,
        // using unique and manual indexes if needed, but here simple columns work.
        $this->db->statement("CREATE TABLE `role_permissions` (
            `role_id` BIGINT UNSIGNED NOT NULL,
            `permission_id` BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (`role_id`, `permission_id`)
        )");

        // tenant_user_roles
        $tur = new MigrationBlueprint('tenant_user_roles');
        $tur->id();
        $tur->bigInteger('tenant_id', true);
        $tur->bigInteger('user_id', true);
        $tur->bigInteger('role_id', true);
        $tur->timestamps();
        $tur->unique(['tenant_id', 'user_id', 'role_id']);
        $this->db->statement($tur->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `tenant_user_roles`');
        $this->db->statement('DROP TABLE IF EXISTS `role_permissions`');
        $this->db->statement('DROP TABLE IF EXISTS `roles`');
        $this->db->statement('DROP TABLE IF EXISTS `permissions`');
    }
}

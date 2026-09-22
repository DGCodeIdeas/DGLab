<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateUserVerificationsTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $v = new MigrationBlueprint('user_verifications');
        $v->id();
        $v->bigInteger('user_id', true);
        $v->string('token', 100)->unique();
        $v->string('type', 20); // 'email', 'password_reset'
        $v->timestamp('expires_at');
        $v->timestamps();
        $this->db->statement($v->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `user_verifications`');
    }
}

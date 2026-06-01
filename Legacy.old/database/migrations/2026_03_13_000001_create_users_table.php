<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateUsersTable implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        $blueprint = new MigrationBlueprint('users');
        $blueprint->id();
        $blueprint->string('uuid', 36)->unique();
        $blueprint->string('email')->nullable()->unique();
        $blueprint->string('username', 100)->nullable()->unique();
        $blueprint->string('phone_number', 20)->nullable()->unique();
        $blueprint->string('password_hash');
        $blueprint->string('password_algo', 50)->default('argon2id');
        $blueprint->string('display_name')->nullable();
        $blueprint->text('avatar_url')->nullable();
        $blueprint->string('status', 20)->default('active');
        $blueprint->boolean('mfa_enabled')->default(false);
        $blueprint->timestamp('last_login_at')->nullable();
        $blueprint->timestamps();
        $blueprint->softDeletes();

        $this->db->statement($blueprint->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `users`');
    }
}

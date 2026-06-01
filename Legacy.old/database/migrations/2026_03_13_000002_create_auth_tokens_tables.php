<?php

use DGLab\Database\MigrationInterface;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class CreateAuthTokensTables implements MigrationInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function up(): void
    {
        // personal_access_tokens
        $pat = new MigrationBlueprint('personal_access_tokens');
        $pat->id();
        $pat->bigInteger('user_id', true);
        $pat->string('name');
        $pat->string('token_hash', 64)->unique();
        $pat->text('abilities')->nullable();
        $pat->timestamp('last_used_at')->nullable();
        $pat->timestamp('expires_at')->nullable();
        $pat->timestamps();
        $this->db->statement($pat->toSql());

        // remember_tokens
        $rt = new MigrationBlueprint('remember_tokens');
        $rt->id();
        $rt->bigInteger('user_id', true);
        $rt->string('token', 100)->unique();
        $rt->timestamp('expires_at');
        $rt->timestamps();
        $this->db->statement($rt->toSql());

        // user_social_accounts
        $sa = new MigrationBlueprint('user_social_accounts');
        $sa->id();
        $sa->bigInteger('user_id', true);
        $sa->string('provider_name', 50);
        $sa->string('provider_user_id');
        $sa->text('provider_data')->nullable();
        $sa->timestamps();
        $sa->unique(['provider_name', 'provider_user_id']);
        $this->db->statement($sa->toSql());
    }

    public function down(): void
    {
        $this->db->statement('DROP TABLE IF EXISTS `user_social_accounts`');
        $this->db->statement('DROP TABLE IF EXISTS `remember_tokens`');
        $this->db->statement('DROP TABLE IF EXISTS `personal_access_tokens`');
    }
}

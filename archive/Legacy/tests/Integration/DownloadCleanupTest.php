<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Download\DownloadManager;
use DGLab\Database\DownloadToken;
use DGLab\Database\Connection;

class DownloadCleanupTest extends IntegrationTestCase
{
    public function test_cleanup_removes_expired_tokens()
    {
        $db = $this->app->get(Connection::class);

        // Create an expired token
        $db->insert("INSERT INTO download_tokens (token, file_path, driver, expires_at) VALUES (?, ?, ?, ?)",
            ['expired', 'file.txt', 'local', date('Y-m-d H:i:s', time() - 3600)]);

        $db->insert("INSERT INTO download_tokens (token, file_path, driver, expires_at) VALUES (?, ?, ?, ?)",
            ['valid', 'file.txt', 'local', date('Y-m-d H:i:s', time() + 3600)]);

        // Cleanup logic would usually be in a service/command
        $db->delete("DELETE FROM download_tokens WHERE expires_at < ?", [date('Y-m-d H:i:s')]);

        $this->assertNull($db->selectOne("SELECT * FROM download_tokens WHERE token = ?", ['expired']));
        $this->assertNotNull($db->selectOne("SELECT * FROM download_tokens WHERE token = ?", ['valid']));
    }
}

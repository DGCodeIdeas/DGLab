<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Orchestrator\CIMonitor;

final class CIMonitorTest extends TestCase
{
    public function testRegisterRepo(): void
    {
        $monitor = new CIMonitor();
        $monitor->registerRepo('test-repo', 'https://ci.example.com/test-repo', 'token123');

        $repos = $monitor->getRegisteredRepos();
        self::assertContains('test-repo', $repos);
    }

    public function testCheckForUnregisteredRepo(): void
    {
        $monitor = new CIMonitor();
        $result = $monitor->check('nonexistent');

        self::assertSame('unknown', $result['status']);
        self::assertStringContainsString('not registered', $result['details']);
    }

    public function testCheckForNonExistentLocalRepo(): void
    {
        $monitor = new CIMonitor();
        $monitor->registerRepo('missing', '/tmp/nonexistent_path_xyz');

        $result = $monitor->check('missing');

        self::assertSame('unknown', $result['status']);
    }

    public function testCheckAll(): void
    {
        $monitor = new CIMonitor();
        $monitor->registerRepo('repo-a', 'https://ci.example.com/a');
        $monitor->registerRepo('repo-b', 'https://ci.example.com/b');

        $results = $monitor->checkAll();

        self::assertCount(2, $results);
        self::assertArrayHasKey('repo-a', $results);
        self::assertArrayHasKey('repo-b', $results);
    }

    public function testGetRegisteredRepos(): void
    {
        $monitor = new CIMonitor();
        $monitor->registerRepo('alpha', 'url-alpha');
        $monitor->registerRepo('beta', 'url-beta');

        $repos = $monitor->getRegisteredRepos();

        self::assertCount(2, $repos);
        self::assertSame(['alpha', 'beta'], $repos);
    }

    public function testGetRegisteredReposEmpty(): void
    {
        $monitor = new CIMonitor();
        self::assertSame([], $monitor->getRegisteredRepos());
    }

    public function testCheckWithLocalCiScript(): void
    {
        // Create a temp repo with a passing ci/run.php
        $tmpDir = \sys_get_temp_dir() . '/ci_test_' . \bin2hex(\random_bytes(4));
        \mkdir($tmpDir . '/ci', 0o777, true);

        $ciScript = <<<'PHP'
            <?php
            declare(strict_types=1);
            echo "All checks passed.\n";
            exit(0);
            PHP;

        \file_put_contents($tmpDir . '/ci/run.php', $ciScript);

        $monitor = new CIMonitor();
        $monitor->registerRepo('local-repo', $tmpDir);

        $result = $monitor->check('local-repo');

        self::assertSame('pass', $result['status']);

        // Cleanup
        $this->rmDir($tmpDir);
    }

    /**
     * Recursively remove a directory.
     */
    private function rmDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @\rmdir($item->getRealPath());
            } else {
                @\unlink($item->getRealPath());
            }
        }

        @\rmdir($dir);
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Orchestrator\RepoManager;

final class RepoManagerTest extends TestCase
{
    private string $testDir;

    private string $remoteDir;

    protected function setUp(): void
    {
        $this->testDir = \sys_get_temp_dir() . '/loom_test_' . \bin2hex(\random_bytes(4));
        $this->remoteDir = \sys_get_temp_dir() . '/loom_remote_' . \bin2hex(\random_bytes(4));

        // Initialize a bare repo to act as a "remote"
        \mkdir($this->remoteDir);
        \exec('git init --bare ' . \escapeshellarg($this->remoteDir) . ' 2>&1');

        // Initialize working dir for the manager
        \mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->testDir);
        $this->rmDir($this->remoteDir);
    }

    public function testClone(): void
    {
        $manager = new RepoManager($this->testDir);
        $result = $manager->clone($this->remoteDir, 'test-repo');

        self::assertTrue($result);
        self::assertDirectoryExists($this->testDir . '/test-repo');
        self::assertDirectoryExists($this->testDir . '/test-repo/.git');
    }

    public function testCheckout(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        // Create a branch on the remote
        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git checkout -b develop 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "init" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git push origin develop 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git checkout main 2>&1');

        $result = $manager->checkout('develop');
        self::assertTrue($result);
    }

    public function testTag(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        $manager2 = new RepoManager($cloneDir);
        $result = $manager2->tag('1.0.0', 'First release');
        self::assertTrue($result);
    }

    public function testTagDoesNotOverwrite(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        $manager2 = new RepoManager($cloneDir);
        $manager2->tag('1.0.0', 'First release');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $manager2->tag('1.0.0', 'Duplicate');
    }

    public function testTagWithInvalidFormat(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        $manager2 = new RepoManager($cloneDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer');
        $manager2->tag('v1.0.0');
    }

    public function testGetCurrentVersion(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag 0.2.0 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "second" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag 0.3.0 2>&1');

        $manager2 = new RepoManager($cloneDir);
        $version = $manager2->getCurrentVersion();

        self::assertSame('0.3.0', $version);
    }

    public function testGetCurrentVersionDefaultsToZeroZeroOne(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        $manager2 = new RepoManager($cloneDir);
        $version = $manager2->getCurrentVersion();

        self::assertSame('0.0.1', $version);
    }

    public function testGetLogSince(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "first" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag 0.1.0 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "second" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "third" 2>&1');

        $manager2 = new RepoManager($cloneDir);
        $log = $manager2->getLogSince('0.1.0');

        self::assertCount(2, $log);
        self::assertStringContainsString('third', $log[0] ?? '');
        self::assertStringContainsString('second', $log[1] ?? '');
    }

    public function testGetWorkingDir(): void
    {
        $manager = new RepoManager($this->testDir);
        self::assertSame($this->testDir, $manager->getWorkingDir());
    }

    public function testGetWorkingDirDefaults(): void
    {
        $default = \sys_get_temp_dir() . '/loom';
        $manager = new RepoManager();
        self::assertSame($default, $manager->getWorkingDir());
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

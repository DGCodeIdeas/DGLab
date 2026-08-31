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

    // ─── P0 gap 1: tag-prefix support ───────────────────────────────────────

    public function testGetCurrentVersionWithPrefixedTag(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        // Bare tags must be IGNORED when a prefix is configured.
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag 0.5.0 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag some-other-pkg-v0.9.0 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "feat: second" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag my-pkg-v1.2.0 2>&1');

        $manager2 = new RepoManager($cloneDir, 'my-pkg');
        $version = $manager2->getCurrentVersion();

        self::assertSame('1.2.0', $version);
    }

    public function testGetCurrentVersionWithLegacyPattern(): void
    {
        // core/container scenario: prefix is "core-container", but the
        // grandfathered v1.0.0 tag (no prefix) must also be readable.
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag v1.0.0 2>&1');

        $manager2 = new RepoManager($cloneDir, 'core-container');
        $manager2->setAdditionalTagPatterns(['/^v(\d+\.\d+\.\d+)$/']);
        $version = $manager2->getCurrentVersion();

        self::assertSame('1.0.0', $version);
    }

    public function testGetCurrentVersionWithLegacyPatternPrefersPrefixedTag(): void
    {
        // After a prefixed release lands (1.1.0), it must outrank the
        // grandfathered v1.0.0.
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag v1.0.0 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "feat: new" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag core-container-v1.1.0 2>&1');

        $manager2 = new RepoManager($cloneDir, 'core-container');
        $manager2->setAdditionalTagPatterns(['/^v(\d+\.\d+\.\d+)$/']);
        $version = $manager2->getCurrentVersion();

        self::assertSame('1.1.0', $version);
    }

    public function testTagWithPrefix(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        $manager2 = new RepoManager($cloneDir, 'my-pkg');
        $result = $manager2->tag('1.2.0', 'Release 1.2.0');
        self::assertTrue($result);

        // Verify the tag name was written with the prefix.
        $tags = \explode("\n", \trim((string) \shell_exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag --list') ?? ''));
        self::assertContains('my-pkg-v1.2.0', $tags);
    }

    public function testTagWithPrefixDoesNotOverwrite(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        $manager2 = new RepoManager($cloneDir, 'my-pkg');
        $manager2->tag('1.0.0', 'First release');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("'my-pkg-v1.0.0' already exists");
        $manager2->tag('1.0.0', 'Duplicate');
    }

    public function testTagWithPrefixRejectsBareTags(): void
    {
        // When a prefix is configured, tag() must NOT silently accept a
        // version that already includes the prefix.
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        $manager2 = new RepoManager($cloneDir, 'my-pkg');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer');
        $manager2->tag('my-pkg-v1.0.0');
    }

    public function testGetTagPrefixAndPathScope(): void
    {
        $manager = new RepoManager($this->testDir, 'my-pkg', 'packages/core/my-pkg');
        self::assertSame('my-pkg', $manager->getTagPrefix());
        self::assertSame('packages/core/my-pkg', $manager->getPathScope());

        $legacy = new RepoManager($this->testDir);
        self::assertNull($legacy->getTagPrefix());
        self::assertNull($legacy->getPathScope());
    }

    // ─── P0 gap 3: path-scoped commit analysis ──────────────────────────────

    public function testGetLogSinceWithPathScope(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag 0.1.0 2>&1');

        // Create a subdirectory structure mimicking a monorepo.
        \mkdir($cloneDir . '/packages/core/container', 0777, true);
        \mkdir($cloneDir . '/packages/core/event-dispatcher', 0777, true);

        // Commit touching only container
        \file_put_contents($cloneDir . '/packages/core/container/file.txt', 'a');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git add -A && git commit -m "feat(container): add file" 2>&1');

        // Commit touching only event-dispatcher
        \file_put_contents($cloneDir . '/packages/core/event-dispatcher/file.txt', 'b');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git add -A && git commit -m "feat(event-dispatcher): add file" 2>&1');

        $manager2 = new RepoManager($cloneDir, null, 'packages/core/container');
        $log = $manager2->getLogSince('0.1.0');

        // Must include ONLY the container commit — not the event-dispatcher one.
        self::assertCount(1, $log);
        self::assertStringContainsString('container', $log[0] ?? '');
        self::assertStringNotContainsString('event-dispatcher', $log[0] ?? '');
    }

    public function testGetLogSinceWithPathScopeOverride(): void
    {
        // Caller can override the constructor's pathScope at call time.
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag 0.1.0 2>&1');

        \mkdir($cloneDir . '/packages/core/a', 0777, true);
        \mkdir($cloneDir . '/packages/core/b', 0777, true);
        \file_put_contents($cloneDir . '/packages/core/a/file.txt', 'a');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git add -A && git commit -m "feat(a): add" 2>&1');
        \file_put_contents($cloneDir . '/packages/core/b/file.txt', 'b');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git add -A && git commit -m "feat(b): add" 2>&1');

        // No constructor scope; pass scope at call time.
        $manager2 = new RepoManager($cloneDir);
        $logA = $manager2->getLogSince('0.1.0', 'packages/core/a');
        $logB = $manager2->getLogSince('0.1.0', 'packages/core/b');

        self::assertCount(1, $logA);
        self::assertCount(1, $logB);
        self::assertStringContainsString('(a)', $logA[0] ?? '');
        self::assertStringContainsString('(b)', $logB[0] ?? '');
    }

    public function testGetLogSinceWithPrefixAndPathScope(): void
    {
        // Full monorepo scenario: prefixed tag + path scope.
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git tag my-pkg-v1.0.0 2>&1');

        \mkdir($cloneDir . '/packages/core/my-pkg', 0777, true);
        \file_put_contents($cloneDir . '/packages/core/my-pkg/file.txt', 'a');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git add -A && git commit -m "feat: new feature" 2>&1');

        $manager2 = new RepoManager($cloneDir, 'my-pkg', 'packages/core/my-pkg');
        $log = $manager2->getLogSince('1.0.0');

        self::assertCount(1, $log);
        self::assertStringContainsString('new feature', $log[0] ?? '');
    }

    // ─── P0 gap 4: tag push ─────────────────────────────────────────────────

    public function testPushTag(): void
    {
        // Set up a working repo + a bare remote.
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        // Create a tag locally.
        $manager2 = new RepoManager($cloneDir, 'my-pkg');
        $manager2->tag('1.0.0', 'Release 1.0.0');

        // Push it to the bare remote using a file:// URL.
        $result = $manager2->pushTag('1.0.0', $this->remoteDir);
        self::assertTrue($result);

        // Verify the tag landed on the remote.
        $remoteTags = \trim((string) \shell_exec('cd ' . \escapeshellarg($this->remoteDir) . ' && git tag --list') ?? '');
        self::assertContains('my-pkg-v1.0.0', \explode("\n", $remoteTags));
    }

    public function testPushTagRejectsInvalidVersion(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        $manager2 = new RepoManager($cloneDir, 'my-pkg');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid SemVer');
        $manager2->pushTag('not-a-version', $this->remoteDir);
    }

    public function testPushTagFailsWhenTagDoesNotExist(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        $manager2 = new RepoManager($cloneDir, 'my-pkg');

        $this->expectException(\RuntimeException::class);
        $manager2->pushTag('1.0.0', $this->remoteDir);
    }

    // ─── P1 gap 7: assertClean ─────────────────────────────────────────────

    public function testAssertCleanPassesOnCleanTree(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        $manager2 = new RepoManager($cloneDir);
        // Should not throw.
        $manager2->assertClean();
        $this->addToAssertionCount(1);
    }

    public function testAssertCleanFailsOnModifiedTrackedFile(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \file_put_contents($cloneDir . '/tracked.txt', 'original');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git add -A && git commit -m "add file" 2>&1');

        // Modify the tracked file.
        \file_put_contents($cloneDir . '/tracked.txt', 'modified');

        $manager2 = new RepoManager($cloneDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('modified');
        $manager2->assertClean();
    }

    public function testAssertCleanFailsOnUntrackedFile(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        // Create an untracked file.
        \file_put_contents($cloneDir . '/untracked.txt', 'surprise');

        $manager2 = new RepoManager($cloneDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('untracked');
        $manager2->assertClean();
    }

    public function testAssertCleanAllowsUntrackedInAllowlist(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        // Create an untracked file that IS in the allowlist.
        \file_put_contents($cloneDir . '/repos.json', '[]');

        $manager2 = new RepoManager($cloneDir);
        // Should not throw — repos.json is allowed.
        $manager2->assertClean(['repos.json']);
        $this->addToAssertionCount(1);
    }

    public function testAssertCleanFailsOnUntrackedNotInAllowlist(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        // Create an untracked file that is NOT in the allowlist.
        \file_put_contents($cloneDir . '/surprise.txt', 'boo');

        $manager2 = new RepoManager($cloneDir);

        $this->expectException(\RuntimeException::class);
        $manager2->assertClean(['repos.json']);
    }

    // ─── P2 gap 10: commitFile ───────────────────────────────────────────────

    public function testCommitFileCreatesCommitAndReturnsSha(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');

        // Create a file to commit.
        \file_put_contents($cloneDir . '/composer.json', '{"name": "test/pkg"}');
        // git requires committer identity to be set.
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git config user.email "test@example.com" && git config user.name "Test" 2>&1');

        $manager2 = new RepoManager($cloneDir);
        $sha = $manager2->commitFile('composer.json', 'chore: bump version to 1.0.0');

        // SHA is 40-char lowercase hex.
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $sha);

        // The commit message landed on HEAD.
        $headMessage = \trim((string) \shell_exec('cd ' . \escapeshellarg($cloneDir) . ' && git log -1 --format=%s') ?? '');
        self::assertSame('chore: bump version to 1.0.0', $headMessage);

        // The HEAD SHA matches what commitFile returned.
        $headSha = \trim((string) \shell_exec('cd ' . \escapeshellarg($cloneDir) . ' && git rev-parse HEAD') ?? '');
        self::assertSame($headSha, $sha);
    }

    public function testCommitFileStagesOnlyTheGivenPath(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git config user.email "test@example.com" && git config user.name "Test" 2>&1');

        // Create two files; only commit one.
        \file_put_contents($cloneDir . '/tracked.txt', 'a');
        \file_put_contents($cloneDir . '/untouched.txt', 'b');

        $manager2 = new RepoManager($cloneDir);
        $manager2->commitFile('tracked.txt', 'add tracked only');

        // The untouched file must NOT be in HEAD's tree.
        $files = \trim((string) \shell_exec('cd ' . \escapeshellarg($cloneDir) . ' && git ls-tree --name-only HEAD') ?? '');
        $fileList = \explode("\n", $files);
        self::assertContains('tracked.txt', $fileList);
        self::assertNotContains('untouched.txt', $fileList);

        // The untouched file should still be untracked in the working tree.
        $status = (string) \shell_exec('cd ' . \escapeshellarg($cloneDir) . ' && git status --porcelain') ?? '';
        self::assertStringContainsString('untouched.txt', $status);
    }

    public function testCommitFileThrowsOnInvalidPath(): void
    {
        $manager = new RepoManager($this->testDir);
        $manager->clone($this->remoteDir, 'test-repo');

        $cloneDir = $this->testDir . '/test-repo';
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git commit --allow-empty -m "initial" 2>&1');
        \exec('cd ' . \escapeshellarg($cloneDir) . ' && git config user.email "test@example.com" && git config user.name "Test" 2>&1');

        $manager2 = new RepoManager($cloneDir);

        // Path does not exist — git add will succeed (no-op) but git commit
        // will fail because there's nothing staged.
        $this->expectException(\RuntimeException::class);
        $manager2->commitFile('does-not-exist.txt', 'nothing to commit');
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

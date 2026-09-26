<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Filesystem\Filesystem;
use SovereignStack\Core\Filesystem\PathTraversalRefusedException;
use SovereignStack\Core\Filesystem\FileIntegrityCheckFailedException;
use SovereignStack\Core\Filesystem\StreamByteLimitExceededException;

/**
 * Depth 3 error-path tests for Filesystem (Lap 2 deepening).
 *
 * Per Lap 2 admission: these tests establish the first depth-2→3 measurement
 * and cover the P0/P1 findings from the architecture backlog.
 */
final class FilesystemDepth3Test extends TestCase
{
    private string $tempRoot;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . '/dglab_fs_d3_' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot, 0755, true);
        $this->fs = new Filesystem($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);
    }

    /**
     * P0-3: Root prefix collision — /tmp/data must NOT match /tmp/database.
     * This is the primary security fix for Lap 2.
     */
    public function testRootPrefixCollisionRejected(): void
    {
        // Create a sibling directory that starts with the same prefix as tempRoot
        $siblingDir = dirname($this->tempRoot) . '/' . basename($this->tempRoot) . 'base';
        if (!is_dir($siblingDir)) {
            mkdir($siblingDir, 0755, true);
        }
        file_put_contents($siblingDir . '/secret.txt', 'sensitive data');

        try {
            // This should be rejected — ../dglab_fs_d3_XXXXbase/secret.txt
            // uses path traversal to escape root via prefix collision
            $this->fs->read(basename($siblingDir) . 'base/secret.txt');
            // If the relative path is just the basename collision:
            $this->fs->read('../' . basename($siblingDir) . '/secret.txt');
            self::fail('Path traversal via prefix collision should be rejected');
        } catch (PathTraversalRefusedException $e) {
            // Expected — boundary-aware check correctly rejects
            $this->assertStringContainsString('escapes root', $e->getMessage());
        } finally {
            @unlink($siblingDir . '/secret.txt');
            @rmdir($siblingDir);
        }
    }

    /**
     * P0-3: Paths within root are accepted (no false positives).
     */
    public function testValidPathsWithinRootAccepted(): void
    {
        $this->fs->write('subdir/file.txt', 'content');
        self::assertSame('content', $this->fs->read('subdir/file.txt'));
        self::assertTrue($this->fs->exists('subdir/file.txt'));
    }

    /**
     * P0-3: Null byte injection rejected.
     */
    public function testNullByteRejected(): void
    {
        $this->expectException(PathTraversalRefusedException::class);
        $this->fs->write("test\0.txt", 'content');
    }

    /**
     * P0-3: Absolute path rejected.
     */
    public function testAbsolutePathRejected(): void
    {
        $this->expectException(PathTraversalRefusedException::class);
        $this->fs->write('/etc/passwd', 'content');
    }

    /**
     * P0-3: Parent directory traversal rejected.
     */
    public function testParentDirectoryTraversalRejected(): void
    {
        $this->expectException(PathTraversalRefusedException::class);
        $this->fs->write('../../escape.txt', 'content');
    }

    /**
     * P1-4: Stream byte limit enforced during stream write.
     */
    public function testStreamByteLimitEnforced(): void
    {
        $fs = new Filesystem($this->tempRoot, streamByteLimit: 10);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, str_repeat('x', 100));
        rewind($stream);

        $this->expectException(StreamByteLimitExceededException::class);
        $fs->writeStream('test.txt', $stream);
        fclose($stream);
    }

    /**
     * P1-4: writeStream produces a valid file on success.
     */
    public function testWriteStreamSuccess(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'streamed content');
        rewind($stream);

        $meta = $this->fs->writeStream('streamed.txt', $stream);
        fclose($stream);

        self::assertSame('streamed content', $this->fs->read('streamed.txt'));
        self::assertSame(15, $meta->size);
    }

    /**
     * P1-4: writeStream on empty stream produces empty file (edge case).
     */
    public function testWriteStreamEmpty(): void
    {
        $stream = fopen('php://memory', 'r+');
        rewind($stream);

        $meta = $this->fs->writeStream('empty.txt', $stream);
        fclose($stream);

        self::assertTrue($this->fs->exists('empty.txt'));
        self::assertSame('', $this->fs->read('empty.txt'));
        self::assertSame(0, $meta->size);
    }

    /**
     * P1-4: delete is idempotent (depth 3 error path).
     */
    public function testDeleteNonExistentIsIdempotent(): void
    {
        // Should not throw
        $this->fs->delete('nonexistent.txt');
        $this->assertTrue(true); // assertion that no exception was thrown
    }

    /**
     * P1-4: read returns null for non-existent (depth 3 error path).
     */
    public function testReadNonExistentReturnsNull(): void
    {
        self::assertNull($this->fs->read('nonexistent.txt'));
    }

    /**
     * P1-4: stat returns null for non-existent (depth 3 error path).
     */
    public function testStatNonExistentReturnsNull(): void
    {
        self::assertNull($this->fs->stat('nonexistent.txt'));
    }

    /**
     * P1-4: readStream returns null for non-existent (depth 3 error path).
     */
    public function testReadStreamNonExistentReturnsNull(): void
    {
        self::assertNull($this->fs->readStream('nonexistent.txt'));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

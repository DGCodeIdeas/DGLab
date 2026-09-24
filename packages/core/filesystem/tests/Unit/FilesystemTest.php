<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Filesystem\Filesystem;
use SovereignStack\Core\Filesystem\FileMetadata;
use SovereignStack\Core\Filesystem\PathTraversalRefusedException;

final class FilesystemTest extends TestCase
{
    private string $tempRoot;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . '/dglab_fs_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot, 0755, true);
        $this->fs = new Filesystem($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);
    }

    public function testWriteAndReadRoundTrip(): void
    {
        $this->fs->write('test.txt', 'Hello World');
        self::assertSame('Hello World', $this->fs->read('test.txt'));
    }

    public function testWriteReturnsFileMetadata(): void
    {
        $meta = $this->fs->write('test.txt', 'Hello World');
        self::assertInstanceOf(FileMetadata::class, $meta);
        self::assertSame(11, $meta->size);
        self::assertNotEmpty($meta->mimeType);
    }

    public function testReadReturnsNullForNonExistent(): void
    {
        self::assertNull($this->fs->read('nonexistent.txt'));
    }

    public function testExists(): void
    {
        $this->fs->write('test.txt', 'content');
        self::assertTrue($this->fs->exists('test.txt'));
        self::assertFalse($this->fs->exists('nonexistent.txt'));
    }

    public function testDeleteIsIdempotent(): void
    {
        $this->fs->write('test.txt', 'content');
        $this->fs->delete('test.txt');
        self::assertFalse($this->fs->exists('test.txt'));

        // Deleting again should not throw
        $this->fs->delete('test.txt');
        self::assertTrue(true);
    }

    public function testStatReturnsNullForNonExistent(): void
    {
        self::assertNull($this->fs->stat('nonexistent.txt'));
    }

    public function testStatReturnsMetadata(): void
    {
        $this->fs->write('test.txt', 'Hello World');
        $meta = $this->fs->stat('test.txt');
        self::assertNotNull($meta);
        self::assertSame(11, $meta->size);
    }

    public function testWriteCreatesParentDirectories(): void
    {
        $this->fs->write('a/b/c/test.txt', 'nested');
        self::assertSame('nested', $this->fs->read('a/b/c/test.txt'));
    }

    public function testPathTraversalWithDotDotRejected(): void
    {
        $this->expectException(PathTraversalRefusedException::class);
        $this->fs->write('../../escape.txt', 'content');
    }

    public function testPathTraversalWithAbsoluteRejected(): void
    {
        $this->expectException(PathTraversalRefusedException::class);
        $this->fs->write('/etc/passwd', 'content');
    }

    public function testPathTraversalWithNullByteRejected(): void
    {
        $this->expectException(PathTraversalRefusedException::class);
        $this->fs->write("test\0.txt", 'content');
    }

    public function testStreamByteLimitEnforced(): void
    {
        $fs = new Filesystem($this->tempRoot, streamByteLimit: 10);
        $this->expectException(\SovereignStack\Core\Filesystem\StreamByteLimitExceededException::class);
        $fs->write('test.txt', str_repeat('x', 100));
    }

    public function testWriteStreamAndRead(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'Streamed content');
        rewind($stream);
        $meta = $this->fs->writeStream('streamed.txt', $stream);
        fclose($stream);

        self::assertSame('Streamed content', $this->fs->read('streamed.txt'));
        self::assertSame(15, $meta->size);
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

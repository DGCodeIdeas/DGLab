<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Stream;
use SovereignStack\Core\Http\UploadedFile;

final class UploadedFileTest extends TestCase
{
    public function testConstructFromStream(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('content');
        $stream->rewind();
        $file = new UploadedFile($stream, 7, \UPLOAD_ERR_OK, 'test.txt', 'text/plain');
        self::assertSame(7, $file->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame('test.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
    }

    public function testGetStreamReturnsOriginalStream(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $file = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);
        self::assertSame($stream, $file->getStream());
    }

    public function testGetStreamThrowsAfterMove(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('data');
        $stream->rewind();
        $file = new UploadedFile($stream, 4, \UPLOAD_ERR_OK);
        $target = tempnam(sys_get_temp_dir(), 'upload_test_');
        $file->moveTo($target);
        $this->expectException(\RuntimeException::class);
        $file->getStream();
        @unlink($target);
    }

    public function testGetStreamThrowsOnUploadError(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $file = new UploadedFile($stream, 0, \UPLOAD_ERR_INI_SIZE);
        $this->expectException(\RuntimeException::class);
        $file->getStream();
    }

    public function testMoveToRejectsEmptyPath(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $file = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);
        $this->expectException(\InvalidArgumentException::class);
        $file->moveTo('');
    }

    public function testMoveToRejectsPathTraversal(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('data');
        $stream->rewind();
        $file = new UploadedFile($stream, 4, \UPLOAD_ERR_OK);
        $this->expectException(\InvalidArgumentException::class);
        $file->moveTo('/tmp/../etc/passwd');
    }

    public function testMoveToRejectsDotSegment(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('data');
        $stream->rewind();
        $file = new UploadedFile($stream, 4, \UPLOAD_ERR_OK);
        $this->expectException(\InvalidArgumentException::class);
        $file->moveTo('/tmp/./subdir/file');
    }

    public function testMoveToCopiesStreamContentsToTarget(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('Hello World');
        $stream->rewind();
        $file = new UploadedFile($stream, 11, \UPLOAD_ERR_OK);
        $target = tempnam(sys_get_temp_dir(), 'upload_test_');
        $file->moveTo($target);
        self::assertSame('Hello World', file_get_contents($target));
        @unlink($target);
    }

    public function testMoveToThrowsAfterAlreadyMoved(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('data');
        $stream->rewind();
        $file = new UploadedFile($stream, 4, \UPLOAD_ERR_OK);
        $target = tempnam(sys_get_temp_dir(), 'upload_test_');
        $file->moveTo($target);
        $this->expectException(\RuntimeException::class);
        $file->moveTo($target . '_2');
        @unlink($target);
    }

    public function testMoveToThrowsOnUploadError(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $file = new UploadedFile($stream, 0, \UPLOAD_ERR_PARTIAL);
        $this->expectException(\RuntimeException::class);
        $file->moveTo('/tmp/should_not_exist');
    }

    public function testClientMetadataIsNullByDefault(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $file = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);
        self::assertNull($file->getClientFilename());
        self::assertNull($file->getClientMediaType());
    }
}

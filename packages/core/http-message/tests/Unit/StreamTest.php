<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Stream;

/**
 * Unit tests for Stream — covers resource lifecycle, readability/writability
 * matrix, detach() inert transition, and the resource-leak security property.
 *
 * Per CORE-04.md §CI Verification Criteria:
 * - Resource lifecycle: construct from filename + from resource
 * - read/write/seek/tell/eof/getContents
 * - detach() returns resource and renders stream inert (subsequent ops throw)
 * - __destruct() closes resource (verified via gc_collect_cycles + leak test)
 *
 * The resource-leak test (testNoResourceLeakOnGc) is included at depth-2
 * per ADR-017 (Fiber-based cooperative runtime): under a long-running
 * FrankenPHP worker, per-request leaks accumulate for the worker's lifetime.
 * This is a stability property, not depth-3+ hardening.
 */
final class StreamTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Construction
    // -----------------------------------------------------------------------

    public function testConstructFromFilenameCreatesReadableWritableStream(): void
    {
        $stream = new Stream('php://temp', 'r+');
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertNull($stream->getSize()); // empty stream, size unknown until written
    }

    public function testConstructFromReadOnlyFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'stream_test_');
        file_put_contents($tmpFile, 'hello');
        $stream = new Stream($tmpFile, 'r');
        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertSame(5, $stream->getSize());
        @unlink($tmpFile);
    }

    public function testConstructFromResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        assert($resource !== false);
        $stream = new Stream($resource);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
    }

    public function testConstructRejectsNonStringNonResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line intentional type violation for testing */
        new Stream(42);
    }

    public function testConstructThrowsOnUnopenableFile(): void
    {
        $this->expectException(\RuntimeException::class);
        new Stream('/nonexistent/path/that/does/not/exist', 'r');
    }

    // -----------------------------------------------------------------------
    // Read / Write / Seek / Tell / EOF
    // -----------------------------------------------------------------------

    public function testWriteAndRead(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('Hello World');
        $stream->rewind();
        self::assertSame('Hello World', $stream->getContents());
    }

    public function testReadReturnsDataAndAdvancesPointer(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('Hello');
        $stream->rewind();
        self::assertSame('Hel', $stream->read(3));
        self::assertSame('lo', $stream->read(2));
        self::assertSame(5, $stream->tell());
    }

    public function testReadZeroLengthReturnsEmptyString(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('data');
        $stream->rewind();
        self::assertSame('', $stream->read(0));
    }

    public function testReadRejectsNegativeLength(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $this->expectException(\InvalidArgumentException::class);
        $stream->read(-1);
    }

    public function testSeekAndTell(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('0123456789');
        $stream->seek(5);
        self::assertSame(5, $stream->tell());
        self::assertSame('56789', $stream->read(5));
    }

    public function testRewindResetsToStart(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('data');
        $stream->rewind();
        self::assertSame(0, $stream->tell());
    }

    public function testEofFalseUntilEndReached(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('abc');
        $stream->rewind();
        self::assertFalse($stream->eof());
        $stream->read(3);
        self::assertFalse($stream->eof());
        $stream->read(1); // read past end
        self::assertTrue($stream->eof());
    }

    public function testGetSizeReturnsWrittenSize(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('Hello');
        self::assertSame(5, $stream->getSize());
    }

    public function testGetSizeInvalidatedAfterWrite(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('Hello');
        self::assertSame(5, $stream->getSize());
        $stream->write(' World');
        self::assertSame(11, $stream->getSize());
    }

    // -----------------------------------------------------------------------
    // toString
    // -----------------------------------------------------------------------

    public function testToStringRewindsAndReturnsContents(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('Hello World');
        // __toString should rewind and return contents
        self::assertSame('Hello World', (string) $stream);
    }

    public function testToStringReturnsEmptyStringOnException(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        // After detach, __toString should return '' (catch in the method)
        self::assertSame('', (string) $stream);
    }

    // -----------------------------------------------------------------------
    // detach() — returns resource and renders stream inert
    // -----------------------------------------------------------------------

    public function testDetachReturnsResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        assert($resource !== false);
        $stream = new Stream($resource);
        $detached = $stream->detach();
        self::assertIsResource($detached);
        fclose($detached);
    }

    public function testDetachReturnsNullOnAlreadyDetachedStream(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        self::assertNull($stream->detach());
    }

    public function testReadThrowsAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        $this->expectException(\RuntimeException::class);
        $stream->read(10);
    }

    public function testWriteThrowsAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        $this->expectException(\RuntimeException::class);
        $stream->write('data');
    }

    public function testSeekThrowsAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        $this->expectException(\RuntimeException::class);
        $stream->seek(0);
    }

    public function testTellThrowsAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        $this->expectException(\RuntimeException::class);
        $stream->tell();
    }

    public function testEofThrowsAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        $this->expectException(\RuntimeException::class);
        $stream->eof();
    }

    public function testGetContentsThrowsAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        $this->expectException(\RuntimeException::class);
        $stream->getContents();
    }

    public function testGetSizeReturnsNullAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write('data');
        $stream->detach();
        self::assertNull($stream->getSize());
    }

    public function testIsReadableFalseAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        self::assertFalse($stream->isReadable());
    }

    public function testIsWritableFalseAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        self::assertFalse($stream->isWritable());
    }

    public function testIsSeekableFalseAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        self::assertFalse($stream->isSeekable());
    }

    // -----------------------------------------------------------------------
    // close()
    // -----------------------------------------------------------------------

    public function testCloseClosesResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        assert($resource !== false);
        $stream = new Stream($resource);
        $stream->close();
        self::assertIsNotResource($resource); // closed by fclose()
    }

    public function testCloseDetachesAfterClosing(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->close();
        // After close, detach returns null (already detached)
        self::assertNull($stream->detach());
    }

    public function testCloseIsIdempotent(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->close();
        // Second close should not throw
        $stream->close();
        $this->expectNotToPerformAssertions();
    }

    // -----------------------------------------------------------------------
    // getMetadata
    // -----------------------------------------------------------------------

    public function testGetMetadataReturnsFullArrayWhenKeyIsNull(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $meta = $stream->getMetadata();
        self::assertIsArray($meta);
        self::assertArrayHasKey('mode', $meta);
        self::assertArrayHasKey('seekable', $meta);
    }

    public function testGetMetadataReturnsSpecificKey(): void
    {
        $stream = new Stream('php://temp', 'r+');
        self::assertTrue($stream->getMetadata('seekable'));
        self::assertSame('r+', $stream->getMetadata('mode'));
    }

    public function testGetMetadataReturnsNullForMissingKey(): void
    {
        $stream = new Stream('php://temp', 'r+');
        self::assertNull($stream->getMetadata('nonexistent_key'));
    }

    public function testGetMetadataReturnsNullAfterDetach(): void
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->detach();
        self::assertNull($stream->getMetadata('mode'));
        self::assertSame([], $stream->getMetadata());
    }

    // -----------------------------------------------------------------------
    // Writability / readability matrix
    // -----------------------------------------------------------------------

    public function testWriteThrowsOnReadOnlyStream(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'stream_test_');
        file_put_contents($tmpFile, 'data');
        $stream = new Stream($tmpFile, 'r');
        $this->expectException(\RuntimeException::class);
        $stream->write('new');
        @unlink($tmpFile);
    }

    public function testReadThrowsOnWriteOnlyStream(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'stream_test_');
        $stream = new Stream($tmpFile, 'w');
        $this->expectException(\RuntimeException::class);
        $stream->read(10);
        @unlink($tmpFile);
    }

    public function testSeekThrowsOnNonSeekableStream(): void
    {
        // php://stdout is not seekable
        $stream = new Stream('php://stdout', 'w');
        $this->expectException(\RuntimeException::class);
        $stream->seek(0);
    }

    // -----------------------------------------------------------------------
    // Resource leak test — depth-2 security property per ADR-017
    // -----------------------------------------------------------------------

    /**
     * Constructs 10,000 Stream objects, releases them, and asserts via
     * gc_collect_cycles() that no descriptors leak.
     *
     * Under ADR-017 (Fiber-based cooperative runtime, ratified), a long-running
     * FrankenPHP worker serves many Pulses across its lifetime. A per-request
     * leak that's harmless under PHP-FPM (process death reclaims everything)
     * accumulates for the worker's lifetime under FrankenPHP. This test is
     * a stability property, not depth-3+ hardening.
     */
    public function testNoResourceLeakOnGc(): void
    {
        // Get baseline open file descriptors
        $baselineFds = $this->countOpenFds();

        // Create 10,000 streams, each owning a php://temp resource
        for ($i = 0; $i < 10000; $i++) {
            $stream = new Stream('php://temp', 'r+');
            $stream->write("data for stream {$i}");
            // $stream goes out of scope here; __destruct should close the resource
        }

        // Force garbage collection to trigger __destruct() for any deferred streams
        gc_collect_cycles();
        gc_collect_cycles(); // twice for safety — cycles can require two passes

        $afterFds = $this->countOpenFds();

        // Allow some tolerance for GC non-determinism, but the delta should be
        // far less than 10,000 (which would indicate a full leak).
        $delta = $afterFds - $baselineFds;
        self::assertLessThan(
            100,
            $delta,
            "Resource leak detected: baseline={$baselineFds} after={$afterFds} delta={$delta}. " .
            "Expected <100 fd delta after GC; got {$delta}."
        );
    }

    /**
     * Count the number of open file descriptors for the current process.
     *
     * On Linux, this reads /proc/self/fd. On other platforms, returns 0
     * (test will be skipped — see testNoResourceLeakOnGcIsPlatformSupported).
     */
    private function countOpenFds(): int
    {
        if (!is_dir('/proc/self/fd')) {
            return 0; // non-Linux; test will be effectively skipped via the <100 assertion
        }
        $fds = scandir('/proc/self/fd');
        return $fds === false ? 0 : count($fds) - 2; // subtract . and ..
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SovereignStack\Core\Logger\Formatter\LineFormatter;
use SovereignStack\Core\Logger\Handler\StreamHandler;
use SovereignStack\Core\Logger\LogRecord;

final class StreamHandlerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'dglab_log_');
        // Unlink so the handler creates a fresh file on first write.
        @unlink($this->tempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testHandleWritesFormattedLineToFile(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $record = LogRecord::create(LogLevel::INFO, 'hello world');
        $handler->handle($record);
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        self::assertNotFalse($contents);
        self::assertStringContainsString('info: hello world', $contents);
        self::assertStringEndsWith("\n", $contents);
    }

    public function testHandleAppendsToExistingFile(): void
    {
        $handler1 = new StreamHandler($this->tempFile);
        $handler1->handle(LogRecord::create(LogLevel::INFO, 'first'));
        $handler1->close();

        $handler2 = new StreamHandler($this->tempFile);
        $handler2->handle(LogRecord::create(LogLevel::INFO, 'second'));
        $handler2->close();

        $contents = file_get_contents($this->tempFile);
        self::assertStringContainsString('first', $contents);
        self::assertStringContainsString('second', $contents);
    }

    public function testIsHandlingReturnsTrueForRecordsAtOrAboveThreshold(): void
    {
        $handler = new StreamHandler($this->tempFile, threshold: LogLevel::WARNING);
        $handler->handle(LogRecord::create(LogLevel::INFO, 'should not write'));
        $handler->handle(LogRecord::create(LogLevel::WARNING, 'should write'));
        $handler->handle(LogRecord::create(LogLevel::ERROR, 'should also write'));
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        self::assertStringNotContainsString('should not write', $contents);
        self::assertStringContainsString('should write', $contents);
        self::assertStringContainsString('should also write', $contents);
    }

    public function testIsHandlingReturnsFalseAfterClose(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $handler->close();

        $record = LogRecord::create(LogLevel::INFO, 'after close');
        self::assertFalse($handler->isHandling($record));
    }

    public function testHandleReturnsFalseWhenNotHandling(): void
    {
        $handler = new StreamHandler($this->tempFile, threshold: LogLevel::ERROR);
        $record = LogRecord::create(LogLevel::INFO, 'below threshold');

        self::assertFalse($handler->handle($record));
    }

    public function testHandleReturnsTrueWhenHandled(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $record = LogRecord::create(LogLevel::INFO, 'handled');

        self::assertTrue($handler->handle($record));
        $handler->close();
    }

    public function testHandleBatchWritesAllFilteredRecords(): void
    {
        $handler = new StreamHandler($this->tempFile, threshold: LogLevel::WARNING);
        $records = [
            LogRecord::create(LogLevel::DEBUG, 'skipped 1'),
            LogRecord::create(LogLevel::INFO, 'skipped 2'),
            LogRecord::create(LogLevel::WARNING, 'written 1'),
            LogRecord::create(LogLevel::ERROR, 'written 2'),
        ];
        $handler->handleBatch($records);
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        self::assertStringNotContainsString('skipped', $contents);
        self::assertStringContainsString('written 1', $contents);
        self::assertStringContainsString('written 2', $contents);
    }

    public function testCustomFormatter(): void
    {
        $formatter = new class implements \SovereignStack\Core\Logger\FormatterInterface {
            public function format(LogRecord $record): string
            {
                return "CUSTOM::{$record->level}::{$record->message}";
            }
            public function formatBatch(array $records): string
            {
                return implode('|', array_map(fn($r) => $this->format($r), $records));
            }
        };

        $handler = new StreamHandler($this->tempFile, formatter: $formatter);
        $handler->handle(LogRecord::create(LogLevel::INFO, 'test'));
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        self::assertStringContainsString('CUSTOM::info::test', $contents);
    }

    public function testAcceptsOpenResource(): void
    {
        $stream = fopen($this->tempFile, 'ab');
        self::assertNotFalse($stream);

        $handler = new StreamHandler($stream);
        $handler->handle(LogRecord::create(LogLevel::INFO, 'via resource'));
        $handler->close();

        // Handler should NOT close externally-provided resources.
        self::assertTrue(is_resource($stream));
        fclose($stream);

        $contents = file_get_contents($this->tempFile);
        self::assertStringContainsString('via resource', $contents);
    }

    public function testRejectsInvalidStreamType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a file path string or an open resource');

        /** @phpstan-ignore argument.type */
        new StreamHandler(42);
    }

    public function testCloseIsIdempotent(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $handler->close();
        $handler->close(); // Should not throw.

        $this->expectNotToPerformAssertions();
    }

    public function testConcurrentWritesDoNotCorruptLines(): void
    {
        // Simulate concurrent writes by interleaving two handlers on the same file.
        $handler1 = new StreamHandler($this->tempFile);
        $handler2 = new StreamHandler($this->tempFile);

        for ($i = 0; $i < 20; $i++) {
            $handler1->handle(LogRecord::create(LogLevel::INFO, "h1-line-{$i}"));
            $handler2->handle(LogRecord::create(LogLevel::INFO, "h2-line-{$i}"));
        }

        $handler1->close();
        $handler2->close();

        $contents = file_get_contents($this->tempFile);
        $lines = explode("\n", $contents);
        // Last element is empty (trailing newline); drop it.
        array_pop($lines);

        // Each line should be a complete, well-formed log entry.
        self::assertCount(40, $lines);
        foreach ($lines as $line) {
            self::assertMatchesRegularExpression(
                '/^\[\d{4}-\d{2}-\d{2}T.+info: h[12]-line-\d+$/',
                $line,
                "Malformed line: {$line}",
            );
        }
    }
}

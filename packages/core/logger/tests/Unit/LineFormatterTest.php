<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SovereignStack\Core\Logger\Formatter\LineFormatter;
use SovereignStack\Core\Logger\LogRecord;

final class LineFormatterTest extends TestCase
{
    public function testFormatProducesBaselineShape(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'Hello world');
        $formatted = (new LineFormatter())->format($record);

        self::assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}\] info: Hello world$/',
            $formatted,
        );
    }

    public function testFormatInterpolatesPlaceholders(): void
    {
        $record = LogRecord::create(
            LogLevel::INFO,
            'User {id} logged in from {ip}',
            ['id' => 42, 'ip' => '10.0.0.1'],
        );
        $formatted = (new LineFormatter())->format($record);

        self::assertStringContainsString('User 42 logged in from 10.0.0.1', $formatted);
    }

    public function testFormatLeavesUnknownPlaceholdersAsIs(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'Hello {missing}');
        $formatted = (new LineFormatter())->format($record);

        self::assertStringContainsString('Hello {missing}', $formatted);
    }

    public function testFormatAppendsContextWhenPresent(): void
    {
        $record = LogRecord::create(
            LogLevel::WARNING,
            'slow query',
            ['duration_ms' => 1500, 'sql' => 'SELECT * FROM users'],
        );
        $formatted = (new LineFormatter())->format($record);

        self::assertStringContainsString('duration_ms=1500', $formatted);
        self::assertStringContainsString("sql='SELECT * FROM users'", $formatted);
    }

    public function testFormatOmitsEmptyContext(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'no context');
        $formatted = (new LineFormatter())->format($record);

        // No trailing {} from empty context
        self::assertStringEndsNotWith(' {}', $formatted);
        self::assertStringEndsWith('no context', $formatted);
    }

    public function testFormatAppendsExceptionStackTrace(): void
    {
        $e = new \RuntimeException('boom');
        $record = LogRecord::create(LogLevel::ERROR, 'failed', ['exception' => $e]);
        $formatted = (new LineFormatter())->format($record);

        self::assertStringContainsString('Exception: RuntimeException', $formatted);
        self::assertStringContainsString("'boom'", $formatted);
        self::assertStringContainsString('at ', $formatted);
    }

    public function testFormatBatchJoinsWithNewlines(): void
    {
        $records = [
            LogRecord::create(LogLevel::INFO, 'first'),
            LogRecord::create(LogLevel::WARNING, 'second'),
            LogRecord::create(LogLevel::ERROR, 'third'),
        ];
        $formatted = (new LineFormatter())->formatBatch($records);

        $lines = explode("\n", $formatted);
        self::assertCount(3, $lines);
        self::assertStringContainsString('first', $lines[0]);
        self::assertStringContainsString('second', $lines[1]);
        self::assertStringContainsString('third', $lines[2]);
    }

    public function testCustomDateFormat(): void
    {
        $timestamp = new \DateTimeImmutable('2026-01-15T10:30:45+00:00');
        $record = new LogRecord($timestamp, LogLevel::INFO, 'test');
        $formatter = new LineFormatter(dateFormat: 'Y-m-d H:i:s');

        $formatted = $formatter->format($record);
        self::assertStringContainsString('[2026-01-15 10:30:45]', $formatted);
    }

    public function testContextArrayValuesRenderedAsJson(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'with array', ['tags' => ['a', 'b', 'c']]);
        $formatted = (new LineFormatter())->format($record);

        self::assertStringContainsString('tags=["a","b","c"]', $formatted);
    }

    // --- P3 Batch 6 ---

    public function testFormatWithEmptyMessageAndEmptyContextProducesMinimalLine(): void
    {
        $record = \SovereignStack\Core\Logger\LogRecord::create(
            level: 'info',
            message: '',
            context: [],
        );
        $formatter = new LineFormatter();
        $formatted = $formatter->format($record);
        self::assertNotEmpty($formatted, 'Empty message should still produce a log line');
    }

    public function testFormatSanitizesNewlinesInMessage(): void
    {
        $record = \SovereignStack\Core\Logger\LogRecord::create(
            level: 'info',
            message: "line1\nline2\rline3",
            context: [],
        );
        $formatter = new LineFormatter();
        $formatted = $formatter->format($record);
        // Log injection prevention: no raw newlines in the formatted output
        self::assertStringNotContainsString("\n", trim($formatted), 'Newlines should be sanitized');
    }
}

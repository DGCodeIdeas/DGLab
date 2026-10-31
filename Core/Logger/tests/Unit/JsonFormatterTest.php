<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SovereignStack\Core\Logger\Formatter\JsonFormatter;
use SovereignStack\Core\Logger\LogRecord;

final class JsonFormatterTest extends TestCase
{
    public function testFormatProducesValidJson(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'Hello');
        $formatted = (new JsonFormatter())->format($record);

        $decoded = json_decode($formatted, true);
        self::assertIsArray($decoded);
        self::assertSame('info', $decoded['level']);
        self::assertSame('Hello', $decoded['message']);
        self::assertArrayHasKey('timestamp', $decoded);
    }

    public function testFormatInterpolatesPlaceholders(): void
    {
        $record = LogRecord::create(
            LogLevel::WARNING,
            'User {id} action {action}',
            ['id' => 99, 'action' => 'login'],
        );
        $formatted = (new JsonFormatter())->format($record);

        $decoded = json_decode($formatted, true);
        self::assertSame('User 99 action login', $decoded['message']);
        self::assertSame(99, $decoded['id']);
        self::assertSame('login', $decoded['action']);
    }

    public function testFormatMergesContextIntoTopLevel(): void
    {
        $record = LogRecord::create(
            LogLevel::ERROR,
            'db error',
            ['query' => 'SELECT 1', 'duration_ms' => 200],
        );
        $formatted = (new JsonFormatter())->format($record);

        $decoded = json_decode($formatted, true);
        self::assertSame('SELECT 1', $decoded['query']);
        self::assertSame(200, $decoded['duration_ms']);
    }

    public function testFormatMergesExtraIntoTopLevel(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'test', [], ['pid' => 1234, 'worker_id' => 'w1']);
        $formatted = (new JsonFormatter())->format($record);

        $decoded = json_decode($formatted, true);
        self::assertSame(1234, $decoded['pid']);
        self::assertSame('w1', $decoded['worker_id']);
    }

    public function testFormatSuffixesCollidingContextKeys(): void
    {
        // 'level' is a reserved field — context 'level' should become 'level_context'.
        $record = LogRecord::create(LogLevel::INFO, 'test', ['level' => 'advanced']);
        $formatted = (new JsonFormatter())->format($record);

        $decoded = json_decode($formatted, true);
        self::assertSame('info', $decoded['level']);
        self::assertSame('advanced', $decoded['level_context']);
    }

    public function testFormatRendersExceptionAsStructuredObject(): void
    {
        $e = new \RuntimeException('boom', 0);
        $record = LogRecord::create(LogLevel::ERROR, 'failed', ['exception' => $e]);
        $formatted = (new JsonFormatter())->format($record);

        $decoded = json_decode($formatted, true);
        self::assertIsArray($decoded['exception']);
        self::assertSame('RuntimeException', $decoded['exception']['class']);
        self::assertSame('boom', $decoded['exception']['message']);
        self::assertArrayHasKey('file', $decoded['exception']);
        self::assertArrayHasKey('line', $decoded['exception']);
        self::assertArrayHasKey('trace', $decoded['exception']);
    }

    public function testFormatBatchProducesJsonLines(): void
    {
        $records = [
            LogRecord::create(LogLevel::INFO, 'first'),
            LogRecord::create(LogLevel::WARNING, 'second'),
        ];
        $formatted = (new JsonFormatter())->formatBatch($records);

        $lines = explode("\n", $formatted);
        self::assertCount(2, $lines);

        $first = json_decode($lines[0], true);
        $second = json_decode($lines[1], true);
        self::assertSame('first', $first['message']);
        self::assertSame('second', $second['message']);
    }

    public function testPrettyPrintOption(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'pretty');
        $formatted = (new JsonFormatter(prettyPrint: true))->format($record);

        self::assertStringContainsString("\n", $formatted);
        self::assertStringContainsString('    ', $formatted); // 4-space indent
    }

    public function testNoEscapedSlashes(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'path test', ['path' => '/var/log/app.log']);
        $formatted = (new JsonFormatter())->format($record);

        // JSON_UNESCAPED_SLASHES means / is NOT escaped as \/
        self::assertStringContainsString('/var/log/app.log', $formatted);
        self::assertStringNotContainsString('\/var\/log', $formatted);
    }
}

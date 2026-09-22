<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Logger\Formatter\LineFormatter;
use SovereignStack\Core\Logger\LogRecord;

/**
 * LineFormatter log injection tests — verify \n/\r in user-supplied values
 * are escaped, preventing fake log entry injection (CWE-117).
 *
 * @package SovereignStack\Core\Logger\Tests\Unit
 */
final class LineFormatterLogInjectionTest extends TestCase
{
    public function testNewlinesInMessageAreEscaped(): void
    {
        $formatter = new LineFormatter();
        $record = LogRecord::create('info', "Hello\n[2026-01-01] emergency: system compromised");

        $output = $formatter->format($record);
        // The fake emergency line must NOT appear as a separate log entry.
        self::assertStringNotContainsString("\n[2026-01-01] emergency:", $output);
        // The \n must be escaped to \\n.
        self::assertStringContainsString('\\n', $output);
    }

    public function testCarriageReturnsInMessageAreEscaped(): void
    {
        $formatter = new LineFormatter();
        $record = LogRecord::create('info', "Hello\r\nInjected");

        $output = $formatter->format($record);
        self::assertStringNotContainsString("\r\n", $output);
        self::assertStringContainsString('\\r', $output);
        self::assertStringContainsString('\\n', $output);
    }

    public function testNewlinesInContextValuesAreEscaped(): void
    {
        $formatter = new LineFormatter();
        $record = LogRecord::create(
            'info',
            'User {name} login',
            ['name' => "admin\n[2026-01-01] emergency: system compromised"],
        );

        $output = $formatter->format($record);
        self::assertStringNotContainsString("\n[2026-01-01] emergency:", $output);
        self::assertStringContainsString('\\n', $output);
    }

    public function testTabsAreEscaped(): void
    {
        $formatter = new LineFormatter();
        $record = LogRecord::create('info', "Hello\tWorld");

        $output = $formatter->format($record);
        self::assertStringNotContainsString("\t", $output);
        self::assertStringContainsString('\\t', $output);
    }

    public function testOtherControlCharsAreEscaped(): void
    {
        $formatter = new LineFormatter();
        $record = LogRecord::create('info', "Hello\x00\x01\x02");

        $output = $formatter->format($record);
        self::assertStringNotContainsString("\x00", $output);
        self::assertStringNotContainsString("\x01", $output);
        self::assertStringContainsString('\\x00', $output);
    }

    public function testCleanMessagePassesThroughUnchanged(): void
    {
        $formatter = new LineFormatter();
        $record = LogRecord::create('info', 'User 42 logged in');

        $output = $formatter->format($record);
        self::assertStringContainsString('User 42 logged in', $output);
        self::assertStringNotContainsString('\\n', $output);
    }
}

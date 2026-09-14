<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Logger\Formatter\LineFormatter;
use SovereignStack\Core\Logger\Formatter\RedactingFormatter;
use SovereignStack\Core\Logger\LogRecord;

/**
 * RedactingFormatter tests — verify sensitive keys are always redacted.
 *
 * @package SovereignStack\Core\Logger\Tests\Unit
 */
final class RedactingFormatterTest extends TestCase
{
    public function testAlwaysRedactKeysAreRedacted(): void
    {
        $inner = new LineFormatter();
        $formatter = new RedactingFormatter($inner);

        $record = LogRecord::create(
            'info',
            'User login',
            ['password' => 'hunter2', 'token' => 'Bearer abc123', 'username' => 'admin'],
        );

        $output = $formatter->format($record);
        self::assertStringNotContainsString('hunter2', $output);
        self::assertStringNotContainsString('Bearer abc123', $output);
        self::assertStringContainsString('admin', $output);
        self::assertStringContainsString('***REDACTED***', $output);
    }

    public function testAdditionalRedactKeysAreRedacted(): void
    {
        $inner = new LineFormatter();
        $formatter = new RedactingFormatter($inner, additionalRedactKeys: ['custom_secret', 'api_token']);

        $record = LogRecord::create(
            'info',
            'Config',
            ['custom_secret' => 'hidden', 'api_token' => 'xyz', 'visible' => 'ok'],
        );

        $output = $formatter->format($record);
        self::assertStringNotContainsString('hidden', $output);
        self::assertStringNotContainsString('xyz', $output);
        self::assertStringContainsString('ok', $output);
    }

    public function testAlwaysRedactCannotBeRemoved(): void
    {
        // Even with empty additionalRedactKeys, ALWAYS_REDACT keys are still redacted.
        $inner = new LineFormatter();
        $formatter = new RedactingFormatter($inner, additionalRedactKeys: []);

        $record = LogRecord::create('info', 'test', ['password' => 'secret']);

        $output = $formatter->format($record);
        self::assertStringNotContainsString('secret', $output);
        self::assertStringContainsString('***REDACTED***', $output);
    }

    public function testNestedArrayKeysAreRedacted(): void
    {
        $inner = new LineFormatter();
        $formatter = new RedactingFormatter($inner);

        $record = LogRecord::create(
            'info',
            'Nested',
            ['user' => ['name' => 'Alice', 'password' => 'hidden']],
        );

        $output = $formatter->format($record);
        self::assertStringNotContainsString('hidden', $output);
        self::assertStringContainsString('***REDACTED***', $output);
        self::assertStringContainsString('Alice', $output);
    }

    public function testCaseInsensitiveRedaction(): void
    {
        $inner = new LineFormatter();
        $formatter = new RedactingFormatter($inner);

        $record = LogRecord::create(
            'info',
            'Test',
            ['PASSWORD' => 'upper', 'Api_Key' => 'mixed', 'session-id' => 'sid123'],
        );

        $output = $formatter->format($record);
        self::assertStringNotContainsString('upper', $output);
        self::assertStringNotContainsString('mixed', $output);
        self::assertStringNotContainsString('sid123', $output);
    }

    public function testNonSensitiveValuesPassThrough(): void
    {
        $inner = new LineFormatter();
        $formatter = new RedactingFormatter($inner);

        $record = LogRecord::create(
            'info',
            'Test',
            ['user_id' => 42, 'action' => 'login', 'ip' => '10.0.0.1'],
        );

        $output = $formatter->format($record);
        self::assertStringContainsString('42', $output);
        self::assertStringContainsString('login', $output);
        self::assertStringContainsString('10.0.0.1', $output);
        self::assertStringNotContainsString('***REDACTED***', $output);
    }
}

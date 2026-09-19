<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SovereignStack\Core\Logger\LogRecord;

final class LogRecordTest extends TestCase
{
    public function testCreateProducesRecordWithCurrentTimestamp(): void
    {
        $before = new \DateTimeImmutable();
        $record = LogRecord::create(LogLevel::INFO, 'Test message');
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $record->timestamp);
        self::assertLessThanOrEqual($after, $record->timestamp);
    }

    public function testCreateCastsStringableMessageToString(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-message';
            }
        };

        $record = LogRecord::create(LogLevel::INFO, $stringable);
        self::assertSame('stringable-message', $record->message);
    }

    public function testConstructorRejectsInvalidLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid log level 'verbose'");

        new LogRecord(
            timestamp: new \DateTimeImmutable(),
            level: 'verbose',
            message: 'test',
        );
    }

    public function testIsAtLeastReturnsTrueForEqualSeverity(): void
    {
        $record = LogRecord::create(LogLevel::WARNING, 'test');
        self::assertTrue($record->isAtLeast(LogLevel::WARNING));
    }

    public function testIsAtLeastReturnsTrueForHigherSeverity(): void
    {
        $record = LogRecord::create(LogLevel::ERROR, 'test');
        self::assertTrue($record->isAtLeast(LogLevel::WARNING));
    }

    public function testIsAtLeastReturnsFalseForLowerSeverity(): void
    {
        $record = LogRecord::create(LogLevel::DEBUG, 'test');
        self::assertFalse($record->isAtLeast(LogLevel::WARNING));
    }

    public function testExceptionReturnsThrowableFromContext(): void
    {
        $e = new \RuntimeException('boom');
        $record = LogRecord::create(LogLevel::ERROR, 'failed', ['exception' => $e]);

        self::assertSame($e, $record->exception());
    }

    public function testExceptionReturnsNullWhenContextHasNoException(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'no exception here');
        self::assertNull($record->exception());
    }

    public function testExceptionReturnsNullWhenContextExceptionIsNotThrowable(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'fake exception', ['exception' => 'not-a-throwable']);
        self::assertNull($record->exception());
    }

    public function testWithExtraReturnsNewInstanceWithExtraAdded(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'test');
        $extended = $record->withExtra('pid', 12345);

        self::assertNotSame($record, $extended);
        self::assertSame([], $record->extra);
        self::assertSame(['pid' => 12345], $extended->extra);
    }

    public function testWithExtraPreservesExistingExtras(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'test', [], ['first' => 1]);
        $extended = $record->withExtra('second', 2);

        self::assertSame(['first' => 1], $record->extra);
        self::assertSame(['second' => 2, 'first' => 1], $extended->extra);
    }

    public function testWithExtraDoesNotMutateContext(): void
    {
        $record = LogRecord::create(LogLevel::INFO, 'test', ['ctx' => 'value']);
        $extended = $record->withExtra('pid', 123);

        self::assertSame(['ctx' => 'value'], $extended->context);
    }

    public function testLevelsBySeverityOrderedLowToHigh(): void
    {
        $levels = LogRecord::LEVELS_BY_SEVERITY;

        self::assertSame(LogLevel::DEBUG, $levels[0]);
        self::assertSame(LogLevel::EMERGENCY, $levels[7]);
        self::assertCount(8, $levels);
    }
}

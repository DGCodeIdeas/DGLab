<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SovereignStack\Core\Logger\Handler\StreamHandler;
use SovereignStack\Core\Logger\HandlerInterface;
use SovereignStack\Core\Logger\Logger;

final class LoggerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'dglab_logger_');
        @unlink($this->tempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testLogWritesToRegisteredHandler(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $logger = new Logger([$handler]);

        $logger->info('hello from logger');
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        self::assertStringContainsString('info: hello from logger', $contents);
    }

    public function testAllPsr3LevelsAreAccepted(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $logger = new Logger([$handler]);

        $logger->debug('debug msg');
        $logger->info('info msg');
        $logger->notice('notice msg');
        $logger->warning('warning msg');
        $logger->error('error msg');
        $logger->critical('critical msg');
        $logger->alert('alert msg');
        $logger->emergency('emergency msg');
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
            self::assertStringContainsString("{$level}: {$level} msg", $contents);
        }
    }

    public function testThresholdFiltersLowLevelRecords(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $logger = (new Logger([$handler]))->withThreshold(LogLevel::WARNING);

        $logger->info('should not write');
        $logger->warning('should write');
        $logger->error('should also write');
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        self::assertStringNotContainsString('should not write', $contents);
        self::assertStringContainsString('should write', $contents);
    }

    public function testThresholdReturnsCurrentLevel(): void
    {
        $logger = new Logger([], LogLevel::INFO);
        self::assertSame(LogLevel::INFO, $logger->threshold());

        $warmer = $logger->withThreshold(LogLevel::WARNING);
        self::assertSame(LogLevel::WARNING, $warmer->threshold());
        self::assertSame(LogLevel::INFO, $logger->threshold(), 'Original logger must not mutate.');
    }

    public function testWithHandlerReturnsNewInstance(): void
    {
        $logger = new Logger();
        $originalHandlers = $logger->handlers();

        $newLogger = $logger->withHandler(new StreamHandler($this->tempFile));

        self::assertNotSame($logger, $newLogger);
        self::assertSame($originalHandlers, $logger->handlers());
        self::assertCount(1, $newLogger->handlers());
    }

    public function testWithHandlerAppendsPreservingOrder(): void
    {
        $h1 = new StreamHandler($this->tempFile);
        $h2 = new StreamHandler($this->tempFile);

        $logger = (new Logger())->withHandler($h1)->withHandler($h2);
        $handlers = $logger->handlers();

        self::assertSame($h1, $handlers[0]);
        self::assertSame($h2, $handlers[1]);
    }

    public function testConstructorRejectsInvalidThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid threshold 'verbose'");

        new Logger([], 'verbose');
    }

    public function testLogRejectsInvalidLevel(): void
    {
        $logger = new Logger();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level');

        /** @phpstan-ignore arguments.type */
        $logger->log('verbose', 'should throw');
    }

    public function testHandlerExceptionIsSwallowed(): void
    {
        $failingHandler = new class implements HandlerInterface {
            public int $callCount = 0;
            public function isHandling(\SovereignStack\Core\Logger\LogRecord $record): bool
            {
                return true;
            }
            public function handle(\SovereignStack\Core\Logger\LogRecord $record): bool
            {
                $this->callCount++;
                throw new \RuntimeException('handler exploded');
            }
            public function handleBatch(array $records): void {}
            public function close(): void {}
        };

        $logger = new Logger([$failingHandler]);

        // Must not throw.
        $logger->error('this should not crash');

        self::assertSame(1, $failingHandler->callCount);
    }

    public function testContextInterpolationInMessage(): void
    {
        $handler = new StreamHandler($this->tempFile);
        $logger = new Logger([$handler]);

        $logger->info('User {id} performed {action}', ['id' => 42, 'action' => 'login']);
        $handler->close();

        $contents = file_get_contents($this->tempFile);
        self::assertStringContainsString('User 42 performed login', $contents);
    }

    public function testMultipleHandlersAllReceiveRecord(): void
    {
        $file1 = $this->tempFile;
        $file2 = $this->tempFile . '.second';

        try {
            $h1 = new StreamHandler($file1);
            $h2 = new StreamHandler($file2);
            $logger = new Logger([$h1, $h2]);

            $logger->info('broadcast');
            $h1->close();
            $h2->close();

            self::assertStringContainsString('broadcast', file_get_contents($file1));
            self::assertStringContainsString('broadcast', file_get_contents($file2));
        } finally {
            if (file_exists($file2)) {
                unlink($file2);
            }
        }
    }
}

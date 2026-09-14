<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger;

use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * Default Logger implementation — immutable, worker-scoped per ADR-017.
 *
 * Holds a list of handlers and a minimum threshold. Each log call:
 *   1. Constructs a LogRecord from the PSR-3 arguments.
 *   2. Filters by threshold (early return if below).
 *   3. Iterates handlers, calling isHandling() + handle() on each.
 *
 * Immutability: {@see withHandler()} and {@see withThreshold()} return
 * new instances. The original Logger is never mutated after construction.
 *
 * Frozen per SDLC-AGRD §2.1 via {@see LoggerInterface}.
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @param array<HandlerInterface> $handlers
     * @param string $threshold PSR-3 LogLevel constant.
     */
    public function __construct(
        private readonly array $handlers = [],
        private readonly string $threshold = LogLevel::DEBUG,
    ) {
        if (!in_array($threshold, LogRecord::LEVELS_BY_SEVERITY, true)) {
            throw new \InvalidArgumentException(
                "Invalid threshold '{$threshold}'. Must be one of: " . implode(', ', LogRecord::LEVELS_BY_SEVERITY),
            );
        }
    }

    public function withHandler(HandlerInterface $handler): static
    {
        return new self(
            handlers: [...$this->handlers, $handler],
            threshold: $this->threshold,
        );
    }

    public function withThreshold(string $threshold): static
    {
        return new self(
            handlers: $this->handlers,
            threshold: $threshold,
        );
    }

    public function threshold(): string
    {
        return $this->threshold;
    }

    public function handlers(): array
    {
        return $this->handlers;
    }

    /**
     * PSR-3 log entry point.
     *
     * @param mixed $level
     * @param string|Stringable $message
     * @param array<mixed, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!is_string($level) || !in_array($level, LogRecord::LEVELS_BY_SEVERITY, true)) {
            throw new \InvalidArgumentException(
                "Invalid log level " . get_debug_type($level) . ". Must be one of: "
                . implode(', ', LogRecord::LEVELS_BY_SEVERITY),
            );
        }

        $record = LogRecord::create($level, $message, $context);

        // Early threshold filter — avoids handler iteration for filtered records.
        if (!$record->isAtLeast($this->threshold)) {
            return;
        }

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record)) {
                try {
                    $result = $handler->handle($record);
                    // Honor the handler's propagation signal: if handle()
                    // returns false, stop processing downstream handlers.
                    // This is the HandlerStack propagation contract —
                    // a handler that returns false "swallows" the record.
                    if ($result === false) {
                        break;
                    }
                } catch (Throwable $e) {
                    // Logging must never crash the application. Swallow handler
                    // failures silently. Production deployments should monitor
                    // for missing log output.
                    // Intentionally no re-throw, no recursion.
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger;

use DateTimeImmutable;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * Immutable log record — the canonical shape that flows through the
 * HandlerStack and is rendered by Formatters.
 *
 * Per RFC 5424, level is one of: debug, info, notice, warning, error,
 * critical, alert, emergency. We use the PSR-3 LogLevel class constants
 * as the canonical strings rather than introducing a separate enum —
 * keeps us PSR-3-aligned and avoids a mapping layer.
 *
 * Worker-scoped per ADR-017: instances are short-lived (one per log call)
 * and never shared across Fibers. Immutability guarantees safe handoff
 * between the calling code and any deferred handler invocation.
 */
final class LogRecord
{
    /**
     * RFC 5424 severity levels, lowest to highest.
     *
     * Used by {@see HandlerInterface::isHandling()} to filter records
     * below the handler's threshold. Order matters — higher index = higher severity.
     */
    public const LEVELS_BY_SEVERITY = [
        LogLevel::DEBUG,
        LogLevel::INFO,
        LogLevel::NOTICE,
        LogLevel::WARNING,
        LogLevel::ERROR,
        LogLevel::CRITICAL,
        LogLevel::ALERT,
        LogLevel::EMERGENCY,
    ];

    /**
     * @param DateTimeImmutable $timestamp When the record was created.
     * @param string $level PSR-3 LogLevel constant.
     * @param string $message Message template, possibly with {placeholder} tokens.
     * @param array<mixed, mixed> $context PSR-3 context, including optional 'exception' => Throwable.
     * @param array<string, mixed> $extra Handler-populated metadata (e.g. file, line, pid).
     */
    public function __construct(
        public readonly DateTimeImmutable $timestamp,
        public readonly string $level,
        public readonly string $message,
        public readonly array $context = [],
        public readonly array $extra = [],
    ) {
        if (!in_array($level, self::LEVELS_BY_SEVERITY, true)) {
            throw new \InvalidArgumentException(
                "Invalid log level '{$level}'. Must be one of: " . implode(', ', self::LEVELS_BY_SEVERITY),
            );
        }
    }

    /**
     * Factory for the common case: timestamp = now, no extras.
     *
     * @param string $level PSR-3 LogLevel constant.
     * @param string|Stringable $message Message template.
     * @param array<string, mixed> $context PSR-3 context.
     * @param array<string, mixed> $extra Handler-populated metadata.
     */
    public static function create(
        string $level,
        string|Stringable $message,
        array $context = [],
        array $extra = [],
    ): self {
        return new self(
            timestamp: new DateTimeImmutable(),
            level: $level,
            message: (string) $message,
            context: $context,
            extra: $extra,
        );
    }

    /**
     * Whether this record's level is at-or-above $threshold.
     *
     * @param string $threshold PSR-3 LogLevel constant.
     */
    public function isAtLeast(string $threshold): bool
    {
        $recordSeverity = array_search($this->level, self::LEVELS_BY_SEVERITY, true);
        $thresholdSeverity = array_search($threshold, self::LEVELS_BY_SEVERITY, true);

        if ($recordSeverity === false || $thresholdSeverity === false) {
            return false; // Defensive — LogRecord constructor already validates level.
        }

        return $recordSeverity >= $thresholdSeverity;
    }

    /**
     * The exception associated with this record, if any.
     *
     * PSR-3 convention: context['exception'] should be a Throwable when present.
     */
    public function exception(): ?Throwable
    {
        $candidate = $this->context['exception'] ?? null;
        return $candidate instanceof Throwable ? $candidate : null;
    }

    /**
     * Return a new record with $key=$value added to the extra bag.
     *
     * Handlers use this to attach metadata (file, line, pid, etc.) without
     * mutating the caller's context.
     *
     * @param string $key
     * @param mixed $value
     */
    public function withExtra(string $key, mixed $value): self
    {
        return new self(
            $this->timestamp,
            $this->level,
            $this->message,
            $this->context,
            [$key => $value] + $this->extra,
        );
    }

    /**
     * Return a new LogRecord with a replaced context array.
     *
     * Used by {@see RedactingFormatter} to strip sensitive keys before
     * the record reaches the inner formatter.
     *
     * @param array<mixed, mixed> $context
     */
    public function withContext(array $context): self
    {
        return new self(
            $this->timestamp,
            $this->level,
            $this->message,
            $context,
            $this->extra,
        );
    }
}

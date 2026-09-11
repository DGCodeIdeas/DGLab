<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Formatter;

use SovereignStack\Core\Logger\FormatterInterface;
use SovereignStack\Core\Logger\LogRecord;
use Throwable;

/**
 * Default text formatter — single-line, human-readable.
 *
 * Format: "[%datetime%] %level%: %message% %context% %extra%"
 *
 * The %context% and %extra% segments are omitted when empty.
 * The 'exception' key in context is rendered specially as a multi-line
 * stack trace appended after the main line.
 *
 * PSR-3 placeholder interpolation is applied to the message before formatting.
 * Example: "User {id} logged in" with context ['id' => 42] becomes
 * "User 42 logged in".
 */
final class LineFormatter implements FormatterInterface
{
    /**
     * @param string $dateFormat DateTime format for the timestamp. Default: ISO 8601 with microseconds.
     */
    public function __construct(
        private readonly string $dateFormat = 'Y-m-d\TH:i:s.uP',
    ) {
    }

    public function format(LogRecord $record): string
    {
        $message = $this->interpolate($record->message, $record->context);
        $timestamp = $record->timestamp->format($this->dateFormat);

        $line = "[{$timestamp}] {$record->level}: {$message}";

        // Render context (minus 'exception', which is handled separately).
        $context = $record->context;
        if (isset($context['exception'])) {
            unset($context['exception']);
        }
        if ($context !== []) {
            $line .= ' ' . $this->renderContext($context);
        }

        if ($record->extra !== []) {
            $line .= ' ' . $this->renderContext($record->extra);
        }

        // Append exception stack trace if present.
        $exception = $record->exception();
        if ($exception !== null) {
            $line .= "\n" . $this->formatException($exception);
        }

        return $line;
    }

    public function formatBatch(array $records): string
    {
        $lines = [];
        foreach ($records as $record) {
            $lines[] = $this->format($record);
        }
        return implode("\n", $lines);
    }

    /**
     * PSR-3 §1.3 placeholder interpolation.
     *
     * Replaces {key} tokens in $message with the string-cast value of
     * $context[$key]. Tokens with no matching context key are left as-is.
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if ($context === []) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                // Exceptions are rendered separately — don't toString them inline.
                continue;
            }
            $replace['{' . $key . '}'] = match (true) {
                is_scalar($value) || $value === null => (string) $value,
                default => json_encode($value, JSON_THROW_ON_ERROR) ?: '',
            };
        }

        return strtr($message, $replace);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderContext(array $data): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            $rendered = match (true) {
                is_scalar($value) || $value === null => var_export($value, true),
                default => json_encode($value, JSON_THROW_ON_ERROR) ?: 'null',
            };
            $parts[] = "{$key}={$rendered}";
        }
        return '{' . implode(' ', $parts) . '}';
    }

    private function formatException(Throwable $e): string
    {
        $trace = $e->getTraceAsString();
        return "  Exception: " . $e::class . " '{$e->getMessage()}' at {$e->getFile()}:{$e->getLine()}\n{$trace}";
    }
}

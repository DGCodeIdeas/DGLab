<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Formatter;

use SovereignStack\Core\Logger\FormatterInterface;
use SovereignStack\Core\Logger\LogRecord;
use Throwable;

/**
 * JSON Lines formatter — one JSON object per record, no enclosing array.
 *
 * Suitable for ingestion into observability pipelines (Loki, Elasticsearch,
 * Datadog ingest). Each record is a self-contained JSON object on its own line.
 *
 * Context and extra are merged into the top-level object (NOT nested under
 * "context" / "extra" keys) for flatter query ergonomics. Collision behaviour:
 * if a context key collides with a reserved field (timestamp, level, message),
 * the context key is suffixed with "_context".
 *
 * The optional 'exception' key in context is rendered as a structured object
 * with class, message, file, line, and trace fields.
 */
final class JsonFormatter implements FormatterInterface
{
    /**
     * @param bool $prettyPrint Whether to pretty-print each JSON object. Default false (one line per record).
     */
    public function __construct(
        private readonly bool $prettyPrint = false,
    ) {
    }

    public function format(LogRecord $record): string
    {
        $payload = [
            'timestamp' => $record->timestamp->format(DateTimeInterface::ATOM),
            'level' => $record->level,
            'message' => $this->interpolate($record->message, $record->context),
        ];

        // Render context (excluding exception, which is structured separately).
        foreach ($record->context as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($key === 'exception') {
                continue;
            }
            $targetKey = array_key_exists($key, $payload) ? "{$key}_context" : $key;
            $payload[$targetKey] = $value;
        }

        foreach ($record->extra as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $targetKey = array_key_exists($key, $payload) ? "{$key}_extra" : $key;
            $payload[$targetKey] = $value;
        }

        $exception = $record->exception();
        if ($exception !== null) {
            $payload['exception'] = $this->formatException($exception);
        }

        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($payload, $flags) ?: '{}';
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
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if ($context === []) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            if (!is_string($key) || $value instanceof Throwable) {
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
     * @return array<string, string|int>
     */
    private function formatException(Throwable $e): array
    {
        return [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger\Formatter;

use SovereignStack\Core\Logger\FormatterInterface;
use SovereignStack\Core\Logger\LogRecord;

/**
 * Decorator formatter that redacts sensitive keys from context and extra.
 *
 * Wraps another formatter (LineFormatter, JsonFormatter, etc.) and strips
 * values whose keys match the ALWAYS_REDACT pattern before delegating to
 * the inner formatter. This is the primary security invariant of CORE-09:
 * no password, token, secret, or authorization header should ever appear
 * in a log file.
 *
 * The ALWAYS_REDACT pattern cannot be removed — callers can only ADD keys
 * via the constructor's $additionalRedactKeys. This is a hard guarantee,
 * not a configuration option.
 *
 * @package SovereignStack\Core\Logger\Formatter
 */
final class RedactingFormatter implements FormatterInterface
{
    /** Keys that are ALWAYS redacted — cannot be opted out of. */
    public const ALWAYS_REDACT = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'auth',
        'api_key',
        'apikey',
        'api-key',
        'private_key',
        'privatekey',
        'access_token',
        'refresh_token',
        'session_id',
        'sessionid',
        'cookie',
        'set-cookie',
    ];

    /** Regex pattern for case-insensitive key matching. */
    private const SECRET_PATTERN = '/password|passwd|secret|token|authorization|auth|api_key|apikey|api-key|private_key|privatekey|access_token|refresh_token|session_id|sessionid|cookie|set-cookie/i';

    /**
     * @param FormatterInterface $innerFormatter The wrapped formatter.
     * @param array<string> $additionalRedactKeys Extra keys to redact (in addition to ALWAYS_REDACT).
     */
    public function __construct(
        private readonly FormatterInterface $innerFormatter,
        private readonly array $additionalRedactKeys = [],
    ) {
    }

    public function format(LogRecord $record): string
    {
        $redacted = $this->redactRecord($record);
        return $this->innerFormatter->format($redacted);
    }

    public function formatBatch(array $records): string
    {
        $redacted = [];
        foreach ($records as $record) {
            $redacted[] = $this->redactRecord($record);
        }
        return $this->innerFormatter->formatBatch($redacted);
    }

    private function redactRecord(LogRecord $record): LogRecord
    {
        $redactedContext = $this->redactArray($record->context);

        if ($redactedContext === $record->context) {
            return $record;
        }

        return $record->withContext($redactedContext);
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function redactArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($this->shouldRedact((string) $key)) {
                $result[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $result[$key] = $this->redactArray($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function shouldRedact(string $key): bool
    {
        if (preg_match(self::SECRET_PATTERN, $key) === 1) {
            return true;
        }
        return in_array(strtolower($key), array_map('strtolower', $this->additionalRedactKeys), true);
    }
}

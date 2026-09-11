<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger;

/**
 * Formats a LogRecord into a single-line string for emission.
 *
 * Implementations MUST be stateless — a formatter shared across handlers
 * must produce identical output for identical input. Formatters MUST NOT
 * perform I/O (no file writes, no network calls); they are pure transformers.
 *
 * @package SovereignStack\Core\Logger
 */
interface FormatterInterface
{
    /**
     * Format a single record into a string (without trailing newline).
     *
     * Implementations should resolve {placeholder} tokens against the
     * record's context per PSR-3 §1.3.
     */
    public function format(LogRecord $record): string;

    /**
     * Format a batch of records into a single string.
     *
     * Default behaviour is to format each record and join with newlines.
     * Override for custom batch formatting (e.g. JSON array).
     *
     * @param array<LogRecord> $records
     */
    public function formatBatch(array $records): string;
}

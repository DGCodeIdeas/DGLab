<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger;

/**
 * Receives a LogRecord and emits it to a destination (file, stream, syslog, network).
 *
 * Handlers decide:
 *   - Whether to handle a record (level threshold, channel filter, etc.)
 *   - How to format it (via a injected FormatterInterface)
 *   - Where to send it (file, network, etc.)
 *
 * Handlers MUST be safe to call from multiple Fibers concurrently per ADR-017.
 * For file-based handlers, this means using flock() around writes.
 *
 * @package SovereignStack\Core\Logger
 */
interface HandlerInterface
{
    /**
     * Whether this handler will process the given record.
     *
     * Called by HandlerStack before {@see handle()}. Returning false
     * allows the stack to skip the formatting + I/O cost entirely.
     */
    public function isHandling(LogRecord $record): bool;

    /**
     * Process the record.
     *
     * Implementations should format via {@see FormatterInterface} and
     * emit to the destination. Returns true if the record was handled
     * (used by some handlers to signal "stop propagation" — though
     * HandlerStack's default does not stop on true).
     */
    public function handle(LogRecord $record): bool;

    /**
     * Process a batch of records.
     *
     * Default behaviour is to iterate and call handle() per record.
     * Override for optimised batch writes (e.g. single fwrite of joined lines).
     *
     * @param array<LogRecord> $records
     */
    public function handleBatch(array $records): void;

    /**
     * Release any resources held by the handler (file handles, sockets).
     *
     * Called when the worker is shutting down. Idempotent — calling
     * close() on an already-closed handler is a no-op.
     */
    public function close(): void;
}

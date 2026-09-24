<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

/**
 * A lifecycle audit record for the Kernel. Per doctrine §4.5.6, the
 * Kernel creates these at 8 lifecycle points and stores them in
 * $lifecycleRecords. Where possible, the record is also attached to
 * the corresponding event (BootEvent, RequestReceivedEvent, etc.) for
 * HUB-06 (Audit) to read.
 *
 * The 8 record types:
 *   - bootStarted:     at Unbooted→Booting transition
 *   - bootCompleted:   at Booting→Booted transition
 *   - bootFailed:      in boot()'s catch block
 *   - handleStarted:    at Booted→Handling transition
 *   - handleCompleted:  in handle()'s finally
 *   - handleFailed:     in any future catch in handle()
 *   - terminateStarted:  at Booted→Terminating transition
 *   - terminateCompleted: at Terminating→Terminated transition
 *
 * These records feed the §8 AuditRecord schema (hash-chained). HUB-06
 * transforms KernelLifecycleRecords into AuditRecords when it ships.
 *
 * @package SovereignStack\Core\Kernel
 */
final class KernelLifecycleRecord
{
    /**
     * @param string      $event             One of the 8 lifecycle event types.
     * @param float       $timestamp         Microtime of the record creation.
     * @param string      $state             The Kernel state at the time.
     * @param float|null  $elapsedMs         Elapsed milliseconds (for completed/failed events).
     * @param int|null    $bootstrapperCount  Number of bootstrappers (for boot events).
     * @param string|null $requestMethod      HTTP method (for handle events).
     * @param string|null $requestUriHash      SHA-256 hash of the request URI (for handle events).
     * @param int|null    $responseStatusCode  HTTP status code (for handleCompleted).
     * @param string|null $throwableClass      FQCN of the thrown exception (for failed events).
     * @param string|null $throwableMessage    Exception message (for failed events).
     */
    public function __construct(
        public readonly string $event,
        public readonly float $timestamp,
        public readonly string $state,
        public readonly ?float $elapsedMs = null,
        public readonly ?int $bootstrapperCount = null,
        public readonly ?string $requestMethod = null,
        public readonly ?string $requestUriHash = null,
        public readonly ?int $responseStatusCode = null,
        public readonly ?string $throwableClass = null,
        public readonly ?string $throwableMessage = null,
    ) {
    }

    /**
     * @return array<string, mixed> Array representation for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'timestamp' => $this->timestamp,
            'state' => $this->state,
            'elapsed_ms' => $this->elapsedMs,
            'bootstrapper_count' => $this->bootstrapperCount,
            'request_method' => $this->requestMethod,
            'request_uri_hash' => $this->requestUriHash,
            'response_status_code' => $this->responseStatusCode,
            'throwable_class' => $this->throwableClass,
            'throwable_message' => $this->throwableMessage,
        ];
    }
}

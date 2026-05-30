# HUB-15.md

## Phase ID

`HUB-15`

## Tier

`Hub`

## Component Name and Description

**Audit Logging Service** – Provides immutable, tamper‑evident audit trails for critical operations. Implements a PSR‑3 logger with structured JSON output and writes to an append‑only store (e.g., Elasticsearch or write‑once file). Emits PSR‑14 events for downstream compliance processors.

## Context7 Research

- **PHP Best Practices**: Use immutable log records, avoid logging sensitive data, ensure write‑once semantics.
- **PSR‑3**: Logger interface for audit logs.
- **PSR‑14**: Dispatches `AuditEvent` after each critical action.
- **Design Patterns**: Decorator for enrichment, Strategy for storage back‑ends, Builder for log record creation.
- **Performance**: Asynchronous write via queue to keep request latency < 2 ms.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Audit;

use Psr\Log\LoggerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

interface AuditLoggerInterface extends LoggerInterface {}

final class JsonAuditLogger implements AuditLoggerInterface
{
    private LoggerInterface $inner;
    private EventDispatcherInterface $dispatcher;

    public function __construct(LoggerInterface $inner, EventDispatcherInterface $dispatcher)
    {
        $this->inner = $inner;
        $this->dispatcher = $dispatcher;
    }

    public function log($level, $message, array $context = []): void
    {
        $record = [
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
        $this->inner->log($level, json_encode($record), []);
        $this->dispatcher->dispatch(new AuditEvent($record));
    }

    // PSR‑3 shortcut methods delegate to log()
    public function emergency($message, array $context = []) { $this->log('emergency', $message, $context); }
    public function alert($message, array $context = [])     { $this->log('alert', $message, $context); }
    public function critical($message, array $context = [])  { $this->log('critical', $message, $context); }
    public function error($message, array $context = [])     { $this->log('error', $message, $context); }
    public function warning($message, array $context = [])   { $this->log('warning', $message, $context); }
    public function notice($message, array $context = [])    { $this->log('notice', $message, $context); }
    public function info($message, array $context = [])      { $this->log('info', $message, $context); }
    public function debug($message, array $context = [])     { $this->log('debug', $message, $context); }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component JsonAuditLogger {
        +log(level, message, context)
    }
    component AuditEvent <<interface>>
    JsonAuditLogger --> AuditEvent
```

## Integration Strategy

Registered as a singleton in the Core DI container (`CORE-02`). Critical services (e.g., `UserService`, `AuthGateway`) inject `AuditLoggerInterface` instead of a generic logger. The dispatcher forwards `AuditEvent` to compliance processors defined in `CORE-07`.

## CI Verification Criteria

- Unit test coverage ≥ 95% for logger delegation and event dispatch.
- Integration test verifies that a `UserCreatedEvent` results in an audit log entry.
- Latency overhead ≤ 2 ms per audited operation.
- Retention: audit records are immutable and searchable for at least 7 years.

## SemVer Impact

**Minor** – Introduces a new audit logging API and events, affecting services that need compliance.

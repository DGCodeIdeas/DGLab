# HUB-07.md

## Phase ID

`HUB-07`

## Tier

`Hub`

## Component Name and Description

**Logging Service** – Centralized, PSR‑3 compliant logger that aggregates structured logs from Core and Hub components. Supports multiple handlers (file, syslog, external services) and contextual data enrichment.

## Context7 Research

- **PHP Best Practices**: Use monolog, immutable log records, avoid logging sensitive data.
- **PSR‑3**: Logger interface standard.
- **Design Patterns**: Strategy for handler selection, Decorator for log enrichment, Singleton for global logger instance.
- **Performance**: Asynchronous log writing via queue to keep request latency < 2 ms.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Log;

use Psr\Log\LoggerInterface;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

final class HubLogger implements LoggerInterface
{
    private MonologLogger $logger;

    public function __construct(array $handlers = [])
    {
        $this->logger = new MonologLogger('hub');
        foreach ($handlers as $handler) {
            $this->logger->pushHandler($handler);
        }
    }

    // PSR‑3 methods delegated to $this->logger
    public function emergency($message, array $context = []) { $this->logger->emergency($message, $context); }
    public function alert($message, array $context = [])     { $this->logger->alert($message, $context); }
    public function critical($message, array $context = [])  { $this->logger->critical($message, $context); }
    public function error($message, array $context = [])     { $this->logger->error($message, $context); }
    public function warning($message, array $context = [])   { $this->logger->warning($message, $context); }
    public function notice($message, array $context = [])    { $this->logger->notice($message, $context); }
    public function info($message, array $context = [])      { $this->logger->info($message, $context); }
    public function debug($message, array $context = [])     { $this->logger->debug($message, $context); }
    public function log($level, $message, array $context = []) { $this->logger->log($level, $message, $context); }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component HubLogger {
        +log(level, message, context)
        +info(message, context)
        +error(message, context)
    }
    component Handler <<interface>>
    HubLogger --> Handler
```

## Integration Strategy

Registered as a singleton in the Core DI container (`CORE-02`). All services type‑hint `Psr\Log\LoggerInterface` and receive this implementation. Handlers are configured in `CORE-01` configuration files.

## CI Verification Criteria

- Unit test coverage ≥ 95% for logger delegation.
- Integration test verifies that logs are written to configured handlers.
- Latency: logging a message adds ≤ 2 ms to request time.
- Security: no sensitive fields (e.g., passwords) are logged; verified by static analysis.

## SemVer Impact

**Minor** – Introduces a new logging infrastructure affecting all components.

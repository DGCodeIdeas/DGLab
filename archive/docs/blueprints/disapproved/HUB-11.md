# HUB-11.md

## Phase ID

`HUB-11`

## Tier

`Hub`

## Component Name and Description

**Metrics & Telemetry Service** – Collects application performance metrics, request traces, and custom business KPIs. Exposes a PSR‑7 endpoint for Prometheus scraping and integrates with OpenTelemetry for distributed tracing.

## Context7 Research

- **PHP Best Practices**: Use lazy collectors, avoid blocking I/O, respect privacy.
- **PSR‑7**: Endpoint receives HTTP GET for `/metrics`.
- **PSR‑11**: Service container registration of `MetricsCollectorInterface`.
- **Design Patterns**: Observer for metric emission, Strategy for exporter back‑ends (Prometheus, StatsD), Singleton for global collector.
- **Performance**: Metric collection overhead < 1 ms per request.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\Metrics;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\Http\Message\ServerRequestInterface; // PSR‑7
use Psr\Http\Message\ResponseInterface;

interface MetricsCollectorInterface
{
    public function increment(string $name, int $value = 1, array $labels = []): void;
    public function gauge(string $name, float $value, array $labels = []): void;
    public function histogram(string $name, float $value, array $labels = []): void;
    public function export(): string; // Prometheus exposition format
}

final class PrometheusCollector implements MetricsCollectorInterface
{
    private array $metrics = [];
    public function increment(string $name, int $value = 1, array $labels = []): void { $this->metrics[$name]['counter'] = ($this->metrics[$name]['counter'] ?? 0) + $value; }
    public function gauge(string $name, float $value, array $labels = []): void { $this->metrics[$name]['gauge'] = $value; }
    public function histogram(string $name, float $value, array $labels = []): void { $this->metrics[$name]['histogram'][] = $value; }
    public function export(): string { /* render Prometheus text format */ return "# Metrics export\n"; }
}

final class MetricsMiddleware
{
    private MetricsCollectorInterface $collector;
    public function __construct(MetricsCollectorInterface $collector) { $this->collector = $collector; }
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;
        $this->collector->histogram('request_duration_seconds', $duration, ['path' => $request->getUri()->getPath()]);
        $this->collector->increment('request_total', 1, ['path' => $request->getUri()->getPath(), 'status' => $response->getStatusCode()]);
        return $response;
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component MetricsMiddleware {
        +__invoke(ServerRequestInterface, callable): ResponseInterface
    }
    component MetricsCollector <<interface>>
    component PrometheusCollector <<interface>>
    MetricsMiddleware --> MetricsCollector
    MetricsCollector --> PrometheusCollector
```

## Integration Strategy

Registered as a singleton in the Core DI container (`CORE-02`). Middleware is attached to the router (`CORE-03`) to record request metrics. The `/metrics` controller resolves `MetricsCollectorInterface` to output the Prometheus format.

## CI Verification Criteria

- Unit test coverage ≥ 95% for collector methods.
- Integration test verifies `/metrics` endpoint returns valid Prometheus text.
- Performance overhead ≤ 1 ms per request.
- Reliability: metrics remain accurate under load of 5 k rps.

## SemVer Impact

**Minor** – Adds observability APIs and middleware, affecting request handling.

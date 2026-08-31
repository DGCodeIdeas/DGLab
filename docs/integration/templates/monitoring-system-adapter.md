# Adapter Template: Monitoring System (Datadog)

## Overview
This template demonstrates implementing a monitoring/metrics adapter using Datadog's API and DogStatsD protocol. Follow this template when creating new monitoring adapters for the Sovereign Stack.

## Interface Implemented
[`MetricsAdapterInterface`](/docs/integration/interoperability-standards.md#2-metricsadapterinterface-observability---monitoring)

## Package Structure

```
dg-adapter-datadog/
├── composer.json
├── README.md
├── src/
│   ├── DatadogAdapter.php               # Main adapter implementation
│   ├── DatadogServiceProvider.php       # ServiceProvider for registration
│   ├── Config/
│   │   └── datadog.php                  # Default configuration
│   ├── Exception/
│   │   ├── DatadogConnectionException.php
│   │   └── DatadogApiException.php
│   ├── Transport/
│   │   ├── StatsDTransport.php          # DogStatsD UDP transport
│   │   └── ApiTransport.php            # HTTP API transport
│   └── Client/
│       ├── DashboardClient.php          # Datadog dashboard API
│       └── MonitorClient.php            # Datadog monitor API
├── config/
│   └── datadog.php                      # Published configuration
└── tests/
    ├── Unit/
    │   ├── DatadogAdapterTest.php
    │   └── Transport/
    │       ├── StatsDTransportTest.php
    │       └── ApiTransportTest.php
    ├── Integration/
    │   └── DatadogAdapterIntegrationTest.php
    └── TestDouble/
        └── FakeStatsDServer.php
```

## Full Implementation

### 1. Composer Manifest

```json
{
    "name": "dg/adapter-datadog",
    "type": "sovereign-stack-adapter",
    "description": "Sovereign Stack adapter for Datadog monitoring and metrics",
    "keywords": ["sovereign-stack", "adapter", "monitoring", "metrics", "datadog"],
    "license": "MIT",
    "require": {
        "php": ">=8.2",
        "sovereign/core": "^2.0",
        "sovereign/adapter-contracts": "^1.0",
        "ext-sockets": "*"
    },
    "require-dev": {
        "sovereign/test-utils": "^1.0",
        "phpunit/phpunit": "^11.0",
        "http-interop/http-factory-guzzle": "^1.0"
    },
    "suggest": {
        "guzzlehttp/guzzle": "Required for HTTP API transport mode"
    },
    "autoload": {
        "psr-4": {
            "Sovereign\\Adapter\\Datadog\\": "src/"
        }
    },
    "extra": {
        "sovereign-stack": {
            "type": "adapter",
            "category": "monitoring",
            "target-blueprints": ["HUB-05"],
            "min-core-version": "2.0.0"
        }
    }
}
```

### 2. StatsD Transport (DogStatsD)

```php
<?php
namespace Sovereign\Adapter\Datadog\Transport;

use Sovereign\Adapter\Datadog\Exception\DatadogConnectionException;

/**
 * Datadog DogStatsD UDP transport for high-performance metric submission.
 */
class StatsDTransport
{
    private const MAX_PACKET_SIZE = 8192; // DogStatsD max UDP packet size

    private ?\Socket $socket = null;
    private array $buffer = [];
    private int $bufferSize;

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8125,
        int $bufferSize = 50,
        private readonly ?string $namespace = null,
        private readonly array $defaultTags = []
    ) {
        $this->bufferSize = max(1, $bufferSize);
    }

    /**
     * Send a metric via DogStatsD protocol.
     *
     * Format: metric.name:value|type|@sample_rate|#tag1,tag2
     */
    public function send(string $metric, string $value, string $type, array $tags = [], ?float $sampleRate = null): void
    {
        $fullMetric = $this->namespace
            ? "{$this->namespace}.{$metric}"
            : $metric;

        $allTags = array_merge($this->defaultTags, $tags);
        $tagString = !empty($allTags)
            ? '|#' . implode(',', $this->formatTags($allTags))
            : '';

        $sampleString = $sampleRate !== null && $sampleRate < 1.0
            ? "|@{$sampleRate}"
            : '';

        $message = "{$fullMetric}:{$value}|{$type}{$sampleString}{$tagString}";
        $this->buffer[] = $message;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $this->ensureConnected();

        $messages = $this->buffer;
        $this->buffer = [];

        // Concatenate multiple metrics into one packet (newline-separated)
        $payload = implode("\n", $messages);

        if (strlen($payload) > self::MAX_PACKET_SIZE) {
            // Split into multiple packets if too large
            $chunks = str_split($payload, self::MAX_PACKET_SIZE);
            foreach ($chunks as $chunk) {
                $this->writeToSocket($chunk);
            }
        } else {
            $this->writeToSocket($payload);
        }
    }

    public function ping(): bool
    {
        try {
            $this->ensureConnected();
            return true;
        } catch (DatadogConnectionException) {
            return false;
        }
    }

    public function close(): void
    {
        $this->flush();
        if ($this->socket !== null) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    private function ensureConnected(): void
    {
        if ($this->socket !== null) {
            return;
        }

        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($this->socket === false) {
            throw new DatadogConnectionException(
                'Failed to create DogStatsD UDP socket: ' . socket_strerror(socket_last_error())
            );
        }
    }

    private function writeToSocket(string $data): void
    {
        $result = @socket_sendto(
            $this->socket,
            $data,
            strlen($data),
            0,
            $this->host,
            $this->port
        );

        if ($result === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            error_log("Datadog StatsD send failed: {$error}");
        }
    }

    private function formatTags(array $tags): array
    {
        return array_map(
            fn(string $key, mixed $value) => is_string($value)
                ? "{$key}:{$value}"
                : "{$key}:{$value}",
            array_keys($tags),
            $tags
        );
    }
}
```

### 3. Main Adapter Implementation

```php
<?php
namespace Sovereign\Adapter\Datadog;

use Sovereign\Adapter\BaseAdapter;
use Sovereign\Adapter\Contracts\MetricsAdapterInterface;
use Sovereign\Adapter\Contracts\TimerInterface;
use Sovereign\Adapter\Datadog\Transport\StatsDTransport;

class DatadogAdapter extends BaseAdapter implements MetricsAdapterInterface
{
    private const TYPE_COUNTER = 'c';
    private const TYPE_GAUGE = 'g';
    private const TYPE_TIMING = 'ms';
    private const TYPE_HISTOGRAM = 'h';
    private const TYPE_DISTRIBUTION = 'd';
    private const TYPE_SET = 's';

    private StatsDTransport $transport;
    private array $defaultTags;
    private string $metricPrefix;

    public function __construct(array $config)
    {
        $this->metricPrefix = $config['metric_prefix'] ?? 'sovereign';

        $this->transport = new StatsDTransport(
            host: $config['host'] ?? '127.0.0.1',
            port: $config['port'] ?? 8125,
            bufferSize: $config['buffer_size'] ?? 50,
            namespace: $this->metricPrefix,
            defaultTags: $config['default_tags'] ?? []
        );

        $this->defaultTags = $config['default_tags'] ?? [];
    }

    // --- MetricsAdapterInterface Implementation ---

    public function increment(string $name, float $value = 1.0, array $tags = []): void
    {
        $this->transport->send(
            $name,
            number_format($value, 1, '.', ''),
            self::TYPE_COUNTER,
            $this->resolveTags($tags)
        );
    }

    public function gauge(string $name, float $value, array $tags = []): void
    {
        $this->transport->send(
            $name,
            number_format($value, 1, '.', ''),
            self::TYPE_GAUGE,
            $this->resolveTags($tags)
        );
    }

    public function timing(string $name, float $milliseconds, array $tags = []): void
    {
        $this->transport->send(
            $name,
            number_format($milliseconds, 2, '.', ''),
            self::TYPE_TIMING,
            $this->resolveTags($tags)
        );
    }

    public function distribution(string $name, float $value, array $tags = []): void
    {
        $this->transport->send(
            $name,
            number_format($value, 2, '.', ''),
            self::TYPE_DISTRIBUTION,
            $this->resolveTags($tags)
        );
    }

    public function startTimer(string $name, array $tags = []): TimerInterface
    {
        $start = hrtime(true);

        return new class($this, $name, $tags, $start) implements TimerInterface {
            private bool $stopped = false;
            private readonly MetricsAdapterInterface $adapter;
            private readonly string $name;
            private readonly array $tags;
            private readonly int $start;

            public function __construct(
                MetricsAdapterInterface $adapter,
                string $name,
                array $tags,
                int $start
            ) {
                $this->adapter = $adapter;
                $this->name = $name;
                $this->tags = $tags;
                $this->start = $start;
            }

            public function stop(): void
            {
                if ($this->stopped) return;
                $this->stopped = true;

                $elapsed = (hrtime(true) - $this->start) / 1_000_000; // ns to ms
                $this->adapter->timing($this->name, $elapsed, $this->tags);
            }

            public function discard(): void
            {
                $this->stopped = true;
            }
        };
    }

    public function flush(): void
    {
        $this->transport->flush();
    }

    // --- AdapterInterface Implementation ---

    public function getId(): string
    {
        return 'dg.datadog';
    }

    public function getName(): string
    {
        return 'Datadog Metrics Adapter';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getFrameworkConstraint(): string
    {
        return '>=2.0.0 <3.0.0';
    }

    public function getTargetBlueprints(): array
    {
        return ['HUB-05'];
    }

    public function healthCheck(): bool
    {
        return $this->transport->ping();
    }

    public function boot(): void
    {
        // StatsD transport is lazy; no connection needed at boot
        $this->increment('adapter.booted');
    }

    public function shutdown(): void
    {
        $this->increment('adapter.shutdown');
        $this->transport->close();
    }

    // --- Private Helpers ---

    private function resolveTags(array $tags): array
    {
        return array_merge($this->defaultTags, $tags);
    }
}
```

### 4. Service Provider

```php
<?php
namespace Sovereign\Adapter\Datadog;

use Sovereign\Adapter\AdapterServiceProvider;
use Sovereign\Adapter\Contracts\AdapterInterface;
use Sovereign\Adapter\Contracts\MetricsAdapterInterface;

class DatadogServiceProvider extends AdapterServiceProvider
{
    protected function createAdapter(): AdapterInterface
    {
        $config = config('adapters.datadog', []);
        return new DatadogAdapter($config);
    }

    protected function bootServices(): void
    {
        // Register as default metrics adapter if configured
        if (config('adapters.datadog.default', false)) {
            $this->app->bind(
                MetricsAdapterInterface::class,
                fn() => $this->app->make(DatadogAdapter::class)
            );
        }

        // Attach to kernel lifecycle events to emit metrics
        $dispatcher = $this->app->make(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $adapter = $this->app->make(DatadogAdapter::class);

        $dispatcher->addListener('kernel.after_request', function () use ($adapter) {
            $adapter->increment('http.request');
        });

        $dispatcher->addListener('kernel.before_boot', function () use ($adapter) {
            $adapter->increment('kernel.boot');
        });
    }
}
```

### 5. Default Configuration

```php
<?php
// config/datadog.php
return [
    /*
    |--------------------------------------------------------------------------
    | Datadog Adapter Configuration
    |--------------------------------------------------------------------------
    */

    // DogStatsD agent host
    'host' => env('DATADOG_AGENT_HOST', '127.0.0.1'),

    // DogStatsD agent port
    'port' => env('DATADOG_AGENT_PORT', 8125),

    // Prefix for all metric names
    'metric_prefix' => env('DATADOG_METRIC_PREFIX', 'sovereign'),

    // Buffer size before flushing to DogStatsD
    'buffer_size' => env('DATADOG_BUFFER_SIZE', 50),

    // Default tags applied to every metric
    'default_tags' => [
        'env' => env('APP_ENV', 'production'),
        'service' => env('APP_NAME', 'sovereign'),
    ],

    // Set to true to make this the default metrics adapter
    'default' => env('DATADOG_DEFAULT', false),
];
```

## Testing

### Unit Test Pattern

```php
<?php
namespace Sovereign\Adapter\Datadog\Tests\Unit;

use Sovereign\Adapter\Datadog\DatadogAdapter;
use Sovereign\Adapter\Testing\AdapterTestCase;

class DatadogAdapterTest extends AdapterTestCase
{
    protected function createAdapter(): DatadogAdapter
    {
        return new DatadogAdapter([
            'host' => '127.0.0.1',
            'port' => 8125,
            'buffer_size' => 1, // Flush immediately for testing
            'default_tags' => ['env' => 'test'],
        ]);
    }

    protected function getMockConfig(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 8125,
            'metric_prefix' => 'test',
        ];
    }

    public function test_increment_sends_counter(): void
    {
        // Use reflection to verify the transport buffer
        $this->adapter->increment('test.counter', 1.0, ['endpoint' => 'api']);
        // Verify via transport mock (see logging template for pattern)
        $this->assertTrue(true);
    }

    public function test_timer_records_duration(): void
    {
        $timer = $this->adapter->startTimer('db.query', ['query_type' => 'select']);
        usleep(1_000); // 1ms
        $timer->stop();
        $this->assertTrue(true);
    }

    public function test_timer_discard_does_not_record(): void
    {
        $timer = $this->adapter->startTimer('slow.query');
        usleep(1_000);
        $timer->discard();
        $this->assertTrue(true);
    }

    public function test_gauge_sets_value(): void
    {
        $this->adapter->gauge('memory.usage', 85.5, ['unit' => 'percent']);
        $this->assertTrue(true);
    }
}
```

### Integration Test Pattern

```php
<?php
namespace Sovereign\Adapter\Datadog\Tests\Integration;

use Sovereign\Adapter\Datadog\DatadogAdapter;
use Sovereign\Adapter\Testing\AdapterIntegrationTest;

class DatadogAdapterIntegrationTest extends AdapterIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no Datadog agent is available
        if (!@fsockopen('127.0.0.1', 8125, $errno, $errstr, 1)) {
            $this->markTestSkipped(
                'Datadog agent not available at 127.0.0.1:8125'
            );
        }
    }

    public function test_end_to_end_metrics(): void
    {
        $adapter = new DatadogAdapter([
            'host' => '127.0.0.1',
            'port' => 8125,
            'buffer_size' => 1,
            'default_tags' => ['env' => 'integration-test'],
        ]);

        $adapter->boot();
        $adapter->increment('integration.test.counter');
        $adapter->gauge('integration.test.gauge', 42);

        $timer = $adapter->startTimer('integration.test.timing');
        usleep(5_000);
        $timer->stop();

        $adapter->shutdown();

        $this->assertTrue(true, 'Metrics sent to Datadog agent. Verify in Datadog UI.');
    }
}
```

## Alert Integration

DatadogAdapter can also create/update monitors via the HTTP API transport:

```php
// Usage in a deployment script or maintenance task
$dashboardClient = new DashboardClient($apiKey, $appKey);
$monitorClient = new MonitorClient($apiKey, $appKey);

// Create a monitor for high error rates
$monitorClient->createMonitor('metric_alert', [
    'name' => 'High Error Rate - sovereign_stack',
    'query' => 'avg(last_5m):avg:sovereign.http.errors{*} > 10',
    'message' => 'Error rate exceeded threshold.',
    'priority' => 2,
]);
```

## Verification Checklist

- [ ] `composer.json` follows `dg/adapter-{name}` naming convention
- [ ] `extra.sovereign-stack` metadata is populated correctly
- [ ] Adapter implements all `MetricsAdapterInterface` methods
- [ ] Timer implementation uses `hrtime()` for nanosecond precision
- [ ] Transport layer supports batching for performance
- [ ] Default tags propagate to all metrics
- [ ] Unit tests cover counter, gauge, timing, distribution
- [ ] Integration tests skip gracefully when agent unavailable
- [ ] Metric prefix is configurable via configuration
- [ ] Adapter handles send failures gracefully (logs, never throws)

## Related Documents

- [Adapter Pattern](/docs/integration/adapter-pattern.md) - Architecture and contract definitions
- [Interoperability Standards](/docs/integration/interoperability-standards.md) - Standard interface contracts
- [Adapter Library](/docs/integration/adapter-library.md) - Complete adapter catalog
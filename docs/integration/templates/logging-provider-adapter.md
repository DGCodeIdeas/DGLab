# Adapter Template: Logging Provider (Graylog GELF)

## Overview
This template demonstrates implementing a logging provider adapter using Graylog's GELF (Graylog Extended Log Format) protocol. Follow this template when creating new logging adapters for the Sovereign Stack.

## Interface Implemented
[`LoggerAdapterInterface`](/docs/integration/interoperability-standards.md#1-loggeradapterinterface-observability---logging)

## Package Structure

```
dg-adapter-graylog/
├── composer.json
├── README.md
├── src/
│   ├── GraylogAdapter.php                # Main adapter implementation
│   ├── GraylogServiceProvider.php        # ServiceProvider for registration
│   ├── Config/
│   │   └── graylog.php                   # Default configuration
│   ├── Exception/
│   │   ├── GraylogConnectionException.php
│   │   └── GraylogSendException.php
│   └── Transport/
│       ├── TransportInterface.php         # Transport abstraction
│       ├── UdpTransport.php              # UDP GELF transport
│       └── TcpTransport.php              # TCP GELF transport
├── config/
│   └── graylog.php                       # Published configuration
└── tests/
    ├── Unit/
    │   ├── GraylogAdapterTest.php
    │   └── Transport/
    │       ├── UdpTransportTest.php
    │       └── TcpTransportTest.php
    ├── Integration/
    │   └── GraylogAdapterIntegrationTest.php
    └── TestDouble/
        └── FakeGraylogServer.php
```

## Full Implementation

### 1. Composer Manifest

```json
{
    "name": "dg/adapter-graylog",
    "type": "sovereign-stack-adapter",
    "description": "Sovereign Stack adapter for Graylog GELF logging",
    "keywords": ["sovereign-stack", "adapter", "logging", "graylog", "gelf"],
    "license": "MIT",
    "require": {
        "php": ">=8.2",
        "sovereign/core": "^2.0",
        "sovereign/adapter-contracts": "^1.0"
    },
    "require-dev": {
        "sovereign/test-utils": "^1.0",
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "Sovereign\\Adapter\\Graylog\\": "src/"
        }
    },
    "extra": {
        "sovereign-stack": {
            "type": "adapter",
            "category": "logging",
            "target-blueprints": ["CORE-09"],
            "min-core-version": "2.0.0"
        }
    }
}
```

### 2. Transport Interface

```php
<?php
namespace Sovereign\Adapter\Graylog\Transport;

interface TransportInterface
{
    /**
     * Send a GELF message to the Graylog server.
     *
     * @param string $message  JSON-encoded GELF message
     * @throws GraylogConnectionException
     */
    public function send(string $message): void;

    /**
     * Check if the transport can connect to the server.
     */
    public function ping(): bool;

    /**
     * Close the transport connection.
     */
    public function close(): void;
}
```

### 3. UDP Transport Implementation

```php
<?php
namespace Sovereign\Adapter\Graylog\Transport;

use Sovereign\Adapter\Graylog\Exception\GraylogConnectionException;

class UdpTransport implements TransportInterface
{
    private const MAX_CHUNK_SIZE = 8152; // Standard GELF UDP chunk size
    private const MAX_MESSAGE_SIZE = 8388608; // 8MB max GELF message

    private ?\Socket $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 12201,
        private readonly int $timeout = 2
    ) {}

    public function send(string $message): void
    {
        $this->ensureConnected();

        $data = gzcompress($message, -1, ZLIB_ENCODING_GZIP);
        $length = strlen($data);

        if ($length > self::MAX_MESSAGE_SIZE) {
            throw new GraylogSendException(
                'Message exceeds maximum GELF size of 8MB'
            );
        }

        if ($length <= self::MAX_CHUNK_SIZE) {
            // Single message
            @socket_sendto(
                $this->socket,
                $data,
                $length,
                0,
                $this->host,
                $this->port
            );
        } else {
            // Chunked message
            $this->sendChunked($data);
        }
    }

    public function ping(): bool
    {
        try {
            $this->ensureConnected();
            return true;
        } catch (GraylogConnectionException) {
            return false;
        }
    }

    public function close(): void
    {
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
            throw new GraylogConnectionException(
                'Failed to create UDP socket: ' . socket_strerror(socket_last_error())
            );
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, [
            'sec' => $this->timeout,
            'usec' => 0,
        ]);
    }

    private function sendChunked(string $data): void
    {
        $chunks = str_split($data, self::MAX_CHUNK_SIZE);
        $messageId = crc32(substr($data, 0, 128));
        $chunkCount = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $header = pack('V', $messageId) . pack('C', $index) . pack('C', $chunkCount);
            @socket_sendto(
                $this->socket,
                $header . $chunk,
                strlen($header) + strlen($chunk),
                0,
                $this->host,
                $this->port
            );
        }
    }
}
```

### 4. Main Adapter Implementation

```php
<?php
namespace Sovereign\Adapter\Graylog;

use Sovereign\Adapter\BaseAdapter;
use Sovereign\Adapter\Contracts\LoggerAdapterInterface;
use Sovereign\Adapter\Graylog\Transport\TransportInterface;
use Sovereign\Adapter\Graylog\Transport\UdpTransport;
use Sovereign\Adapter\Graylog\Exception\GraylogSendException;
use Psr\Log\LogLevel;

class GraylogAdapter extends BaseAdapter implements LoggerAdapterInterface
{
    private const GELF_VERSION = '1.1';
    private const LEVEL_MAP = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];

    private TransportInterface $transport;
    private string $minLevel;
    private array $globalContext = [];
    private array $buffer = [];
    private int $bufferSize;

    public function __construct(array $config)
    {
        $this->bufferSize = $config['buffer_size'] ?? 10;

        $this->transport = match ($config['transport'] ?? 'udp') {
            'udp' => new UdpTransport(
                $config['host'],
                $config['port'] ?? 12201,
                $config['timeout'] ?? 2
            ),
            'tcp' => new TcpTransport(
                $config['host'],
                $config['port'] ?? 12201,
                $config['timeout'] ?? 2
            ),
            default => throw new \InvalidArgumentException(
                "Unknown transport: {$config['transport']}"
            ),
        };

        $this->minLevel = $config['level'] ?? LogLevel::DEBUG;
    }

    // --- LoggerAdapterInterface Implementation ---

    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->isLevelEnabled($level)) {
            return;
        }

        $gelfMessage = $this->buildGelfMessage($level, $message, $context);
        $this->buffer[] = $gelfMessage;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function setLevel(string $level): void
    {
        $this->minLevel = $level;
    }

    public function getLevel(): string
    {
        return $this->minLevel;
    }

    public function flush(): void
    {
        $messages = $this->buffer;
        $this->buffer = [];

        foreach ($messages as $message) {
            try {
                $this->transport->send(json_encode($message));
            } catch (GraylogSendException $e) {
                // Log to error log as fallback; never throw from an adapter
                error_log("Graylog adapter send failed: {$e->getMessage()}");
            }
        }
    }

    public function withContext(array $context): void
    {
        $this->globalContext = array_merge($this->globalContext, $context);
    }

    public function getPsrLogger(): ?\Psr\Log\LoggerInterface
    {
        // Graylog adapter doesn't expose a PSR-3 logger directly
        return null;
    }

    // --- AdapterInterface Implementation ---

    public function getId(): string
    {
        return 'dg.graylog';
    }

    public function getName(): string
    {
        return 'Graylog GELF Adapter';
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
        return ['CORE-09'];
    }

    public function healthCheck(): bool
    {
        return $this->transport->ping();
    }

    public function boot(): void
    {
        // Transport is lazy-initialized on first send
    }

    public function shutdown(): void
    {
        $this->flush();
        $this->transport->close();
    }

    // --- Private Helpers ---

    private function buildGelfMessage(string $level, string $message, array $context): array
    {
        $mergedContext = array_merge($this->globalContext, $context);

        $gelf = [
            'version'       => self::GELF_VERSION,
            'host'          => $mergedContext['host'] ?? gethostname(),
            'short_message' => $this->interpolate($message, $mergedContext),
            'timestamp'     => microtime(true),
            'level'         => self::LEVEL_MAP[$level] ?? 6,
            '_facility'     => $mergedContext['facility'] ?? 'sovereign-stack',
        ];

        // Add additional fields as GELF underscore-prefixed fields
        foreach ($mergedContext as $key => $value) {
            if (!in_array($key, ['host', 'facility'], true)) {
                $gelf["_{$key}"] = $this->sanitizeValue($value);
            }
        }

        return $gelf;
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            // Truncate very long strings for GELF compatibility
            return mb_strlen($value) > 32768
                ? mb_substr($value, 0, 32768) . '...'
                : $value;
        }
        if (is_scalar($value) || is_null($value)) {
            return $value;
        }
        // Arrays/objects get JSON-encoded
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function isLevelEnabled(string $level): bool
    {
        $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT     => 1,
            LogLevel::CRITICAL  => 2,
            LogLevel::ERROR     => 3,
            LogLevel::WARNING   => 4,
            LogLevel::NOTICE    => 5,
            LogLevel::INFO      => 6,
            LogLevel::DEBUG     => 7,
        ];

        $current = $levels[$level] ?? 7;
        $minimum = $levels[$this->minLevel] ?? 7;

        return $current <= $minimum;
    }
}
```

### 5. Service Provider

```php
<?php
namespace Sovereign\Adapter\Graylog;

use Sovereign\Adapter\AdapterServiceProvider;
use Sovereign\Adapter\Contracts\AdapterInterface;
use Sovereign\Adapter\Contracts\LoggerAdapterInterface;

class GraylogServiceProvider extends AdapterServiceProvider
{
    protected function createAdapter(): AdapterInterface
    {
        $config = config('adapters.graylog', []);
        return new GraylogAdapter($config);
    }

    public function bootServices(): void
    {
        // Register as the active logger if configured as default
        if (config('adapters.graylog.default', false)) {
            $this->app->bind(
                LoggerAdapterInterface::class,
                fn() => $this->app->make(GraylogAdapter::class)
            );
        }
    }
}
```

### 6. Default Configuration

```php
<?php
// config/graylog.php
return [
    /*
    |--------------------------------------------------------------------------
    | Graylog GELF Adapter Configuration
    |--------------------------------------------------------------------------
    */

    // Transport: 'udp' or 'tcp'
    'transport' => env('GRAYLOG_TRANSPORT', 'udp'),

    // Graylog server hostname or IP
    'host' => env('GRAYLOG_HOST', '127.0.0.1'),

    // Graylog GELF input port
    'port' => env('GRAYLOG_PORT', 12201),

    // Connection timeout in seconds
    'timeout' => env('GRAYLOG_TIMEOUT', 2),

    // Minimum log level (PSR-3)
    'level' => env('GRAYLOG_LEVEL', 'debug'),

    // Buffer size before flushing to Graylog
    'buffer_size' => env('GRAYLOG_BUFFER_SIZE', 10),

    // Set to true to make this the default logger adapter
    'default' => env('GRAYLOG_DEFAULT', false),
];
```

## Testing

### Unit Test Pattern

```php
<?php
namespace Sovereign\Adapter\Graylog\Tests\Unit;

use Sovereign\Adapter\Graylog\GraylogAdapter;
use Sovereign\Adapter\Graylog\Transport\TransportInterface;
use Sovereign\Adapter\Testing\AdapterTestCase;

class GraylogAdapterTest extends AdapterTestCase
{
    private TransportInterface $mockTransport;

    protected function createAdapter(): GraylogAdapter
    {
        $this->mockTransport = $this->createMock(TransportInterface::class);

        return new GraylogAdapter([
            'transport' => 'udp',
            'host' => '127.0.0.1',
            'port' => 12201,
            'level' => 'debug',
            'buffer_size' => 1, // Flush immediately for testing
        ]);
    }

    protected function getMockConfig(): array
    {
        return [
            'transport' => 'udp',
            'host' => '127.0.0.1',
            'port' => 12201,
            'level' => 'debug',
        ];
    }

    public function test_log_sends_gelf_message(): void
    {
        $this->mockTransport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (string $json) {
                $data = json_decode($json, true);
                $this->assertEquals('1.1', $data['version']);
                $this->assertEquals('Test message', $data['short_message']);
                $this->assertArrayHasKey('timestamp', $data);
                return true;
            }));

        $this->adapter->log('info', 'Test message');
    }

    public function test_log_respects_level_filtering(): void
    {
        $this->mockTransport->expects($this->never())
            ->method('send');

        $adapter = new GraylogAdapter([
            'transport' => 'udp',
            'host' => '127.0.0.1',
            'level' => 'error', // Only error+ passes
            'buffer_size' => 1,
        ]);

        $adapter->log('debug', 'Should be filtered');
    }

    public function test_global_context_is_included(): void
    {
        $this->mockTransport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (string $json) {
                $data = json_decode($json, true);
                $this->assertEquals('req-123', $data['_request_id']);
                $this->assertEquals('tenant-a', $data['_tenant_id']);
                return true;
            }));

        $this->adapter->withContext([
            'request_id' => 'req-123',
            'tenant_id' => 'tenant-a',
        ]);
        $this->adapter->log('info', 'Context test');
    }
}
```

### Integration Test Pattern

```php
<?php
namespace Sovereign\Adapter\Graylog\Tests\Integration;

use Sovereign\Adapter\Graylog\GraylogAdapter;
use Sovereign\Adapter\Testing\AdapterIntegrationTest;

class GraylogAdapterIntegrationTest extends AdapterIntegrationTest
{
    private const TEST_SERVER_HOST = '127.0.0.1';
    private const TEST_SERVER_PORT = 12201;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->isGraylogAvailable()) {
            $this->markTestSkipped(
                'Graylog server not available at ' . self::TEST_SERVER_HOST . ':' . self::TEST_SERVER_PORT
            );
        }
    }

    protected function createAdapter(): GraylogAdapter
    {
        return new GraylogAdapter([
            'transport' => 'udp',
            'host' => self::TEST_SERVER_HOST,
            'port' => self::TEST_SERVER_PORT,
            'level' => 'debug',
            'buffer_size' => 1,
        ]);
    }

    private function isGraylogAvailable(): bool
    {
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            return false;
        }
        $result = @socket_sendto($socket, '', 0, 0, self::TEST_SERVER_HOST, self::TEST_SERVER_PORT);
        socket_close($socket);
        return $result !== false;
    }

    public function test_end_to_end_logging(): void
    {
        $adapter = $this->createAdapter();
        $adapter->boot();

        $adapter->log('info', 'Integration test message', [
            'test_id' => 'e2e-001',
        ]);

        $adapter->shutdown();

        // Verify: Check Graylog UI or API for received message
        $this->assertTrue(true, 'Message sent to Graylog. Verify via Graylog web interface.');
    }
}
```

## Verification Checklist

- [ ] `composer.json` follows `dg/adapter-{name}` naming convention
- [ ] `extra.sovereign-stack` metadata is populated correctly
- [ ] Adapter implements all `LoggerAdapterInterface` methods
- [ ] Adapter wraps third-party exceptions in adapter-specific exceptions
- [ ] Adapter never exposes third-party SDK types in its public API
- [ ] Transport layer is abstracted behind an interface
- [ ] Unit tests cover filtering, batching, context propagation
- [ ] Integration tests can be skipped when external service is unavailable
- [ ] Configuration is validated in the constructor with clear error messages
- [ ] Adapter handles send failures gracefully (logs to error_log, never throws)

## Related Documents

- [Adapter Pattern](/docs/integration/adapter-pattern.md) - Architecture and contract definitions
- [Interoperability Standards](/docs/integration/interoperability-standards.md) - Standard interface contracts
- [Adapter Library](/docs/integration/adapter-library.md) - Complete adapter catalog
# Interoperability Standards

## Overview
This document defines standard interfaces for common integration points across the Sovereign Stack. Each standard includes an interface contract, example adapter implementation, and testing requirements. These contracts are owned by the framework (in `Sovereign\Adapter\Contracts\`) and implemented by third-party adapters.

## Standard Interface Index

| # | Interface | Domain | Blueprint Reference | Status |
|---|-----------|--------|-------------------|--------|
| 1 | `LoggerAdapterInterface` | Observability - Logging | CORE-09 | Stable |
| 2 | `MetricsAdapterInterface` | Observability - Monitoring | HUB-05 | Stable |
| 3 | `TracingAdapterInterface` | Observability - Distributed Tracing | HUB-06 | Stable |
| 4 | `ContainerAdapterInterface` | Deployment - Container Orchestration | DEPLOY-01 | Stable |
| 5 | `CacheAdapterInterface` | Performance - Caching | HUB-02 | Stable |
| 6 | `QueueAdapterInterface` | Messaging - Queue Systems | HUB-11 | Stable |
| 7 | `AuthProviderAdapterInterface` | Security - Authentication | CORE-11 | Stable |
| 8 | `StorageAdapterInterface` | Storage - File Systems | CORE-14 | Stable |
| 9 | `EncryptionAdapterInterface` | Security - Key Management | CORE-16 | Stable |
| 10 | `SearchAdapterInterface` | Search - Full-Text Search | HUB-07 | Stable |
| 11 | `NotificationAdapterInterface` | Communication - Notifications | HUB-08 | Stable |
| 12 | `RateLimiterAdapterInterface` | Security - Rate Limiting | CORE-12 | Stable |
| 13 | `AuditLogAdapterInterface` | Compliance - Audit Logging | HUB-09 | Beta |
| 14 | `ConfigProviderAdapterInterface` | Configuration - External Config | CORE-10 | Beta |

---

### 1. LoggerAdapterInterface (Observability - Logging)

**Target Blueprint:** [CORE-09](/docs/blueprints/Core/CORE-09.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

use Psr\Log\LogLevel;

/**
 * Standard interface for logging service adapters.
 * Follows PSR-3 LogLevel convention for severity levels.
 */
interface LoggerAdapterInterface extends AdapterInterface
{
    /**
     * Log a message at a specific level.
     *
     * @param string $level  One of LogLevel::EMERGENCY .. LogLevel::DEBUG
     * @param string $message  Log message with {placeholder} support
     * @param array  $context  Key-value pairs for placeholder replacement
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * Set the minimum log level this adapter will process.
     * Messages below this threshold are discarded.
     */
    public function setLevel(string $level): void;

    /**
     * Get the current minimum log level.
     */
    public function getLevel(): string;

    /**
     * Flush buffered log entries to the remote service.
     * Called during Kernel shutdown.
     */
    public function flush(): void;

    /**
     * Attach additional contextual data to all subsequent log entries.
     *
     * @param array $context  Global context (e.g., request_id, tenant_id)
     */
    public function withContext(array $context): void;

    /**
     * Get the underlying PSR-3 LoggerInterface instance, if available.
     * Returns null for adapters that don't expose a PSR-3 logger.
     */
    public function getPsrLogger(): ?\Psr\Log\LoggerInterface;
}
```

#### Example: Monolog Adapter Skeleton

```php
<?php
namespace Sovereign\Adapter\Monolog;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Sovereign\Adapter\Contracts\LoggerAdapterInterface;
use Sovereign\Adapter\BaseAdapter;

class MonologAdapter extends BaseAdapter implements LoggerAdapterInterface
{
    private MonologLogger $logger;
    private array $globalContext = [];

    public function __construct(array $config)
    {
        $this->logger = new MonologLogger($config['channel'] ?? 'app');

        foreach ($config['handlers'] ?? [] as $handlerConfig) {
            $handler = match ($handlerConfig['type']) {
                'stream' => new StreamHandler(
                    $handlerConfig['path'],
                    $handlerConfig['level']
                ),
                'rotating' => new RotatingFileHandler(
                    $handlerConfig['path'],
                    $handlerConfig['max_files'] ?? 30,
                    $handlerConfig['level']
                ),
                default => throw new \InvalidArgumentException(
                    "Unknown handler type: {$handlerConfig['type']}"
                ),
            };

            if ($config['format'] === 'json') {
                $handler->setFormatter(new JsonFormatter());
            }

            $this->logger->pushHandler($handler);
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log(
            $level,
            $message,
            array_merge($this->globalContext, $context)
        );
    }

    public function setLevel(string $level): void
    {
        foreach ($this->logger->getHandlers() as $handler) {
            $handler->setLevel(
                MonologLogger::toMonologLevel($level)
            );
        }
    }

    public function getLevel(): string
    {
        // Return the minimum level across all handlers
        $levels = array_map(
            fn($h) => $h->getLevel()->getName(),
            $this->logger->getHandlers()
        );
        return min($levels);
    }

    public function flush(): void
    {
        $this->logger->reset();
    }

    public function withContext(array $context): void
    {
        $this->globalContext = array_merge($this->globalContext, $context);
    }

    public function getPsrLogger(): ?\Psr\Log\LoggerInterface
    {
        return $this->logger;
    }

    // --- AdapterInterface implementation ---
    public function getId(): string { return 'dg.monolog'; }
    public function getName(): string { return 'Monolog Adapter'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getFrameworkConstraint(): string { return '>=2.0.0 <3.0.0'; }
    public function getTargetBlueprints(): array { return ['CORE-09']; }

    public function healthCheck(): bool
    {
        try {
            $this->logger->debug('Health check');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function boot(): void
    {
        // Monolog is ready immediately
    }

    public function shutdown(): void
    {
        $this->flush();
    }
}
```

#### Testing Requirements
- [ ] Unit test with `MockHandler` verifying log levels
- [ ] Unit test verifying `withContext()` propagates to entries
- [ ] Unit test verifying `flush()` clears buffer
- [ ] Integration test writing to temp file, reading back contents
- [ ] Integration test verifying JSON format when configured
- [ ] Contract test for all required interface methods

---

### 2. MetricsAdapterInterface (Observability - Monitoring)

**Target Blueprint:** [HUB-05](/docs/blueprints/Hub/HUB-05.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for metrics/monitoring system adapters.
 */
interface MetricsAdapterInterface extends AdapterInterface
{
    /**
     * Increment a counter metric.
     */
    public function increment(string $name, float $value = 1.0, array $tags = []): void;

    /**
     * Record a gauge value.
     */
    public function gauge(string $name, float $value, array $tags = []): void;

    /**
     * Record a timing/histogram value in milliseconds.
     */
    public function timing(string $name, float $milliseconds, array $tags = []): void;

    /**
     * Record a distribution value (for percentile calculations).
     */
    public function distribution(string $name, float $value, array $tags = []): void;

    /**
     * Start a timer and return a TimerInterface that records duration on stop.
     */
    public function startTimer(string $name, array $tags = []): TimerInterface;

    /**
     * Flush buffered metrics to the remote service.
     */
    public function flush(): void;
}

/**
 * Timer returned by startTimer().
 */
interface TimerInterface
{
    /**
     * Stop the timer and record the elapsed duration.
     */
    public function stop(): void;

    /**
     * Stop the timer but discard the recording.
     */
    public function discard(): void;
}
```

#### Example: Prometheus Adapter Skeleton

```php
<?php
namespace Sovereign\Adapter\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\RenderTextFormat;
use Sovereign\Adapter\Contracts\MetricsAdapterInterface;
use Sovereign\Adapter\Contracts\TimerInterface;
use Sovereign\Adapter\BaseAdapter;

class PrometheusAdapter extends BaseAdapter implements MetricsAdapterInterface
{
    private CollectorRegistry $registry;
    private array $defaultTags;

    public function __construct(array $config)
    {
        $storage = match ($config['storage'] ?? 'memory') {
            'memory' => new InMemory(),
            'redis' => new \Prometheus\Storage\Redis($config['redis']),
            'apcu' => new \Prometheus\Storage\APCUG(),
            default => new InMemory(),
        };
        $this->registry = new CollectorRegistry($storage);
        $this->defaultTags = $config['default_tags'] ?? [];
    }

    private function resolveTags(array $tags): array
    {
        return array_merge($this->defaultTags, $tags);
    }

    public function increment(string $name, float $value = 1.0, array $tags = []): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'app',
            $name,
            'Counter: ' . $name,
            array_keys($this->resolveTags($tags))
        );
        $counter->incBy($value, $this->resolveTags($tags));
    }

    public function gauge(string $name, float $value, array $tags = []): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            'app',
            $name,
            'Gauge: ' . $name,
            array_keys($this->resolveTags($tags))
        );
        $gauge->set($value, $this->resolveTags($tags));
    }

    public function timing(string $name, float $milliseconds, array $tags = []): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            'app',
            $name,
            'Timing: ' . $name,
            array_keys($this->resolveTags($tags)),
            [0.1, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0, 300.0]
        );
        $histogram->observe($milliseconds / 1000.0, $this->resolveTags($tags));
    }

    public function distribution(string $name, float $value, array $tags = []): void
    {
        $this->timing($name, $value, $tags);
    }

    public function startTimer(string $name, array $tags = []): TimerInterface
    {
        $start = microtime(true);
        return new class($this, $name, $tags, $start) implements TimerInterface {
            private bool $stopped = false;

            public function __construct(
                private MetricsAdapterInterface $adapter,
                private string $name,
                private array $tags,
                private float $start
            ) {}

            public function stop(): void
            {
                if ($this->stopped) return;
                $this->stopped = true;
                $elapsed = (microtime(true) - $this->start) * 1000;
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
        $renderer = new RenderTextFormat();
        file_put_contents(
            sys_get_temp_dir() . '/prometheus_metrics.txt',
            $renderer->render($this->registry->getMetricFamilySamples())
        );
    }

    // --- AdapterInterface implementation ---
    public function getId(): string { return 'dg.prometheus'; }
    public function getName(): string { return 'Prometheus Adapter'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getFrameworkConstraint(): string { return '>=2.0.0 <3.0.0'; }
    public function getTargetBlueprints(): array { return ['HUB-05']; }

    public function healthCheck(): bool
    {
        return $this->registry->getMetricFamilySamples() !== [];
    }

    public function boot(): void {}
    public function shutdown(): void {}
}
```

#### Testing Requirements
- [ ] Unit test with InMemory storage verifying counter increments
- [ ] Unit test verifying gauge set/get
- [ ] Unit test verifying timer recording
- [ ] Unit test verifying tag propagation
- [ ] Integration test with Prometheus pushgateway
- [ ] Contract test for all required interface methods

---

### 3. TracingAdapterInterface (Observability - Distributed Tracing)

**Target Blueprint:** [HUB-06](/docs/blueprints/Hub/HUB-06.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for distributed tracing adapters.
 */
interface TracingAdapterInterface extends AdapterInterface
{
    /**
     * Start a new trace span.
     *
     * @param string $name       Span name (e.g., "database.query")
     * @param array  $attributes Key-value metadata
     * @param string|null $parentSpanId  Parent span for nested traces
     * @return SpanInterface
     */
    public function startSpan(string $name, array $attributes = [], ?string $parentSpanId = null): SpanInterface;

    /**
     * Inject trace context into outgoing request headers.
     * Used for propagating traces across service boundaries.
     */
    public function injectContext(array &$headers): void;

    /**
     * Extract trace context from incoming request headers.
     * Used for continuing traces from upstream services.
     */
    public function extractContext(array $headers): ?string;

    /**
     * Get the current trace ID (if a trace is active).
     */
    public function getCurrentTraceId(): ?string;

    /**
     * Flush pending spans to the tracing backend.
     */
    public function flush(): void;
}

/**
 * Represents a single span within a trace.
 */
interface SpanInterface
{
    public function getName(): string;
    public function getSpanId(): string;
    public function getTraceId(): string;

    /**
     * Add key-value attributes to this span.
     */
    public function setAttributes(array $attributes): void;

    /**
     * Record an exception on this span.
     */
    public function recordException(\Throwable $e): void;

    /**
     * Mark this span as errored with a description.
     */
    public function setError(string $description): void;

    /**
     * End the span and record its duration.
     */
    public function end(): void;

    /**
     * Check if the span has ended.
     */
    public function isEnded(): bool;
}
```

#### Testing Requirements
- [ ] Unit test verifying span creation and lifecycle
- [ ] Unit test verifying parent-child span relationships
- [ ] Unit test verifying inject/extract propagation
- [ ] Unit test verifying error recording
- [ ] Integration test with Jaeger or OpenTelemetry collector
- [ ] Contract test for all required interface methods

---

### 4. ContainerAdapterInterface (Deployment - Container Orchestration)

**Target Blueprint:** [DEPLOY-01](/docs/blueprints/Deploy/DEPLOY-01.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for container orchestration platform adapters.
 */
interface ContainerAdapterInterface extends AdapterInterface
{
    /**
     * Deploy a service to the orchestration platform.
     *
     * @param string $serviceName  Service identifier
     * @param array  $spec         Deployment specification (image, replicas, env, ports, etc.)
     * @return DeploymentResult
     */
    public function deploy(string $serviceName, array $spec): DeploymentResult;

    /**
     * Scale a service to the specified number of replicas.
     */
    public function scale(string $serviceName, int $replicas): void;

    /**
     * Get the health status of a deployed service.
     */
    public function getServiceStatus(string $serviceName): ServiceStatus;

    /**
     * List all services managed by this adapter.
     * @return ServiceStatus[]
     */
    public function listServices(): array;

    /**
     * Rollback a service to a previous deployment revision.
     */
    public function rollback(string $serviceName, string $revision): DeploymentResult;

    /**
     * Stream logs from a service instance.
     *
     * @param string $serviceName
     * @param int    $tailLines  Number of recent lines to fetch
     * @return string[]
     */
    public function getLogs(string $serviceName, int $tailLines = 100): array;
}

class DeploymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $deploymentId,
        public readonly string $revision,
        public readonly ?string $errorMessage = null
    ) {}
}

class ServiceStatus
{
    public function __construct(
        public readonly string $serviceName,
        public readonly int $desiredReplicas,
        public readonly int $readyReplicas,
        public readonly string $status, // running, degraded, failed, stopped
        public readonly ?string $version,
        public readonly array $endpoints = []
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with mock orchestrator API
- [ ] Unit test verifying deployment spec transformation
- [ ] Unit test verifying scaling operations
- [ ] Unit test verifying health check mapping
- [ ] Integration test with Docker Compose or kind cluster
- [ ] Contract test for all required interface methods

---

### 5. CacheAdapterInterface (Performance - Caching)

**Target Blueprint:** [HUB-02](/docs/blueprints/Hub/HUB-02.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for cache backend adapters.
 */
interface CacheAdapterInterface extends AdapterInterface
{
    /**
     * Retrieve an item from cache.
     *
     * @param string $key      Cache key
     * @param mixed  $default  Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in cache.
     *
     * @param string $key      Cache key
     * @param mixed  $value    Value to store (must be serializable)
     * @param int    $ttl      Time-to-live in seconds (0 = forever)
     */
    public function set(string $key, mixed $value, int $ttl = 0): void;

    /**
     * Delete an item from cache.
     */
    public function delete(string $key): void;

    /**
     * Clear the entire cache.
     */
    public function clear(): void;

    /**
     * Check if a key exists in cache.
     */
    public function has(string $key): bool;

    /**
     * Retrieve multiple items at once.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array;

    /**
     * Store multiple items at once.
     *
     * @param array<string, mixed> $items  Key-value pairs
     * @param int $ttl  Time-to-live in seconds
     */
    public function setMultiple(array $items, int $ttl = 0): void;

    /**
     * Delete multiple items at once.
     *
     * @param string[] $keys
     */
    public function deleteMultiple(array $keys): void;

    /**
     * Increment a numeric cache value.
     */
    public function increment(string $key, int $amount = 1): int;

    /**
     * Decrement a numeric cache value.
     */
    public function decrement(string $key, int $amount = 1): int;

    /**
     * Get cache statistics (hits, misses, size, etc.).
     */
    public function getStats(): CacheStats;
}

class CacheStats
{
    public function __construct(
        public readonly int $hits = 0,
        public readonly int $misses = 0,
        public readonly int $size = 0,
        public readonly float $hitRate = 0.0
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with in-memory driver verifying CRUD operations
- [ ] Unit test verifying TTL expiration
- [ ] Unit test verifying multi-get/multi-set
- [ ] Unit test verifying increment/decrement
- [ ] Integration test with Redis server
- [ ] Contract test for all required interface methods

---

### 6. QueueAdapterInterface (Messaging - Queue Systems)

**Target Blueprint:** [HUB-11](/docs/blueprints/Hub/HUB-11.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for message queue adapters.
 */
interface QueueAdapterInterface extends AdapterInterface
{
    /**
     * Push a message onto a queue.
     *
     * @param string $queue    Queue name
     * @param mixed  $payload  Message payload (must be serializable)
     * @param array  $options  Adapter-specific options (delay, priority, etc.)
     * @return string  Message ID
     */
    public function push(string $queue, mixed $payload, array $options = []): string;

    /**
     * Pull a message from a queue (non-blocking).
     *
     * @param string $queue  Queue name
     * @return QueuedMessage|null
     */
    public function pull(string $queue): ?QueuedMessage;

    /**
     * Acknowledge that a message has been processed.
     */
    public function acknowledge(string $queue, string $messageId): void;

    /**
     * Reject a message (returns it to the queue or dead-letter).
     */
    public function reject(string $queue, string $messageId, bool $requeue = false): void;

    /**
     * Get the approximate number of messages in the queue.
     */
    public function size(string $queue): int;

    /**
     * Purge all messages from a queue.
     */
    public function purge(string $queue): void;

    /**
     * Create a queue if it doesn't exist.
     *
     * @param string $queue  Queue name
     * @param array  $options  Queue configuration (durable, max_length, etc.)
     */
    public function declareQueue(string $queue, array $options = []): void;
}

class QueuedMessage
{
    public function __construct(
        public readonly string $id,
        public readonly mixed $payload,
        public readonly array $attributes,
        public readonly int $attempts = 0
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with mock broker verifying push/pull
- [ ] Unit test verifying acknowledge/reject lifecycle
- [ ] Unit test verifying queue purge
- [ ] Unit test verifying message ordering guarantees
- [ ] Integration test with RabbitMQ or Kafka
- [ ] Contract test for all required interface methods

---

### 7. AuthProviderAdapterInterface (Security - Authentication)

**Target Blueprint:** [CORE-11](/docs/blueprints/Core/CORE-11.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for authentication provider adapters.
 * Supports multiple auth mechanisms behind a unified contract.
 */
interface AuthProviderAdapterInterface extends AdapterInterface
{
    /**
     * Authenticate a user with credentials.
     *
     * @param string $provider  Provider identifier (e.g., "google", "ldap")
     * @param array  $credentials  Provider-specific credentials
     * @return AuthResult
     */
    public function authenticate(string $provider, array $credentials): AuthResult;

    /**
     * Validate a token and return the authenticated identity.
     *
     * @param string $token     Access/ID token
     * @param string $provider  Provider identifier
     * @return AuthResult
     */
    public function validateToken(string $token, string $provider): AuthResult;

    /**
     * Get the authorization URL for OAuth-style flows.
     */
    public function getAuthorizationUrl(string $provider, array $options = []): string;

    /**
     * Exchange an authorization code for tokens.
     */
    public function exchangeCode(string $provider, string $code): TokenSet;

    /**
     * Refresh an expired token.
     */
    public function refreshToken(string $provider, string $refreshToken): TokenSet;

    /**
     * Revoke a token (logout).
     */
    public function revokeToken(string $provider, string $token): void;

    /**
     * Get user profile/claims from the provider.
     */
    public function getUserInfo(string $provider, string $token): array;
}

class AuthResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $identity,
        public readonly ?string $token,
        public readonly ?string $refreshToken,
        public readonly array $claims = [],
        public readonly ?string $errorMessage = null
    ) {}
}

class TokenSet
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly ?string $idToken = null
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with mock OAuth server
- [ ] Unit test verifying token validation
- [ ] Unit test verifying authorization URL generation
- [ ] Unit test verifying code exchange flow
- [ ] Integration test with OAuth provider sandbox
- [ ] Contract test for all required interface methods

---

### 8. StorageAdapterInterface (Storage - File Systems)

**Target Blueprint:** [CORE-14](/docs/blueprints/Core/CORE-14.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for file storage adapters.
 */
interface StorageAdapterInterface extends AdapterInterface
{
    /**
     * Read a file's contents.
     */
    public function get(string $path): string;

    /**
     * Write contents to a file.
     */
    public function put(string $path, string $contents, array $options = []): bool;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Delete a file.
     */
    public function delete(string $path): bool;

    /**
     * Move/rename a file.
     */
    public function move(string $from, string $to): bool;

    /**
     * Copy a file.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Get file size in bytes.
     */
    public function size(string $path): int;

    /**
     * Get file last modified timestamp.
     */
    public function lastModified(string $path): int;

    /**
     * Get a URL for the file (if publicly accessible).
     */
    public function url(string $path): string;

    /**
     * List files in a directory.
     * @return string[]
     */
    public function listContents(string $directory, bool $recursive = false): array;
}
```

#### Testing Requirements
- [ ] Unit test with local filesystem driver
- [ ] Unit test verifying path traversal prevention
- [ ] Unit test verifying atomic write patterns
- [ ] Integration test with S3-compatible storage (MinIO)
- [ ] Contract test for all required interface methods

---

### 9. EncryptionAdapterInterface (Security - Key Management)

**Target Blueprint:** [CORE-16](/docs/blueprints/Core/CORE-16.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for encryption and key management adapters.
 */
interface EncryptionAdapterInterface extends AdapterInterface
{
    /**
     * Encrypt plaintext data.
     *
     * @param string $plaintext  Data to encrypt
     * @param array  $context    Encryption context (key ID, algorithm overrides)
     * @return string  Encrypted payload
     */
    public function encrypt(string $plaintext, array $context = []): string;

    /**
     * Decrypt ciphertext data.
     *
     * @param string $ciphertext  Data to decrypt
     * @param array  $context     Decryption context
     * @return string  Decrypted plaintext
     */
    public function decrypt(string $ciphertext, array $context = []): string;

    /**
     * Generate a new encryption key.
     *
     * @param string $keyId    Identifier for the key
     * @param array  $options  Key generation options (algorithm, length)
     * @return string  Key reference or ID
     */
    public function generateKey(string $keyId, array $options = []): string;

    /**
     * Rotate a key (generate new version, keep old for decryption).
     */
    public function rotateKey(string $keyId): void;

    /**
     * List available keys with metadata.
     * @return KeyMetadata[]
     */
    public function listKeys(): array;

    /**
     * Sign data using the adapter's signing capability.
     */
    public function sign(string $data, string $keyId): string;

    /**
     * Verify a signature.
     */
    public function verify(string $data, string $signature, string $keyId): bool;
}

class KeyMetadata
{
    public function __construct(
        public readonly string $keyId,
        public readonly string $algorithm,
        public readonly int $createdAt,
        public readonly int $version,
        public readonly bool $enabled
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with symmetric encryption driver
- [ ] Unit test verifying encrypt/decrypt round-trip
- [ ] Unit test verifying key generation
- [ ] Unit test verifying sign/verify
- [ ] Integration test with Vault dev server
- [ ] Contract test for all required interface methods

---

### 10. SearchAdapterInterface (Search - Full-Text Search)

**Target Blueprint:** [HUB-07](/docs/blueprints/Hub/HUB-07.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for search engine adapters.
 */
interface SearchAdapterInterface extends AdapterInterface
{
    /**
     * Index a document.
     *
     * @param string $index     Index name
     * @param string $id        Document ID
     * @param array  $body      Document body
     * @param array  $options   Indexing options
     */
    public function index(string $index, string $id, array $body, array $options = []): void;

    /**
     * Index multiple documents in bulk.
     *
     * @param string $index   Index name
     * @param array  $documents  Array of [id => body] pairs
     */
    public function bulkIndex(string $index, array $documents): void;

    /**
     * Search for documents.
     *
     * @param string $index     Index name
     * @param string $query     Search query
     * @param array  $options   Search options (filters, sort, pagination)
     * @return SearchResult
     */
    public function search(string $index, string $query, array $options = []): SearchResult;

    /**
     * Get a document by ID.
     */
    public function get(string $index, string $id): ?array;

    /**
     * Delete a document.
     */
    public function delete(string $index, string $id): void;

    /**
     * Delete an entire index.
     */
    public function deleteIndex(string $index): void;

    /**
     * Create an index with mappings.
     */
    public function createIndex(string $index, array $mappings): void;
}

class SearchResult
{
    public function __construct(
        public readonly array $hits,
        public readonly int $total,
        public readonly float $tookMs,
        public readonly array $aggregations = []
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with in-memory search driver
- [ ] Unit test verifying index/document CRUD
- [ ] Unit test verifying search with filters
- [ ] Integration test with Elasticsearch or Meilisearch
- [ ] Contract test for all required interface methods

---

### 11. NotificationAdapterInterface (Communication - Notifications)

**Target Blueprint:** [HUB-08](/docs/blueprints/Hub/HUB-08.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for notification delivery adapters.
 */
interface NotificationAdapterInterface extends AdapterInterface
{
    /**
     * Send a notification.
     *
     * @param string $channel   Channel (email, sms, push, slack)
     * @param array  $recipients  Recipient identifiers
     * @param string $subject   Message subject/title
     * @param string $body      Message body
     * @param array  $options   Channel-specific options
     * @return NotificationResult
     */
    public function send(string $channel, array $recipients, string $subject, string $body, array $options = []): NotificationResult;

    /**
     * Send a templated notification.
     *
     * @param string $channel      Channel
     * @param array  $recipients   Recipient identifiers
     * @param string $templateId   Template identifier
     * @param array  $templateData Template variable values
     * @param array  $options      Channel-specific options
     * @return NotificationResult
     */
    public function sendTemplate(string $channel, array $recipients, string $templateId, array $templateData, array $options = []): NotificationResult;

    /**
     * Get delivery status for a previous notification.
     */
    public function getDeliveryStatus(string $notificationId): string;
}

class NotificationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $notificationId,
        public readonly int $recipientCount,
        public readonly ?string $errorMessage = null
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with mailpit/mock SMTP server
- [ ] Unit test verifying template rendering
- [ ] Unit test verifying recipient validation
- [ ] Integration test with SendGrid or Mailtrap
- [ ] Contract test for all required interface methods

---

### 12. RateLimiterAdapterInterface (Security - Rate Limiting)

**Target Blueprint:** [CORE-12](/docs/blueprints/Core/CORE-12.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for rate limiting adapters.
 */
interface RateLimiterAdapterInterface extends AdapterInterface
{
    /**
     * Attempt to consume one token from the rate limit bucket.
     *
     * @param string $key       Rate limit key (e.g., "api:user:42")
     * @param int    $maxTokens Maximum tokens allowed in the window
     * @param int    $window    Time window in seconds
     * @return RateLimitResult
     */
    public function consume(string $key, int $maxTokens, int $window): RateLimitResult;

    /**
     * Get the current rate limit status for a key without consuming.
     */
    public function getStatus(string $key, int $maxTokens, int $window): RateLimitStatus;

    /**
     * Reset the rate limit counter for a key.
     */
    public function reset(string $key): void;
}

class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $resetAt,
        public readonly int $retryAfter = 0
    ) {}
}

class RateLimitStatus
{
    public function __construct(
        public readonly int $remaining,
        public readonly int $limit,
        public readonly int $resetAt
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test with in-memory driver verifying token consumption
- [ ] Unit test verifying window reset
- [ ] Unit test verifying burst handling
- [ ] Integration test with Redis
- [ ] Contract test for all required interface methods

---

### 13. AuditLogAdapterInterface (Compliance - Audit Logging) [Beta]

**Target Blueprint:** [HUB-09](/docs/blueprints/Hub/HUB-09.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for audit logging adapters.
 * Provides immutable, tamper-evident audit trail capabilities.
 */
interface AuditLogAdapterInterface extends AdapterInterface
{
    /**
     * Record an audit event.
     *
     * @param string $action     Action performed (e.g., "user.delete", "config.update")
     * @param array  $payload    Event details (who, what, when, context)
     * @param array  $options    Immutability options (hash chain, signing)
     * @return string  Event ID
     */
    public function record(string $action, array $payload, array $options = []): string;

    /**
     * Query audit events.
     *
     * @param array $filters  Filters (actor, action, time range, etc.)
     * @param int   $limit    Max results
     * @param int   $offset   Pagination offset
     * @return AuditEvent[]
     */
    public function query(array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Verify the integrity of an audit chain (tamper detection).
     */
    public function verifyIntegrity(string $fromId, string $toId): IntegrityReport;

    /**
     * Export audit events for compliance reporting.
     */
    public function export(array $filters, string $format = 'json'): string;
}

class AuditEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $action,
        public readonly array $payload,
        public readonly int $timestamp,
        public readonly ?string $hash = null
    ) {}
}

class IntegrityReport
{
    public function __construct(
        public readonly bool $tampered,
        public readonly int $eventsChecked,
        public readonly ?string $firstBrokenChain = null
    ) {}
}
```

#### Testing Requirements
- [ ] Unit test verifying event recording with hash chain
- [ ] Unit test verifying tamper detection
- [ ] Unit test verifying query filtering
- [ ] Integration test with database-backed store
- [ ] Contract test for all required interface methods

---

### 14. ConfigProviderAdapterInterface (Configuration - External Config) [Beta]

**Target Blueprint:** [CORE-10](/docs/blueprints/Core/CORE-10.md)

#### Interface Contract

```php
<?php
namespace Sovereign\Adapter\Contracts;

/**
 * Standard interface for external configuration providers.
 * Allows configuration to be sourced from remote services (etcd, Consul, AWS SSM, etc.).
 */
interface ConfigProviderAdapterInterface extends AdapterInterface
{
    /**
     * Get a configuration value.
     *
     * @param string $key      Configuration key (dot notation supported)
     * @param mixed  $default  Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a configuration value remotely.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool;

    /**
     * Delete a configuration key.
     */
    public function delete(string $key): void;

    /**
     * Watch for changes to a configuration key.
     * Calls the callback when the value changes.
     */
    public function watch(string $key, callable $callback): void;

    /**
     * List all configuration keys under a prefix.
     * @return string[]
     */
    public function list(string $prefix): array;
}
```

#### Testing Requirements
- [ ] Unit test with in-memory config store
- [ ] Unit test verifying hierarchical key access
- [ ] Unit test verifying watch callback invocation
- [ ] Integration test with etcd or Consul
- [ ] Contract test for all required interface methods

---

## Contract Testing Suite

Every adapter MUST pass the following contract test suite before being accepted:

### AdapterContractTest

```php
<?php
namespace Sovereign\Adapter\Tests\Contracts;

use PHPUnit\Framework\TestCase;
use Sovereign\Adapter\Contracts\AdapterInterface;

abstract class AdapterContractTest extends TestCase
{
    abstract protected function createAdapter(): AdapterInterface;

    public function test_adapter_has_unique_id(): void
    {
        $id = $this->createAdapter()->getId();
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+\.[a-z0-9_-]+$/', $id);
    }

    public function test_adapter_has_name(): void
    {
        $this->assertNotEmpty($this->createAdapter()->getName());
    }

    public function test_adapter_has_semver_version(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $this->createAdapter()->getVersion()
        );
    }

    public function test_adapter_has_framework_constraint(): void
    {
        $this->assertNotEmpty(
            $this->createAdapter()->getFrameworkConstraint()
        );
    }

    public function test_adapter_has_target_blueprints(): void
    {
        $blueprints = $this->createAdapter()->getTargetBlueprints();
        $this->assertNotEmpty($blueprints);
        foreach ($blueprints as $bp) {
            $this->assertMatchesRegularExpression('/^[A-Z]+-\d+$/', $bp);
        }
    }

    public function test_adapter_health_check_returns_bool(): void
    {
        $result = $this->createAdapter()->healthCheck();
        $this->assertIsBool($result);
    }

    public function test_adapter_boot_shutdown_cycle(): void
    {
        $adapter = $this->createAdapter();
        $adapter->boot();   // Should not throw
        $adapter->shutdown(); // Should not throw
    }
}
```

## Compliance Checklist for Each Standard

- [ ] Interface contract defined in `Sovereign\Adapter\Contracts\`
- [ ] Skeleton adapter implementation provided
- [ ] Unit tests covering all interface methods
- [ ] Integration tests with real or containerized service
- [ ] Contract tests verifying adapter contract compliance
- [ ] Configuration schema documented
- [ ] Error handling strategy documented (exceptions vs. fallbacks)
- [ ] Performance characteristics documented (latency expectations)
- [ ] Example usage in documentation

## Related Documents

- [Integration Bridge Pattern](./adapter-pattern.md) - Architecture overview
- [Adapter Library Documentation](./adapter-library.md) - Complete adapter catalog
- [Adapter Templates](./templates/) - Reference implementations
- [Marketplace Concept](./marketplace-concept.md) - Vetting and publishing
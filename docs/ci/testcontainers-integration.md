# Testcontainers Integration Guide

> **Navigation:** [CI Home](index.md) | [In-Memory Alternatives](in-memory-alternatives.md) | [CI Configuration](ci-configuration.md) | [Local Development Setup](local-development-setup.md)
>
> **Related:** [Testing Recipes](../testing/recipes.md) | [Docker Compose Dev](../../infrastructure/docker-compose.dev.yml)

---

## Overview

**Testcontainers** provides lightweight, ephemeral instances of external services (Redis, Elasticsearch, MySQL/MariaDB) as disposable Docker containers during test execution. This eliminates the assumption that CI runners have pre-configured infrastructure, addressing **Weakness 4: CI Assumes External Service Availability**.

### Why Testcontainers over Static Services

| Aspect | Testcontainers | Static Services |
|--------|---------------|-----------------|
| **Setup time** | Automatic (container pulls) | Manual provisioning |
| **Isolation** | Per-test-suite containers | Shared state pollution |
| **Version control** | Pin image tags in code | Ops-managed versions |
| **Parallelism** | Dedicated containers per shard | Contention on shared services |
| **Cleanup** | Automatic `rm -v` after suite | Manual cleanup |

---

## Installation

### Composer Dependency

```bash
composer require --dev testcontainers/testcontainers-php
```

Testcontainers for PHP requires the Docker socket to be accessible. In CI environments this is typically provided by mounting the Docker socket into the CI container:

```yaml
# .github/workflows/ci.yml
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      docker:
        image: docker:dind
        options: --privileged
```

### PHPUnit Configuration

Add the following to [`phpunit.xml`](../../Legacy.old/phpunit.xml) to enable container-based test suites:

```xml
<phpunit>
    <php>
        <env name="TESTCONTAINERS_ENABLED" value="true"/>
        <env name="DOCKER_HOST" value="unix:///var/run/docker.sock"/>
    </php>
</phpunit>
```

---

## Container Setup Patterns

### 1. Redis Container

```php
<?php

namespace DGLab\Tests\Integration\CI;

use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;

trait RedisContainer
{
    private static ?GenericContainer $redisContainer = null;
    private static string $redisHost = '127.0.0.1';
    private static int $redisPort = 6379;

    public static function startRedisContainer(): void
    {
        if (self::$redisContainer !== null) {
            return;
        }

        self::$redisContainer = new GenericContainer('redis:7-alpine');
        self::$redisContainer->withExposedPorts(6379);
        self::$redisContainer->start();

        self::$redisHost = self::$redisContainer->getHost();
        self::$redisPort = self::$redisContainer->getMappedPort(6379);
    }

    public static function stopRedisContainer(): void
    {
        if (self::$redisContainer !== null) {
            self::$redisContainer->stop();
            self::$redisContainer = null;
        }
    }

    public function getRedisConnectionParams(): array
    {
        return [
            'host' => self::$redisHost,
            'port' => self::$redisPort,
            'password' => '',
            'database' => 0,
        ];
    }
}
```

#### Usage in Test Suite

```php
<?php

namespace DGLab\Tests\Integration\Cache;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Tests\Integration\CI\RedisContainer;

class RedisCacheIntegrationTest extends IntegrationTestCase
{
    use RedisContainer;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::startRedisContainer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopRedisContainer();
        parent::tearDownAfterClass();
    }

    public function test_cache_set_and_get(): void
    {
        $params = $this->getRedisConnectionParams();
        $redis = new \Redis();
        $redis->connect($params['host'], $params['port']);

        $redis->set('test_key', 'test_value', 60);
        $this->assertEquals('test_value', $redis->get('test_key'));
    }
}
```

---

### 2. Elasticsearch Container

```php
<?php

namespace DGLab\Tests\Integration\CI;

use Testcontainers\Container\GenericContainer;

trait ElasticsearchContainer
{
    private static ?GenericContainer $esContainer = null;
    private static string $esHost = '127.0.0.1';
    private static int $esPort = 9200;

    public static function startElasticsearchContainer(): void
    {
        if (self::$esContainer !== null) {
            return;
        }

        self::$esContainer = new GenericContainer('elasticsearch:8.12.0');
        self::$esContainer->withExposedPorts(9200, 9300);
        self::$esContainer->withEnvironment([
            'discovery.type' => 'single-node',
            'xpack.security.enabled' => 'false',
            'ES_JAVA_OPTS' => '-Xms256m -Xmx256m',
        ]);
        self::$esContainer->start();

        self::$esHost = self::$esContainer->getHost();
        self::$esPort = self::$esContainer->getMappedPort(9200);

        // Wait for Elasticsearch to be ready
        self::waitForElasticsearch();
    }

    private static function waitForElasticsearch(int $maxRetries = 30): void
    {
        $client = new \GuzzleHttp\Client(['base_uri' => "http://{$esHost}:{$esPort}"]);
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $response = $client->get('/_cluster/health');
                if ($response->getStatusCode() === 200) {
                    return;
                }
            } catch (\Exception $e) {
                // Not ready yet
            }
            sleep(1);
        }
        throw new \RuntimeException('Elasticsearch did not become ready in time');
    }

    public static function stopElasticsearchContainer(): void
    {
        if (self::$esContainer !== null) {
            self::$esContainer->stop();
            self::$esContainer = null;
        }
    }

    public function getElasticsearchConnectionParams(): array
    {
        return [
            'host' => self::$esHost,
            'port' => self::$esPort,
            'scheme' => 'http',
        ];
    }
}
```

#### Usage in Test Suite

```php
<?php

namespace DGLab\Tests\Integration\Search;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Tests\Integration\CI\ElasticsearchContainer;

class ElasticsearchIntegrationTest extends IntegrationTestCase
{
    use ElasticsearchContainer;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::startElasticsearchContainer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopElasticsearchContainer();
        parent::tearDownAfterClass();
    }

    public function test_index_and_search(): void
    {
        $params = $this->getElasticsearchConnectionParams();
        $client = new \GuzzleHttp\Client([
            'base_uri' => "{$params['scheme']}://{$params['host']}:{$params['port']}",
        ]);

        // Create index
        $client->put('/test_index', [
            'json' => [
                'settings' => ['number_of_shards' => 1, 'number_of_replicas' => 0],
            ],
        ]);

        // Index a document
        $client->post('/test_index/_doc', [
            'json' => ['title' => 'DGLab Search Test', 'content' => 'Testing Elasticsearch container'],
        ]);

        // Force refresh
        $client->post('/test_index/_refresh');

        // Search
        $response = $client->post('/test_index/_search', [
            'json' => ['query' => ['match' => ['title' => 'DGLab']]],
        ]);

        $data = json_decode($response->getBody(), true);
        $this->assertGreaterThan(0, $data['hits']['total']['value']);
    }
}
```

---

### 3. MySQL/MariaDB Container

```php
<?php

namespace DGLab\Tests\Integration\CI;

use Testcontainers\Container\GenericContainer;

trait MysqlContainer
{
    private static ?GenericContainer $mysqlContainer = null;
    private static string $mysqlHost = '127.0.0.1';
    private static int $mysqlPort = 3306;
    private static string $mysqlDatabase = 'dglab_test';
    private static string $mysqlUser = 'dglab';
    private static string $mysqlPassword = 'dglab_test';

    public static function startMysqlContainer(): void
    {
        if (self::$mysqlContainer !== null) {
            return;
        }

        self::$mysqlContainer = new GenericContainer('mariadb:11');
        self::$mysqlContainer->withExposedPorts(3306);
        self::$mysqlContainer->withEnvironment([
            'MARIADB_DATABASE' => self::$mysqlDatabase,
            'MARIADB_USER' => self::$mysqlUser,
            'MARIADB_PASSWORD' => self::$mysqlPassword,
            'MARIADB_ROOT_PASSWORD' => 'root_test',
        ]);
        self::$mysqlContainer->start();

        self::$mysqlHost = self::$mysqlContainer->getHost();
        self::$mysqlPort = self::$mysqlContainer->getMappedPort(3306);

        // Wait for MySQL to be ready
        self::waitForMysql();
    }

    private static function waitForMysql(int $maxRetries = 30): void
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $pdo = new \PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s', self::$mysqlHost, self::$mysqlPort, self::$mysqlDatabase),
                    self::$mysqlUser,
                    self::$mysqlPassword,
                    [\PDO::ATTR_TIMEOUT => 1]
                );
                $pdo->query('SELECT 1');
                return;
            } catch (\PDOException $e) {
                // Not ready yet
            }
            sleep(1);
        }
        throw new \RuntimeException('MySQL did not become ready in time');
    }

    public static function stopMysqlContainer(): void
    {
        if (self::$mysqlContainer !== null) {
            self::$mysqlContainer->stop();
            self::$mysqlContainer = null;
        }
    }

    public function getMysqlConnectionParams(): array
    {
        return [
            'host' => self::$mysqlHost,
            'port' => self::$mysqlPort,
            'database' => self::$mysqlDatabase,
            'username' => self::$mysqlUser,
            'password' => self::$mysqlPassword,
        ];
    }

    /**
     * Run all available migrations against the MySQL container.
     */
    public function runMigrations(): void
    {
        $params = $this->getMysqlConnectionParams();
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s', $params['host'], $params['port'], $params['database']),
            $params['username'],
            $params['password']
        );

        $migrationDir = __DIR__ . '/../../../database/migrations';
        $files = glob($migrationDir . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }
}
```

---

## Container Lifecycle Management

### Strategy: Test Suite Isolation

Use `setUpBeforeClass()` / `tearDownAfterClass()` to share containers across all tests in a suite, minimizing container start/stop overhead:

```php
abstract class ContainerAwareTestCase extends IntegrationTestCase
{
    use RedisContainer, ElasticsearchContainer, MysqlContainer;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::startRedisContainer();
        self::startElasticsearchContainer();
        self::startMysqlContainer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopMysqlContainer();
        self::stopElasticsearchContainer();
        self::stopRedisContainer();
        parent::tearDownAfterClass();
    }
}
```

### Strategy: Per-Test Isolation

For tests that must not share state, start/stop per test method:

```php
protected function setUp(): void
{
    parent::setUp();
    self::startRedisContainer(); // fresh container each test
}

protected function tearDown(): void
{
    self::stopRedisContainer();
    parent::tearDown();
}
```

---

## CI Pipeline Integration

### GitHub Actions Example

```yaml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      docker:
        image: docker:dind
        options: --privileged

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: pdo, pdo_mysql, redis, json
          coverage: xdebug

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Unit tests (no external services)
        run: php vendor/bin/phpunit --testsuite=unit

      - name: Integration tests (Testcontainers)
        run: php vendor/bin/phpunit --testsuite=integration
        env:
          TESTCONTAINERS_ENABLED: "true"
          DOCKER_HOST: "tcp://docker:2375"
```

### Resource Limits

Testcontainers supports setting CPU and memory limits on containers:

```php
self::$redisContainer = new GenericContainer('redis:7-alpine');
self::$redisContainer->withCpuShares(512);   // 0.5 CPU
self::$redisContainer->withMemoryLimit('256m');
self::$redisContainer->withMemorySwap('512m');
```

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| `Docker\Exception\DockerConnectionException` | Docker socket not accessible | Set `DOCKER_HOST` env var or mount `/var/run/docker.sock` |
| Container fails to start | Image not pulled / OOM | Check network, increase memory limit |
| Port mapping conflict | Local service on same port | Kill local service or use different port range |
| Slow test suite startup | Multiple containers starting sequentially | Reuse containers with static lifecycle methods |

---

## References

- [Testcontainers for PHP](https://testcontainers.com/guides/getting-started-with-testcontainers-for-php/)
- [Testcontainers Documentation](https://testcontainers.com/)
- [DGLab Docker Compose Dev](../../infrastructure/docker-compose.dev.yml)
- [DGLab Testing Recipes](../testing/recipes.md)
- [CI Configuration](ci-configuration.md)

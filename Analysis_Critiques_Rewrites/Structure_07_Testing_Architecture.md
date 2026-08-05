# DGLab Wheel Architecture
## Structure 07: Testing Architecture

> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Layered Verification

---

## 1. The Testing Principle

In the DGLab wheel, **every component is tested at the layer it occupies**. A Core primitive is tested in isolation. A Hub service is tested against its interfaces. A Spoke is tested as a complete vertical slice. The wheel is verified from the center outward.

```
Testing Pyramid:

                    ▲
                   ╱ ╲
                  ╱   ╲     E2E Tests (Full wheel spin)
                 ╱  5% ╲    Browser / API automation
                ╱─────────╲
               ╱           ╲
              ╱  Integration ╲
             ╱    Tests 15%   ╲   Hub-to-Hub, Spoke-to-Hub
            ╱───────────────────╲
           ╱                     ╲
          ╱     Contract Tests    ╲
         ╱        20%              ╲  Interface compliance
        ╱───────────────────────────╲
       ╱                             ╲
      ╱        Unit Tests 60%         ╲  Core primitives, Hub services
     ╱───────────────────────────────────╲
```

**Rule:** A test at layer N must not mock layer N-1 (closer to Core). It may mock layer N+1 (closer to Rim).

---

## 2. Test Categories by Wheel Layer

### 2.1 Core Tests — Unit & Property-Based

Core primitives are tested in **pure isolation** — no database, no network, no filesystem (unless testing filesystem primitives).

```php
// CORE-16 (Crypto) — unit test
final class CryptoEnvelopeTest extends TestCase
{
    public function test_encrypt_decrypt_roundtrip(): void
    {
        $crypto = new EncryptionService(new FileKeyStore(__DIR__ . '/fixtures/keys'));

        $plaintext = 'sensitive data';
        $aad = 'tenant_123';

        $envelope = $crypto->encrypt($plaintext, $aad);
        $decrypted = $crypto->decrypt($envelope, $aad);

        $this->assertSame($plaintext, $decrypted);
    }

    public function test_aad_mismatch_fails(): void
    {
        $this->expectException(DecryptionException::class);

        $crypto = new EncryptionService(new FileKeyStore(__DIR__ . '/fixtures/keys'));
        $envelope = $crypto->encrypt('data', 'tenant_a');
        $crypto->decrypt($envelope, 'tenant_b'); // Wrong AAD
    }

    public function test_key_rotation_preserves_decryptability(): void
    {
        $keyManager = new KeyManager(new FileKeyStore(__DIR__ . '/fixtures/keys'));
        $crypto = new EncryptionService($keyManager);

        // Encrypt with old key
        $envelope = $crypto->encrypt('data', '');

        // Rotate key
        $keyManager->rotate();

        // Old envelope still decryptable
        $this->assertSame('data', $crypto->decrypt($envelope, ''));

        // New encryptions use new key
        $newEnvelope = $crypto->encrypt('new data', '');
        $this->assertNotSame($envelope, $newEnvelope);
    }
}
```

### 2.2 Hub Tests — Integration with In-Memory Doubles

Hub services are tested against real Core primitives but with **in-memory** or **test-double** versions of other Hub services.

```php
// HUB-04 (Identity) — integration test
final class IdentityServiceTest extends HubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use real DBAL with SQLite in-memory
        $this->dbal = $this->container()->get(DbalInterface::class);
        $this->dbal->execute('CREATE TABLE users (...)');

        // Use real cache with in-memory store
        $this->cache = $this->container()->get(CacheInterface::class);

        // Use real crypto with test keys
        $this->crypto = $this->container()->get(EncryptionInterface::class);
    }

    public function test_user_creation_triggers_audit_event(): void
    {
        $events = new CollectingEventBus(); // Test double for HUB-09
        $this->container()->instance(EventBusInterface::class, $events);

        $identity = new IdentityService($this->dbal, $this->cache, $this->crypto);
        $user = $identity->createUser('test@example.com', 'password123', 'tenant_1');

        $this->assertNotNull($user);
        $this->assertSame('test@example.com', $user->email);

        // Verify audit event was dispatched
        $this->assertCount(1, $events->dispatched);
        $this->assertSame('user.created', $events->dispatched[0]->eventType());
    }

    public function test_tenant_isolation_enforced(): void
    {
        $identity = new IdentityService($this->dbal, $this->cache, $this->crypto);

        // Create user in tenant A
        $userA = $identity->createUser('a@example.com', 'password', 'tenant_a');

        // Attempt to find as tenant B
        $this->expectException(SecurityException::class);
        $identity->findUser($userA->id, tenantId: 'tenant_b');
    }
}
```

### 2.3 Spoke Tests — Vertical Slice Testing

Spokes are tested as **complete vertical slices** — real HTTP request through real middleware to real controllers, with the database and cache running in Docker.

```php
// ESPOKE-03 (Account Hub) — E2E-style spoke test
final class AccountHubTest extends SpokeTestCase
{
    protected string $spoke = 'espoke-03';

    public function test_user_can_update_profile(): void
    {
        // Arrange: Create authenticated user
        $user = $this->createUser(email: 'test@example.com', tenantId: 'tenant_1');
        $token = $this->generateJwt($user);

        // Act: Send HTTP request through full stack
        $response = $this->postJson(
            uri: '/account/profile',
            data: ['name' => 'Updated Name'],
            headers: ['Authorization' => "Bearer {$token}"]
        );

        // Assert: Response
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');

        // Assert: Database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'tenant_id' => 'tenant_1',
        ]);

        // Assert: Audit
        $this->assertAuditLogContains([
            'action' => 'user.profile.updated',
            'actor_id' => $user->id,
        ]);

        // Assert: Cache invalidated
        $this->assertCacheMissing("user:tenant_1:profile:{$user->id}");
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $response = $this->getJson('/account/profile');
        $response->assertStatus(401);
    }

    public function test_cross_tenant_access_blocked(): void
    {
        $user = $this->createUser(tenantId: 'tenant_a');
        $token = $this->generateJwt($user);

        // Attempt to access tenant_b endpoint
        $response = $this->getJson(
            '/account/profile',
            headers: ['Authorization' => "Bearer {$token}", 'X-Tenant-Id' => 'tenant_b']
        );

        $response->assertStatus(403);
    }
}
```

### 2.4 Bridge Tests — Contract & Security

BRIDGE-01 is tested for **contract compliance** and **security properties**:

```php
// BRIDGE-01 (Vanguard) — security test
final class VanguardSecurityTest extends BridgeTestCase
{
    public function test_jwt_expired_token_rejected(): void
    {
        $expiredToken = $this->generateJwt(
            user: $this->createUser(),
            expiresAt: new \DateTimeImmutable('-1 hour')
        );

        $response = $this->getJson('/api/private/data', [
            'Authorization' => "Bearer {$expiredToken}"
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'token_expired');
    }

    public function test_rate_limit_enforced(): void
    {
        $token = $this->generateJwt($this->createUser());

        // Exhaust rate limit
        for ($i = 0; $i < 100; $i++) {
            $this->getJson('/api/public/data', ['Authorization' => "Bearer {$token}"]);
        }

        // 101st request should be rate limited
        $response = $this->getJson('/api/public/data', ['Authorization' => "Bearer {$token}"]);
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_zero_exposure_internal_spoke_inaccessible(): void
    {
        // Direct request to internal spoke from public internet
        $response = $this->getJson('/admin/users'); // ISPOKE-01 route

        // Should be rejected at BRIDGE-01 before reaching ISPOKE-01
        $response->assertStatus(401); // No JWT
    }

    public function test_admin_route_requires_super_admin_role(): void
    {
        $regularUser = $this->createUser(role: 'user');
        $token = $this->generateJwt($regularUser);

        $response = $this->getJson('/admin/dashboard', [
            'Authorization' => "Bearer {$token}"
        ]);

        $response->assertStatus(403);
    }
}
```

---

## 3. The Test Harness (HUB-29)

`HUB-29` provides the infrastructure for testing the wheel:

```php
interface HubTestHarnessInterface
{
    /**
     * Replace a service in the container with a test double.
     * Must be called before boot().
     */
    public function mockService(string $serviceId, object $mock): void;

    /**
     * Authenticate as a specific user for subsequent requests.
     */
    public function actingAs(Authenticatable $user, array $scopes = []): self;

    /**
     * Create a tenant with fixture data.
     */
    public function createTenant(array $overrides = []): Tenant;

    /**
     * Create a user within a tenant.
     */
    public function createUser(array $overrides = []): User;

    /**
     * Generate a valid JWT for a user.
     */
    public function generateJwt(User $user, array $claims = []): string;

    /**
     * Assert that the audit log contains a matching entry.
     */
    public function assertAuditLogContains(array $criteria): void;

    /**
     * Assert that a cache key exists (or is missing).
     */
    public function assertCacheHas(string $key): void;
    public function assertCacheMissing(string $key): void;

    /**
     * Assert database state.
     */
    public function assertDatabaseHas(string $table, array $data): void;
    public function assertDatabaseMissing(string $table, array $data): void;

    /**
     * Freeze time for deterministic testing.
     */
    public function freezeTime(\DateTimeImmutable $time): void;

    /**
     * Travel forward in time (for testing scheduled tasks).
     */
    public function travelTo(\DateTimeImmutable $time): void;
}
```

---

## 4. Test Fixtures & Factories

### 4.1 Model Factories

```php
// UserFactory — generates test users with valid data
final class UserFactory
{
    public function create(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? Ulid::generate(),
            tenantId: $overrides['tenant_id'] ?? TenantFactory::create()->id,
            email: $overrides['email'] ?? faker()->email(),
            name: $overrides['name'] ?? faker()->name(),
            role: $overrides['role'] ?? 'user',
            passwordHash: $overrides['password_hash'] ?? password_hash('password123', PASSWORD_ARGON2ID),
            createdAt: $overrides['created_at'] ?? new \DateTimeImmutable(),
        );
    }
}

// TenantFactory — generates test tenants
final class TenantFactory
{
    public function create(array $overrides = []): Tenant
    {
        return new Tenant(
            id: $overrides['id'] ?? Ulid::generate(),
            name: $overrides['name'] ?? faker()->company(),
            domain: $overrides['domain'] ?? faker()->domainName(),
            plan: $overrides['plan'] ?? 'free',
            isActive: $overrides['is_active'] ?? true,
        );
    }
}
```

### 4.2 Fixture Loaders

```php
// Database fixtures for integration tests
final class DatabaseFixtures
{
    public function load(string $fixtureName): void
    {
        $path = __DIR__ . "/fixtures/{$fixtureName}.sql";
        $sql = file_get_contents($path);

        foreach (explode(';', $sql) as $statement) {
            if (trim($statement)) {
                $this->dbal->execute($statement);
            }
        }
    }

    public function seedUsers(int $count, string $tenantId): array
    {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = UserFactory::create(['tenant_id' => $tenantId]);
        }
        return $users;
    }
}
```

---

## 5. Performance Testing

### 5.1 Benchmark Methodology

Every Hub service and Core primitive defines measurable benchmarks:

```php
// CORE-16 benchmark
final class CryptoBenchmark extends BenchmarkCase
{
    public function test_encrypt_throughput(): void
    {
        $crypto = $this->container()->get(EncryptionInterface::class);
        $payload = str_repeat('x', 1024); // 1KB

        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $crypto->encrypt($payload, 'test');
        }
        $elapsed = (hrtime(true) - $start) / 1e6; // ms

        $this->reportMetric('crypto.encrypt.1kb', $elapsed / 1000); // ms per op
        // No assertion — just measurement. Baseline established over time.
    }
}
```

### 5.2 Load Testing with k6

```javascript
// tests/load/public-api.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '2m', target: 100 },   // Ramp up
        { duration: '5m', target: 100 },   // Steady state
        { duration: '2m', target: 200 },   // Stress
        { duration: '2m', target: 0 },     // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<200'],   // 95% under 200ms
        http_req_failed: ['rate<0.01'],     // < 1% errors
    },
};

export default function () {
    const res = http.get('https://api.sovereign.example/v1/status');
    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 200ms': (r) => r.timings.duration < 200,
    });
    sleep(1);
}
```

---

## 6. Security Testing

### 6.1 Static Analysis

```yaml
# phpstan.neon — strictest level
parameters:
    level: 9
    paths:
        - core/src
        - hub/src
        - spokes/src
        - bridge/src
    rules:
        - NoRawSqlConcatRule
        - NoResolutionInRegisterRule
        - RequiredScopeAnnotationRule
        - TenantScopeEnforcementRule
    ignoreErrors: []
```

### 6.2 Dependency Audit

```bash
# Run in CI
composer audit --format=json
# Fails build if any CVE > moderate severity
```

### 6.3 Secret Scanning

```bash
# Pre-commit hook
# Fails commit if any secret pattern detected
trufflehog filesystem . --only-verified
```

### 6.4 Penetration Test Scenarios

| Scenario | Tool | Frequency |
|---|---|---|
| SQL injection | SQLMap + custom tests | Per commit |
| XSS | OWASP ZAP | Per deploy |
| JWT forgery | Custom test suite | Per commit |
| CSRF | Burp Suite | Quarterly |
| Privilege escalation | Custom RBAC tests | Per commit |
| Path traversal | Custom file upload tests | Per commit |

---

## 7. Chaos Testing

Chaos tests verify the wheel's resilience by intentionally breaking components:

```php
// Chaos test: Database failover
final class DatabaseChaosTest extends ChaosTestCase
{
    public function test_app_survives_primary_db_failure(): void
    {
        // Arrange: App is serving requests
        $this->warmCache();

        // Act: Kill primary database
        $this->infrastructure()->killService('mysql-primary');

        // Assert: Read requests still succeed (from cache or replica)
        $response = $this->getJson('/api/public/content');
        $response->assertStatus(200);

        // Assert: Write requests fail gracefully
        $writeResponse = $this->postJson('/api/private/action', []);
        $writeResponse->assertStatus(503);
        $writeResponse->assertJsonPath('error', 'service_unavailable');
    }

    public function test_app_survives_redis_failure(): void
    {
        // Arrange
        $this->actingAs($this->createUser());

        // Act: Kill Redis
        $this->infrastructure()->killService('redis');

        // Assert: App falls back to database (slower but functional)
        $response = $this->getJson('/account/profile');
        $response->assertStatus(200);

        // Assert: No cache-related errors exposed to user
        $this->assertNoErrorInLogs(level: 'critical');
    }
}
```

---

## 8. CI/CD Test Pipeline

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  static-analysis:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install
      - run: vendor/bin/phpstan analyse --error-format=github
      - run: vendor/bin/psalm --output-format=github
      - run: composer audit

  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', coverage: xdebug }
      - run: composer install
      - run: vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage.xml
      - uses: codecov/codecov-action@v3
        with: { files: coverage.xml }

  integration-tests:
    runs-on: ubuntu-latest
    services:
      mysql: { image: mysql:8.0, env: { MYSQL_ROOT_PASSWORD: root } }
      redis: { image: redis:7, ports: ['6379:6379'] }
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install
      - run: vendor/bin/phpunit --testsuite=integration

  e2e-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker-compose -f tests/e2e/docker-compose.yml up -d
      - run: sleep 30 # Wait for services
      - run: vendor/bin/phpunit --testsuite=e2e
      - run: docker-compose -f tests/e2e/docker-compose.yml down

  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: trufflehog filesystem . --only-verified --fail
      - run: docker run --rm -v $(pwd):/app aquasec/trivy fs /app

  performance-baseline:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-dev --optimize-autoloader
      - run: php tests/benchmark/run.php --report=benchmark.json
      - run: php tests/benchmark/compare.php baseline.json benchmark.json
```

---

## 9. Test Isolation Guarantees

| Guarantee | Mechanism |
|---|---|
| **Database isolation** | Each test runs in a transaction rolled back after test |
| **Cache isolation** | Test cache namespace: `test:{testId}:*` |
| **Event isolation** | CollectingEventBus captures events; no real dispatch |
| **Time isolation** | `freezeTime()` prevents time-based flakiness |
| **File isolation** | `storage/tests/{testId}/` for file operations |
| **Network isolation** | HTTP tests use in-memory kernel; no real network |

---

*End of Structure 07: Testing Architecture*

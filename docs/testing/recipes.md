# Testing Recipes Cookbook

## Overview

A catalog of ~20 documented testing patterns for common DGLab scenarios. Each recipe follows a consistent format: **When to use → Code example → Expected results → Common pitfalls**.

### Test Environment Conventions

| Convention | Standard |
|-----------|----------|
| **Base test class** | [`DGLab\Tests\TestCase`](../Legacy.old/tests/TestCase.php) |
| **Integration base** | [`DGLab\Tests\IntegrationTestCase`](../Legacy.old/tests/IntegrationTestCase.php) |
| **Database** | SQLite `:memory:` for integration tests |
| **Mocking** | Prophecy (`ProphecyTrait`) |
| **Event faking** | [`EventFake`](../Legacy.old/tests/Unit/Core/EventFake.php) |
| **Custom assertions** | [`MakesReactiveAssertions`](../Legacy.old/tests/Concerns/MakesReactiveAssertions.php), [`MakesAccessibilityAssertions`](../Legacy.old/tests/Concerns/MakesAccessibilityAssertions.php), [`MakesVisualAssertions`](../Legacy.old/tests/Concerns/MakesVisualAssertions.php) |
| **Test runner** | `php cli/test.php run` with `--unit`/`--integration`/`--browser` filters |
| **Scaffolding** | `php cli/test.php make:test <Name>` |

---

## Category A: Foundation Patterns (4 recipes)

---

### Recipe 1: Unit Test with Mocking

**When to use**: You need to test a class that depends on external services (database, cache, HTTP client) without actually calling those services.

```php
<?php

namespace DGLab\Tests\Unit\Services;

use DGLab\Tests\TestCase;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\KeyManagementService;
use Prophecy\PhpUnit\ProphecyTrait;

class JWTServiceTest extends TestCase
{
    use ProphecyTrait;

    private JWTService $service;
    private $keyManagement;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock of the dependency
        $this->keyManagement = $this->prophesize(KeyManagementService::class);

        // Configure mock behavior
        $this->keyManagement->getPublicKey()
            ->willReturn('-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----');

        // Inject mock via constructor
        $this->service = new JWTService($this->keyManagement->reveal());
    }

    public function test_it_generates_a_valid_token(): void
    {
        $token = $this->service->generateToken(['user_id' => 42, 'role' => 'admin']);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertEquals(3, count(explode('.', $token))); // JWT has 3 parts
    }

    public function test_it_validates_a_token(): void
    {
        $token = $this->service->generateToken(['user_id' => 42]);
        $payload = $this->service->validateToken($token);

        $this->assertEquals(42, $payload['user_id']);
    }

    public function test_it_rejects_expired_tokens(): void
    {
        $token = $this->service->generateToken(['user_id' => 42, 'exp' => time() - 3600]);

        $this->expectException(\RuntimeException::class);
        $this->service->validateToken($token);
    }

    public function test_it_caches_public_key(): void
    {
        // First call fetches from key management
        $this->service->getPublicKey();

        // Second call should not hit key management again (cached)
        $this->service->getPublicKey();

        // Prophecy will fail if getPublicKey() was called more than once
        $this->keyManagement->getPublicKey()->shouldHaveBeenCalledTimes(1);
    }
}
```

**Expected**: All assertions pass, mock interactions verified.

**Pitfalls**:
- Always call `$mock->reveal()` when injecting prophesized objects
- Use `shouldHaveBeenCalledTimes()` to verify expected call counts
- Don't mock value objects — only mock services with side effects

---

### Recipe 2: Test Fixtures Setup & Cleanup

**When to use**: You need temporary files, directories, or database state for testing, and must clean up afterward.

```php
<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;

class InfrastructureTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create isolated temp directory
        $this->tempDir = sys_get_temp_dir() . '/dg_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/logs', 0777, true);
        mkdir($this->tempDir . '/cache', 0777, true);

        // Register test-specific services using temp dir
        $this->app->singleton(\Psr\Log\LoggerInterface::class, function () {
            return new \DGLab\Core\Logger($this->tempDir . '/logs');
        });
    }

    protected function tearDown(): void
    {
        // Cleanup: remove temp directory and all contents
        if ($this->tempDir && is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }

        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_logger_writes_to_temp_directory(): void
    {
        $logger = $this->app->get(\Psr\Log\LoggerInterface::class);
        $logger->info('Test log entry');

        $logFile = $this->tempDir . '/logs/dglab.log';
        $this->assertFileExists($logFile);
        $this->assertStringContainsString('Test log entry', file_get_contents($logFile));
    }

    public function test_temp_directory_is_isolated_per_test(): void
    {
        // Each test run gets a fresh temp directory
        $this->assertDirectoryExists($this->tempDir);

        // Previous temp dir from setUp is clean
        $files = scandir($this->tempDir);
        $this->assertCount(4, $files); // ., .., logs, cache
    }
}
```

**Expected**: Files are written and cleaned up correctly; each test is isolated.

**Pitfalls**:
- Always use `uniqid()` to avoid temp directory collisions in parallel runs
- The `tearDown()` cleanup must be robust — wrap with `is_dir()` checks
- Never hardcode temp paths; use `sys_get_temp_dir()`

---

### Recipe 3: Data Provider Pattern

**When to use**: You need to run the same test logic with multiple input/output combinations.

```php
<?php

namespace DGLab\Tests\Unit\Services;

use DGLab\Tests\TestCase;
use DGLab\Services\Validation\InputValidator;

class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InputValidator();
    }

    /**
     * @dataProvider provideValidEmails
     */
    public function test_it_validates_email_addresses(string $email, bool $expected): void
    {
        $result = $this->validator->validateEmail($email);
        $this->assertEquals($expected, $result);
    }

    public static function provideValidEmails(): array
    {
        return [
            'simple email'           => ['user@example.com', true],
            'with plus addressing'   => ['user+tag@example.com', true],
            'with subdomain'         => ['user@sub.example.com', true],
            'missing @'             => ['userexample.com', false],
            'missing domain'        => ['user@', false],
            'double dots'           => ['user@example..com', false],
            'empty string'          => ['', false],
            'special characters'    => ['user.name@example.com', true],
            'ip domain'             => ['user@[192.168.1.1]', true],
            'long tld'              => ['user@example.museum', true],
        ];
    }

    /**
     * @dataProvider provideEdgeCases
     */
    public function test_it_handles_edge_cases(string $input, array $rules, bool $expected): void
    {
        $result = $this->validator->validate($input, $rules);
        $this->assertEquals($expected, $result['valid']);
    }

    public static function provideEdgeCases(): array
    {
        return [
            'null byte injection' => [
                "test\0user",
                ['required', 'string', 'max:255'],
                false,
            ],
            'unicode overflow' => [
                str_repeat('あ', 256),
                ['required', 'string', 'max:255'],
                false,
            ],
            'exact boundary' => [
                str_repeat('a', 255),
                ['required', 'string', 'max:255'],
                true,
            ],
        ];
    }
}
```

**Expected**: Each data set runs as an independent test with clear naming (the array key becomes the test name suffix).

**Pitfalls**:
- Data providers must be `public static` methods
- Array keys become test names — use descriptive keys for failed test identification
- Data providers are called before `setUp()` — don't rely on instance state

---

### Recipe 4: Custom Assertion Pattern

**When to use**: You frequently assert the same complex conditions across multiple tests.

```php
<?php

namespace DGLab\Tests\Concerns;

use DGLab\Core\Response;

trait MakesApiAssertions
{
    protected function assertJsonResponse(Response $response, int $expectedStatus = 200): array
    {
        $this->assertEquals(
            $expectedStatus,
            $response->getStatusCode(),
            "Expected HTTP status {$expectedStatus}"
        );

        $contentType = $response->getHeader('Content-Type');
        $this->assertStringContainsString('application/json', $contentType);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data, 'Response is not valid JSON');

        return $data;
    }

    protected function assertApiSuccess(Response $response, string $message = null): array
    {
        $data = $this->assertJsonResponse($response);

        $this->assertTrue($data['success'] ?? false, 'API response indicates failure');

        if ($message) {
            $this->assertEquals($message, $data['message'] ?? '');
        }

        return $data['data'] ?? $data;
    }

    protected function assertApiError(Response $response, int $expectedStatus = 400): array
    {
        $data = $this->assertJsonResponse($response, $expectedStatus);

        $this->assertArrayHasKey('error', $data, 'API error response missing error key');
        $this->assertArrayHasKey('message', $data, 'API error response missing message');

        return $data;
    }

    protected function assertValidationError(Response $response, string $field = null): array
    {
        $data = $this->assertApiError($response, 422);

        $this->assertArrayHasKey('errors', $data, 'Validation response missing errors');

        if ($field) {
            $this->assertArrayHasKey($field, $data['errors'], "Validation error for '{$field}' not found");
        }

        return $data;
    }

    protected function assertPaginationStructure(array $data): void
    {
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('current_page', $data['meta']);
        $this->assertArrayHasKey('last_page', $data['meta']);
        $this->assertArrayHasKey('per_page', $data['meta']);
        $this->assertArrayHasKey('total', $data['meta']);
    }
}

// Usage in test:
class UserControllerTest extends TestCase
{
    use MakesApiAssertions;

    public function test_it_returns_user_list(): void
    {
        $response = $this->call('GET', '/api/v1/users');
        $data = $this->assertApiSuccess($response);
        $this->assertIsArray($data);
    }

    public function test_it_validates_required_fields(): void
    {
        $response = $this->call('POST', '/api/v1/users', []);
        $data = $this->assertValidationError($response, 'email');
        $this->assertEquals('The email field is required.', $data['errors']['email'][0]);
    }
}
```

**Expected**: Reusable custom assertions that make tests more readable and maintainable.

**Pitfalls**:
- Keep custom assertions focused on one concern (don't mix API + DOM assertions)
- Always include descriptive assertion messages for debugging
- Document the assertion trait with examples

---

## Category B: Async & Event Patterns (3 recipes)

---

### Recipe 5: Event Dispatching Test

**When to use**: You need to verify that events are (or are not) dispatched during an operation.

```php
<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\GenericEvent;

class EventDispatcherTest extends TestCase
{
    public function test_it_dispatches_events(): void
    {
        $this->fakeEvents();

        // Perform action that should dispatch an event
        event('user.registered', ['user_id' => 42, 'email' => 'test@example.com']);

        // Assert event was dispatched
        $this->assertEventDispatched('user.registered');
    }

    public function test_it_dispatches_events_with_data(): void
    {
        $this->fakeEvents();

        event('user.registered', ['user_id' => 42, 'email' => 'test@example.com']);

        $this->assertEventDispatched('user.registered', function ($event) {
            return $event->getData()['user_id'] === 42
                && $event->getData()['email'] === 'test@example.com';
        });
    }

    public function test_it_does_not_dispatch_for_skipped_actions(): void
    {
        $this->fakeEvents();

        // Action that shouldn't dispatch 'user.registered'
        // e.g., calling without required data
        event('user.registered', ['invalid' => true]);

        // Use callback to filter: only dispatch with valid user_id
        $this->assertEventNotDispatched('user.registered', function ($event) {
            return isset($event->getData()['user_id']);
        });
    }

    public function test_it_tracks_event_order(): void
    {
        $this->fakeEvents();

        event('process.started', ['id' => 1]);
        event('process.progress', ['id' => 1, 'pct' => 50]);
        event('process.completed', ['id' => 1]);

        $this->assertEventDispatched('process.started');
        $this->assertEventDispatched('process.progress');
        $this->assertEventDispatched('process.completed');
    }

    public function test_it_dispatches_to_multiple_listeners(): void
    {
        $this->fakeEvents();

        $dispatcher = $this->app->get(DispatcherInterface::class);
        $dispatcher->listen('test.event', function () { /* listener 1 */ });
        $dispatcher->listen('test.event', function () { /* listener 2 */ });

        event('test.event');

        // Both listeners should have been called
        $this->assertEventDispatched('test.event');
    }
}
```

**Expected**: Events are correctly tracked, filtered, and asserted.

**Pitfalls**:
- Always call `$this->fakeEvents()` before the action under test
- `assertEventNotDispatched` without callback asserts no instance was dispatched at all
- `EventFake` replaces the real dispatcher — remember to restore if testing the dispatcher itself

---

### Recipe 6: Async Listener Test

**When to use**: You need to verify that async/queued listeners are properly enqueued.

```php
<?php

namespace DGLab\Tests\Integration\Core;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Database\Connection;

class TestAsyncListener
{
    public function handle(EventInterface $event): void
    {
        // Simulated async handler
    }
}

class AsyncEventTest extends IntegrationTestCase
{
    public function test_it_queues_async_listeners(): void
    {
        $db = $this->app->get(Connection::class);
        $this->fakeEvents();

        // Register a class-based listener as async (4th param = async flag)
        $dispatcher = $this->app->get(\DGLab\Core\Contracts\DispatcherInterface::class);
        $dispatcher->listen('async.test', TestAsyncListener::class, 0, true);

        // Dispatch the event
        event('async.test', ['foo' => 'bar']);

        // Verify it was queued in the event_queue table
        $record = $db->selectOne(
            "SELECT * FROM event_queue WHERE event_alias = ?",
            ['async.test']
        );

        $this->assertNotNull($record);
        $this->assertEquals('pending', $record->status);
        $this->assertEquals('async.test', $record->event_alias);
    }

    public function test_it_stores_event_payload_in_queue(): void
    {
        $db = $this->app->get(Connection::class);
        $this->fakeEvents();

        $payload = ['user_id' => 42, 'action' => 'purchase', 'amount' => 29.99];

        $dispatcher = $this->app->get(\DGLab\Core\Contracts\DispatcherInterface::class);
        $dispatcher->listen('order.placed', TestAsyncListener::class, 0, true);

        event('order.placed', $payload);

        $record = $db->selectOne(
            "SELECT * FROM event_queue WHERE event_alias = ?",
            ['order.placed']
        );

        $storedPayload = json_decode($record->payload, true);
        $this->assertEquals($payload['user_id'], $storedPayload['user_id']);
        $this->assertEquals($payload['amount'], $storedPayload['amount']);
    }

    public function test_sync_listeners_are_not_queued(): void
    {
        $db = $this->app->get(Connection::class);
        $this->fakeEvents();

        $this->app->get(\DGLab\Core\Contracts\DispatcherInterface::class)
            ->listen('sync.test', function () { /* sync handler */ }, 0, false);

        event('sync.test');

        $record = $db->selectOne(
            "SELECT * FROM event_queue WHERE event_alias = ?",
            ['sync.test']
        );

        $this->assertNull($record, 'Sync listeners should not create queue entries');
    }
}
```

**Expected**: Async listeners create queue entries; sync listeners do not.

**Pitfalls**:
- Requires database setup from `IntegrationTestCase`
- Use `beginTransaction`/`rollBack` in `setUp`/`tearDown` to avoid test pollution
- The 4th parameter to `listen()` controls async behavior

---

### Recipe 7: Event Payload Assertion

**When to use**: You need to verify the exact data passed with an event.

```php
<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;

class EventPayloadTest extends TestCase
{
    public function test_event_contains_expected_payload(): void
    {
        $this->fakeEvents();

        $userData = [
            'id' => 42,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'roles' => ['admin', 'editor'],
        ];

        event('user.created', $userData);

        $this->assertEventDispatched('user.created', function ($event) use ($userData) {
            $data = $event->getData();

            // Exact match on specific fields
            return $data['id'] === $userData['id']
                && $data['name'] === $userData['name']
                && $data['email'] === $userData['email'];
        });
    }

    public function test_event_excludes_sensitive_data(): void
    {
        $this->fakeEvents();

        event('user.created', [
            'id' => 42,
            'name' => 'Alice',
            'password' => 'supersecret',
            'credit_card' => '4111-1111-1111-1111',
        ]);

        $this->assertEventDispatched('user.created', function ($event) {
            $data = $event->getData();

            // Sensitive fields should NOT be in the event payload
            return !isset($data['password'])
                && !isset($data['credit_card']);
        });
    }

    public function test_event_payload_with_collections(): void
    {
        $this->fakeEvents();

        $items = [
            ['id' => 1, 'name' => 'Item A', 'price' => 10.99],
            ['id' => 2, 'name' => 'Item B', 'price' => 24.99],
            ['id' => 3, 'name' => 'Item C', 'price' => 5.99],
        ];

        event('order.created', [
            'order_id' => 'ORD-2024-001',
            'items' => $items,
            'total' => 41.97,
        ]);

        $this->assertEventDispatched('order.created', function ($event) {
            $data = $event->getData();

            return $data['order_id'] === 'ORD-2024-001'
                && count($data['items']) === 3
                && abs($data['total'] - 41.97) < 0.001;
        });
    }

    public function test_multiple_events_have_correct_payloads(): void
    {
        $this->fakeEvents();

        // Simulate a multi-step process
        event('workflow.started', ['workflow_id' => 'WF-001', 'step' => 'init']);
        event('workflow.progress', ['workflow_id' => 'WF-001', 'step' => 'validate', 'pct' => 25]);
        event('workflow.progress', ['workflow_id' => 'WF-001', 'step' => 'process', 'pct' => 75]);
        event('workflow.completed', ['workflow_id' => 'WF-001', 'step' => 'done', 'pct' => 100]);

        // Verify each event's payload independently
        $this->assertEventDispatched('workflow.started', fn($e) => $e->getData()['step'] === 'init');
        $this->assertEventDispatched('workflow.progress', fn($e) => $e->getData()['pct'] === 25);
        $this->assertEventDispatched('workflow.progress', fn($e) => $e->getData()['pct'] === 75);
        $this->assertEventDispatched('workflow.completed', fn($e) => $e->getData()['pct'] === 100);
    }
}
```

**Expected**: Event payloads are verified for content, sensitive data exclusion, and multi-step correctness.

**Pitfalls**:
- Callbacks receive the event object — use `$event->getData()` to access payload
- For numeric comparisons, use a tolerance (`abs($a - $b) < 0.001`) to avoid floating point issues
- Each `assertEventDispatched` can have its own independent callback

---

## Category C: Database Patterns (3 recipes)

---

### Recipe 8: In-Memory SQLite Test

**When to use**: You need database operations in tests without external database setup.

```php
<?php

namespace DGLab\Tests\Integration\Services;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Database\Connection;
use DGLab\Database\Model;

class UserRepositoryTest extends IntegrationTestCase
{
    protected bool $runMigrations = true;

    public function test_it_inserts_and_retrieves_records(): void
    {
        $db = $this->app->get(Connection::class);

        $db->insert(
            "INSERT INTO users (name, email, created_at) VALUES (?, ?, ?)",
            ['Alice', 'alice@example.com', date('c')]
        );

        $user = $db->selectOne("SELECT * FROM users WHERE email = ?", ['alice@example.com']);

        $this->assertNotNull($user);
        $this->assertEquals('Alice', $user->name);
        $this->assertEquals('alice@example.com', $user->email);
    }

    public function test_it_enforces_unique_constraints(): void
    {
        $db = $this->app->get(Connection::class);

        $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['Alice', 'alice@test.com']);

        $this->expectException(\PDOException::class);
        $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['Bob', 'alice@test.com']);
    }

    public function test_transaction_rollback_isolates_tests(): void
    {
        $db = $this->app->get(Connection::class);

        $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['Charlie', 'charlie@test.com']);

        // This insert happens inside the test's transaction
        // It will be rolled back by tearDown()
        $count = $db->selectValue("SELECT COUNT(*) FROM users");
        $this->assertEquals(1, $count);

        // Next test starts with a clean slate due to rollback
    }
}
```

**Expected**: Database operations work against in-memory SQLite; each test is isolated via transaction rollback.

**Pitfalls**:
- In-memory SQLite means data is lost after connection closes — use temp file for cross-connection tests
- SQLite doesn't support all MySQL/PostgreSQL features (e.g., `FULL OUTER JOIN`, `ALTER TABLE ... DROP COLUMN`)
- Set `$runMigrations = true` in `setUp` to ensure schema exists

---

### Recipe 9: Migration Verification

**When to use**: You need to verify that database migrations run correctly and produce the expected schema.

```php
<?php

namespace DGLab\Tests\Integration\Database;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Database\Connection;
use DGLab\Database\Migration;

class MigrationTest extends IntegrationTestCase
{
    protected bool $runMigrations = false; // We'll run them manually

    public function test_migrations_run_without_errors(): void
    {
        $db = $this->app->get(Connection::class);
        $migration = new Migration(
            $db,
            $this->app->getBasePath() . '/database/migrations'
        );

        $ran = $migration->run();

        // Verify migrations were executed
        $this->assertNotEmpty($ran);
        $this->assertIsArray($ran);

        // Verify migration tracking table exists
        $tables = $db->select("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_map(fn($t) => $t->name, $tables);
        $this->assertContains('migrations', $tableNames);
    }

    public function test_idempotent_migration(): void
    {
        $db = $this->app->get(Connection::class);
        $migration = new Migration(
            $db,
            $this->app->getBasePath() . '/database/migrations'
        );

        // Run migrations once
        $firstRun = $migration->run();
        $this->assertNotEmpty($firstRun);

        // Run migrations again — should be no-op
        $secondRun = $migration->run();
        $this->assertEmpty($secondRun, 'Migrations should not re-run');
    }

    public function test_expected_tables_exist_after_migration(): void
    {
        $db = $this->app->get(Connection::class);
        $migration = new Migration($db, $this->app->getBasePath() . '/database/migrations');
        $migration->run();

        $expectedTables = [
            'users',
            'tenants',
            'permissions',
            'roles',
            'migrations',
            'event_queue',
            'audit_logs',
        ];

        $tables = $db->select("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_map(fn($t) => $t->name, $tables);

        foreach ($expectedTables as $table) {
            $this->assertContains(
                $table,
                $tableNames,
                "Expected table '{$table}' not found after migration"
            );
        }
    }

    public function test_expected_columns_exist_in_users_table(): void
    {
        $db = $this->app->get(Connection::class);
        $migration = new Migration($db, $this->app->getBasePath() . '/database/migrations');
        $migration->run();

        $columns = $db->select("PRAGMA table_info(users)");
        $columnNames = array_map(fn($c) => $c->name, $columns);

        $expectedColumns = ['id', 'name', 'email', 'password', 'tenant_id', 'created_at', 'updated_at'];

        foreach ($expectedColumns as $col) {
            $this->assertContains($col, $columnNames, "Expected column '{$col}' not found in users table");
        }
    }
}
```

**Expected**: Migrations run, are idempotent, and produce the expected schema.

**Pitfalls**:
- Set `$runMigrations = false` when manually controlling migration execution
- SQLite's `PRAGMA table_info` is the portable way to check column existence
- Verify both forward (first run) and idempotent (second run) behavior

---

### Recipe 10: Transaction Rollback Pattern

**When to use**: You need test isolation for database tests — each test starts with a clean slate.

```php
<?php

namespace DGLab\Tests\Integration\Services;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Database\Connection;
use DGLab\Database\Model;

class DatabaseIsolationTest extends IntegrationTestCase
{
    protected bool $runMigrations = true;

    public function test_first_test_inserts_data(): void
    {
        $db = $this->app->get(Connection::class);

        $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['TestA', 'a@test.com']);

        $count = $db->selectValue("SELECT COUNT(*) FROM users");
        $this->assertEquals(1, $count);
    }

    public function test_second_test_starts_clean(): void
    {
        $db = $this->app->get(Connection::class);

        // Despite the first test inserting data, this test starts with 0
        $count = $db->selectValue("SELECT COUNT(*) FROM users");
        $this->assertEquals(0, $count, 'Database should be clean for each test');
    }

    public function test_concurrent_isolation_simulation(): void
    {
        $db = $this->app->get(Connection::class);

        // Start a nested transaction
        $db->beginTransaction();

        $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['Nested', 'nested@test.com']);

        // Data visible inside the transaction
        $count = $db->selectValue("SELECT COUNT(*) FROM users");
        $this->assertEquals(1, $count);

        // Rollback nested transaction
        $db->rollBack();

        // Data no longer visible
        $count = $db->selectValue("SELECT COUNT(*) FROM users");
        $this->assertEquals(0, $count, 'Rollback should remove nested insert');
    }

    public function test_transaction_nesting(): void
    {
        $db = $this->app->get(Connection::class);

        // IntegrationTestCase starts a transaction in setUp
        // We can nest additional savepoints

        $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['Parent', 'parent@test.com']);

        $db->beginTransaction(); // savepoint
        $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['Child', 'child@test.com']);
        $db->rollBack(); // rollback to savepoint

        // Child insert is rolled back, parent insert remains
        $users = $db->select("SELECT email FROM users ORDER BY email");
        $emails = array_map(fn($u) => $u->email, $users);
        $this->assertContains('parent@test.com', $emails);
        $this->assertNotContains('child@test.com', $emails);
    }
}
```

**Expected**: Each test starts with a clean database state due to transaction rollback.

**Pitfalls**:
- `IntegrationTestCase` calls `beginTransaction()` in `setUp` and `rollBack()` in `tearDown`
- Nested transactions use SQLite savepoints — they work but have limitations
- Don't use `TRUNCATE` tables — transaction rollback is cleaner and faster

---

## Category D: CLI & Command Patterns (3 recipes)

---

### Recipe 11: CLI Command Output Test

**When to use**: You need to test that CLI commands produce correct output and exit codes.

```php
<?php

namespace DGLab\Tests\Unit\Cli;

use DGLab\Tests\TestCase;

class CommandOutputTest extends TestCase
{
    public function test_command_outputs_help_text(): void
    {
        $output = $this->runCommand('greet', ['help']);

        $this->assertStringContainsString('DGLab Greeting CLI', $output);
        $this->assertStringContainsString('greet <name>', $output);
    }

    public function test_command_greets_user(): void
    {
        $output = $this->runCommand('greet', ['greet', 'Alice']);

        $this->assertStringContainsString('Hello, Alice!', $output);
    }

    public function test_command_defaults_to_world(): void
    {
        $output = $this->runCommand('greet', ['greet']);

        $this->assertStringContainsString('Hello, World!', $output);
    }

    public function test_command_exits_with_error_for_unknown_command(): void
    {
        $exitCode = 0;
        $output = $this->runCommand('greet', ['nonexistent'], $exitCode);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('Unknown command', $output);
    }

    public function test_verbose_flag_shows_info(): void
    {
        $output = $this->runCommand('greet', ['greet', 'Bob', '--verbose']);

        $this->assertStringContainsString('[INFO]', $output);
        $this->assertStringContainsString('Hello, Bob!', $output);
    }

    private function runCommand(string $script, array $args, &$exitCode = null): string
    {
        $scriptPath = __DIR__ . '/../../cli/' . $script . '.php';
        $argStr = implode(' ', array_map('escapeshellarg', $args));

        $output = [];
        $cmd = sprintf('php %s %s 2>&1', escapeshellarg($scriptPath), $argStr);
        exec($cmd, $output, $exitCode);

        return implode("\n", $output);
    }
}
```

**Expected**: CLI commands produce expected output and correct exit codes for various inputs.

**Pitfalls**:
- Use `2>&1` to capture stderr in output
- Test both success and failure exit codes
- ANSI color codes may be present in output — strip them with `preg_replace('/\033\[[0-9;]*m/', '', $output)`

---

### Recipe 12: Scaffolded File Test

**When to use**: You need to verify that scaffolding commands generate correct file content.

```php
<?php

namespace DGLab\Tests\Unit\Cli;

use DGLab\Tests\TestCase;

class ScaffoldTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = sys_get_temp_dir() . '/dg_scaffold_' . uniqid();
        mkdir($this->outputDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->outputDir && is_dir($this->outputDir)) {
            array_map('unlink', glob($this->outputDir . '/*'));
            rmdir($this->outputDir);
        }
        parent::tearDown();
    }

    public function test_scaffold_creates_unit_test_file(): void
    {
        $path = $this->outputDir . '/UserServiceTest.php';
        $className = 'UserServiceTest';
        $namespace = 'DGLab\\Tests\\Unit';

        $content = $this->generateTestTemplate($className, $namespace, 'TestCase');
        file_put_contents($path, $content);

        // Verify file exists
        $this->assertFileExists($path);

        // Verify correct class name
        $this->assertStringContainsString('class UserServiceTest extends TestCase', $content);
        $this->assertStringContainsString('namespace DGLab\\Tests\\Unit;', $content);

        // Verify PHP syntax is valid
        $this->assertTrue($this->isValidPHP($path), 'Generated file is not valid PHP');
    }

    public function test_scaffold_creates_integration_test_with_database_support(): void
    {
        $content = $this->generateTestTemplate('OrderIntegrationTest', 'DGLab\\Tests\\Integration', 'IntegrationTestCase');

        $this->assertStringContainsString('IntegrationTestCase', $content);
        $this->assertStringContainsString('setUp', $content);
        $this->assertStringContainsString('parent::setUp', $content);
    }

    public function test_scaffold_prevents_overwrite_without_force(): void
    {
        $path = $this->outputDir . '/ExistingTest.php';
        file_put_contents($path, 'original content');

        // Without --force, should not overwrite
        $shouldOverwrite = false; // This would come from CLI flag check
        $this->assertFalse($shouldOverwrite, 'Should not overwrite without --force');
        $this->assertEquals('original content', file_get_contents($path));
    }

    public function test_scaffold_respects_custom_namespace(): void
    {
        $content = $this->generateTestTemplate('CustomTest', 'App\\Tests\\Custom', 'TestCase');

        $this->assertStringContainsString('namespace App\\Tests\\Custom;', $content);
    }

    private function generateTestTemplate(string $class, string $namespace, string $baseClass): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use DGLab\\Tests\\{$baseClass};

class {$class} extends {$baseClass}
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_something(): void
    {
        \$this->assertTrue(true);
    }
}

PHP;
    }

    private function isValidPHP(string $path): bool
    {
        $output = [];
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
        return $exitCode === 0;
    }
}
```

**Expected**: Scaffolded files are syntactically valid, use correct namespaces, and respect overwrite protection.

**Pitfalls**:
- Always use `php -l` to verify generated PHP syntax
- Test both `--force` (overwrite) and default (no overwrite) behavior
- Verify namespace matches the test type convention

---

### Recipe 13: Argument Parsing Test

**When to use**: You need to verify that CLI argument parsing handles various input combinations correctly.

```php
<?php

namespace DGLab\Tests\Unit\Cli;

use DGLab\Tests\TestCase;

class ArgumentParsingTest extends TestCase
{
    public function test_it_parses_positional_arguments(): void
    {
        $argv = ['script.php', 'command', 'arg1', 'arg2', '--flag'];
        $this->assertEquals('command', $argv[1] ?? 'help');
        $this->assertEquals('arg1', $argv[2] ?? null);
        $this->assertEquals('arg2', $argv[3] ?? null);
    }

    public function test_it_defaults_missing_command(): void
    {
        $argv = ['script.php'];
        $this->assertEquals('help', $argv[1] ?? 'help');
    }

    public function test_it_detects_boolean_flags(): void
    {
        $argv = ['script.php', 'run', '--verbose', '--dry-run'];

        $this->assertTrue(in_array('--verbose', $argv));
        $this->assertTrue(in_array('--dry-run', $argv));
        $this->assertFalse(in_array('--force', $argv));
    }

    public function test_it_parses_named_option_equals_syntax(): void
    {
        $argv = ['script.php', 'run', '--group=auth', '--limit=50'];

        $options = [];
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
                [$name, $value] = explode('=', substr($arg, 2), 2);
                $options[$name] = $value;
            }
        }

        $this->assertEquals('auth', $options['group']);
        $this->assertEquals('50', $options['limit']);
    }

    public function test_it_parses_named_option_space_syntax(): void
    {
        $argv = ['script.php', 'run', '--format', 'json', '--name', 'Alice'];

        $options = [];
        for ($i = 0; $i < count($argv); $i++) {
            if (str_starts_with($argv[$i], '--') && !str_contains($argv[$i], '=')) {
                $name = substr($argv[$i], 2);
                if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    $options[$name] = $argv[$i + 1];
                    $i++; // skip value
                } else {
                    $options[$name] = true; // boolean flag
                }
            }
        }

        $this->assertEquals('json', $options['format']);
        $this->assertEquals('Alice', $options['name']);
    }

    /**
     * @dataProvider provideMixedArguments
     */
    public function test_it_handles_mixed_arguments(array $argv, string $expectedCommand, array $expectedPositional, array $expectedFlags): void
    {
        $command = $argv[1] ?? 'help';
        $this->assertEquals($expectedCommand, $command);

        $positional = $this->getPositionalArgs($argv);
        $this->assertEquals($expectedPositional, $positional);

        foreach ($expectedFlags as $flag => $present) {
            $this->assertEquals($present, in_array($flag, $argv), "Flag {$flag} should be " . ($present ? 'present' : 'absent'));
        }
    }

    public static function provideMixedArguments(): array
    {
        return [
            'simple' => [
                ['script.php', 'run'],
                'run', [], [],
            ],
            'with positional' => [
                ['script.php', 'make:test', 'AuthService'],
                'make:test', ['AuthService'], [],
            ],
            'with flags' => [
                ['script.php', 'run', '--unit', '--stop-on-failure'],
                'run', [], ['--unit' => true, '--stop-on-failure' => true],
            ],
            'with named option' => [
                ['script.php', 'run', '--group', 'auth', '--filter=login'],
                'run', [], ['--group' => false], // --group followed by value is not a flag
            ],
            'help command' => [
                ['script.php', 'help'],
                'help', [], [],
            ],
        ];
    }

    private function getPositionalArgs(array $argv): array
    {
        $args = array_slice($argv, 2);
        $filtered = [];
        for ($i = 0; $i < count($args); $i++) {
            if (str_starts_with($args[$i], '-')) continue;
            $filtered[] = $args[$i];
        }
        return $filtered;
    }
}
```

**Expected**: Argument parsing correctly handles positional args, flags, named options (both `=` and space syntax), and edge cases like mixed arguments.

**Pitfalls**:
- Test both `--name=value` and `--name value` syntaxes
- Handle the case where an option value starts with `-` (e.g., `--name -value`)
- Ensure boolean flags don't accidentally consume the next argument

---

## Category E: HTTP & API Patterns (3 recipes)

---

### Recipe 14: Request/Response Cycle Test

**When to use**: You need to test the full request/response lifecycle through the framework.

```php
<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Router;

class RequestResponseTest extends TestCase
{
    public function test_it_creates_a_request(): void
    {
        $request = new Request(
            ['param1' => 'value1'],
            ['form_field' => 'data'],
            [],
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/users']
        );

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/users', $request->getUri());
        $this->assertEquals('value1', $request->get('param1'));
    }

    public function test_it_sends_a_request_through_router(): void
    {
        $this->app->singleton(Router::class, function ($app) {
            return new Router($app);
        });

        $request = $this->createRequest('GET', '/health');
        $this->app->set(Request::class, $request);

        $response = $this->call('GET', '/health');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertNotEmpty($response->getContent());
    }

    public function test_it_returns_json_response(): void
    {
        $response = $this->call('GET', '/api/v1/users');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type'));
    }

    public function test_it_handles_query_parameters(): void
    {
        $request = $this->createRequest('GET', '/api/v1/users?page=2&per_page=20');

        $uri = $request->getUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=20', $uri);
    }

    public function test_it_handles_post_data(): void
    {
        $response = $this->call('POST', '/api/v1/users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_it_returns_404_for_unknown_routes(): void
    {
        $response = $this->call('GET', '/nonexistent-route');

        $this->assertEquals(404, $response->getStatusCode());
    }
}
```

**Expected**: Requests are properly created, routed, and responses returned with correct status codes.

**Pitfalls**:
- Use `$this->createRequest()` and `$this->call()` from `TestCase` for consistency
- Test both happy paths and error paths (404, 422, 500)
- Query parameters vs POST body require different handling in `createRequest()`

---

### Recipe 15: Route Resolution Test

**When to use**: You need to verify that routes match correctly and extract parameters.

```php
<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;
use DGLab\Core\Router;

class RouteResolutionTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->app);
    }

    public function test_it_matches_static_routes(): void
    {
        $this->router->get('/health', 'HealthController@check');

        $result = $this->router->match('GET', '/health');
        $this->assertNotNull($result);
        $this->assertEquals('HealthController@check', $result['handler']);
    }

    public function test_it_matches_parameterized_routes(): void
    {
        $this->router->get('/users/{id}', 'UserController@show');

        $result = $this->router->match('GET', '/users/42');

        $this->assertNotNull($result);
        $this->assertEquals('UserController@show', $result['handler']);
        $this->assertEquals(['id' => '42'], $result['params']);
    }

    public function test_it_matches_nested_parameterized_routes(): void
    {
        $this->router->get('/users/{userId}/posts/{postId}', 'PostController@show');

        $result = $this->router->match('GET', '/users/42/posts/7');

        $this->assertEquals(['userId' => '42', 'postId' => '7'], $result['params']);
    }

    public function test_it_returns_null_for_unmatched_routes(): void
    {
        $this->router->get('/users', 'UserController@index');

        $result = $this->router->match('GET', '/nonexistent');
        $this->assertNull($result);
    }

    public function test_it_matches_correct_method_only(): void
    {
        $this->router->post('/users', 'UserController@store');

        $getResult = $this->router->match('GET', '/users');
        $postResult = $this->router->match('POST', '/users');

        $this->assertNull($getResult);
        $this->assertNotNull($postResult);
    }

    public function test_it_applies_middleware_to_routes(): void
    {
        $this->router->get('/admin', 'AdminController@index', ['auth', 'admin']);

        $result = $this->router->match('GET', '/admin');

        $this->assertNotNull($result);
        $this->assertEquals(['auth', 'admin'], $result['middleware']);
    }

    public function test_it_resolves_named_routes(): void
    {
        $this->router->get('/users/{id}', 'UserController@show')->name('users.show');

        $uri = $this->router->route('users.show', ['id' => 42]);
        $this->assertEquals('/users/42', $uri);
    }
}
```

**Expected**: Routes match correctly with parameters, method filtering, middleware, and named route resolution.

**Pitfalls**:
- Test route priority if multiple routes could match
- Parameter order matters in nested routes
- Named routes require the `name()` fluent method

---

### Recipe 16: API Endpoint Integration Test

**When to use**: You need to test the full request-to-response pipeline including middleware, validation, and database.

```php
<?php

namespace DGLab\Tests\Integration\Api;

use DGLab\Tests\IntegrationTestCase;

class UserApiIntegrationTest extends IntegrationTestCase
{
    protected bool $runMigrations = true;

    public function test_create_user_endpoint(): void
    {
        $response = $this->call('POST', '/api/v1/users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'SecurePass123!',
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Alice', $data['data']['name']);
        $this->assertArrayNotHasKey('password', $data['data']);
    }

    public function test_create_user_validates_input(): void
    {
        $response = $this->call('POST', '/api/v1/users', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('name', $data['errors']);
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function test_list_users_paginates(): void
    {
        // Seed some users
        for ($i = 1; $i <= 25; $i++) {
            $this->db->insert(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                ["User {$i}", "user{$i}@example.com"]
            );
        }

        // Get page 1
        $response = $this->call('GET', '/api/v1/users?page=1&per_page=10');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(10, $data['data']);

        // Get page 2
        $response = $this->call('GET', '/api/v1/users?page=2&per_page=10');
        $data = json_decode($response->getContent(), true);

        $this->assertCount(10, $data['data']);
        $this->assertEquals(11, $data['data'][0]['id']); // 1-indexed in SQLite
    }

    public function test_show_user_returns_details(): void
    {
        $this->db->insert(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['Bob', 'bob@example.com']
        );
        $userId = $this->db->lastInsertId();

        $response = $this->call('GET', "/api/v1/users/{$userId}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Bob', $data['data']['name']);
    }

    public function test_delete_user_removes_resource(): void
    {
        $this->db->insert(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            ['Charlie', 'charlie@example.com']
        );
        $userId = $this->db->lastInsertId();

        $response = $this->call('DELETE', "/api/v1/users/{$userId}");

        $this->assertEquals(200, $response->getStatusCode());

        // Verify deletion
        $user = $this->db->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertNull($user);
    }

    public function test_authenticated_endpoint_requires_token(): void
    {
        $response = $this->call('GET', '/api/v1/admin/users');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_authenticated_endpoint_accepts_valid_token(): void
    {
        // Generate a token
        $token = $this->generateToken(['user_id' => 1, 'role' => 'admin']);

        $response = $this->call('GET', '/api/v1/admin/users', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function generateToken(array $payload): string
    {
        // Simplified — use actual JWT service in real tests
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = base64_encode(json_encode($payload));
        $sig = base64_encode(hash_hmac('sha256', "{$header}.{$body}", 'test-secret', true));
        return "{$header}.{$body}.{$sig}";
    }
}
```

**Expected**: Full API lifecycle (CRUD) works correctly with validation, pagination, authentication.

**Pitfalls**:
- Each test starts with a clean database via transaction rollback
- Seed data within the test that needs it
- Test authentication by providing `Authorization` header via the 4th parameter of `call()`

---

## Category F: Multi-Tenancy & Security Patterns (4 recipes)

---

### Recipe 17: Tenant Isolation Test

**When to use**: You need to verify that multi-tenant data isolation is enforced.

```php
<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Database\Connection;

class TenantIsolationTest extends IntegrationTestCase
{
    protected bool $runMigrations = true;

    private int $tenantA;
    private int $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two tenants
        $this->tenantA = $this->createTenant('Tenant A');
        $this->tenantB = $this->createTenant('Tenant B');
    }

    private function createTenant(string $name): int
    {
        $this->db->insert("INSERT INTO tenants (name, slug) VALUES (?, ?)", [$name, strtolower(str_replace(' ', '_', $name))]);
        return $this->db->lastInsertId();
    }

    private function createUserForTenant(int $tenantId, string $email): int
    {
        $this->db->insert(
            "INSERT INTO users (name, email, tenant_id) VALUES (?, ?, ?)",
            ["User {$email}", $email, $tenantId]
        );
        return $this->db->lastInsertId();
    }

    public function test_tenant_a_cannot_access_tenant_b_data(): void
    {
        $userA = $this->createUserForTenant($this->tenantA, 'a@test.com');
        $userB = $this->createUserForTenant($this->tenantB, 'b@test.com');

        // Query as Tenant A — should only see Tenant A's user
        $userAQuery = $this->db->selectOne(
            "SELECT * FROM users WHERE id = ? AND tenant_id = ?",
            [$userB, $this->tenantA]
        );

        $this->assertNull($userAQuery, 'Tenant A should not see Tenant B user');
    }

    public function test_tenant_scoped_count_is_correct(): void
    {
        $this->createUserForTenant($this->tenantA, 'a1@test.com');
        $this->createUserForTenant($this->tenantA, 'a2@test.com');
        $this->createUserForTenant($this->tenantB, 'b1@test.com');

        $countA = $this->db->selectValue(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ?",
            [$this->tenantA]
        );
        $countB = $this->db->selectValue(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ?",
            [$this->tenantB]
        );

        $this->assertEquals(2, $countA, 'Tenant A should have 2 users');
        $this->assertEquals(1, $countB, 'Tenant B should have 1 user');
    }

    public function test_concurrent_tenant_operations_are_isolated(): void
    {
        // Simulate two requests in parallel by sequentially switching tenant context
        $this->switchTenant($this->tenantA);
        $userA = $this->createUserForTenant($this->tenantA, 'concurrent_a@test.com');

        $this->switchTenant($this->tenantB);
        $userB = $this->createUserForTenant($this->tenantB, 'concurrent_b@test.com');

        // Verify no cross-contamination
        $countAll = $this->db->selectValue("SELECT COUNT(*) FROM users WHERE email LIKE 'concurrent_%'");
        $this->assertEquals(2, $countAll);

        // Each user belongs to correct tenant
        $actualA = $this->db->selectOne("SELECT tenant_id FROM users WHERE id = ?", [$userA]);
        $actualB = $this->db->selectOne("SELECT tenant_id FROM users WHERE id = ?", [$userB]);

        $this->assertEquals($this->tenantA, $actualA->tenant_id);
        $this->assertEquals($this->tenantB, $actualB->tenant_id);
    }

    private function switchTenant(int $tenantId): void
    {
        // Set tenant context — adjust based on actual implementation
        $this->app->setConfig('tenant.current_id', $tenantId);
    }
}
```

**Expected**: Cross-tenant data access is blocked; counts are scoped correctly.

**Pitfalls**:
- Every query should include `tenant_id` in the WHERE clause
- Test both directions (A→B and B→A)
- Verify that queries WITHOUT `tenant_id` filter are caught by middleware/framework

---

### Recipe 18: Rate Limiting Test

**When to use**: You need to verify that rate limits are enforced correctly.

```php
<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\RateLimiter;
use DGLab\Core\Cache;

class RateLimiterTest extends IntegrationTestCase
{
    private RateLimiter $rateLimiter;
    private Cache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Cache(sys_get_temp_dir() . '/dg_test_cache_' . uniqid());
        $this->rateLimiter = new RateLimiter($this->cache);
    }

    public function test_it_allows_requests_within_limit(): void
    {
        $key = 'test:user:42';

        for ($i = 0; $i < 10; $i++) {
            $result = $this->rateLimiter->attempt($key, 10, 60); // 10 requests per 60 seconds
            $this->assertTrue($result['allowed']);
            $this->assertEquals(10 - $i - 1, $result['remaining']);
        }
    }

    public function test_it_blocks_requests_exceeding_limit(): void
    {
        $key = 'test:user:99';
        $maxAttempts = 5;

        // Use up all attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->attempt($key, $maxAttempts, 60);
        }

        // Next attempt should be blocked
        $result = $this->rateLimiter->attempt($key, $maxAttempts, 60);
        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['remaining']);
        $this->assertArrayHasKey('retry_after', $result);
    }

    public function test_rate_limit_resets_after_window(): void
    {
        $key = 'test:reset:test';
        $maxAttempts = 3;

        // Exhaust limit
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->attempt($key, $maxAttempts, 1); // 1 second window
        }

        // Verify blocked
        $this->assertFalse($this->rateLimiter->attempt($key, $maxAttempts, 1)['allowed']);

        // Wait for window to expire
        sleep(1);

        // Should be allowed again
        $result = $this->rateLimiter->attempt($key, $maxAttempts, 1);
        $this->assertTrue($result['allowed']);
    }

    public function test_different_keys_have_independent_limits(): void
    {
        $keyA = 'user:1';
        $keyB = 'user:2';

        // Exhaust key A
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->attempt($keyA, 5, 60);
        }

        // Key B should still be allowed
        $resultB = $this->rateLimiter->attempt($keyB, 5, 60);
        $this->assertTrue($resultB['allowed']);
        $this->assertEquals(4, $resultB['remaining']);

        // Key A should be blocked
        $resultA = $this->rateLimiter->attempt($keyA, 5, 60);
        $this->assertFalse($resultA['allowed']);
    }

    public function test_rate_limit_headers_match_limits(): void
    {
        $key = 'test:headers';
        $maxAttempts = 10;

        $result = $this->rateLimiter->attempt($key, $maxAttempts, 60);

        $this->assertArrayHasKey('X-RateLimit-Limit', $result['headers']);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $result['headers']);
        $this->assertArrayHasKey('X-RateLimit-Reset', $result['headers']);

        $this->assertEquals($maxAttempts, $result['headers']['X-RateLimit-Limit']);
    }
}
```

**Expected**: Rate limits are enforced, reset after window expires, and are independent per key.

**Pitfalls**:
- Use a separate cache instance per test to avoid shared state
- Test with very short windows (1 second) when testing reset behavior
- Verify both the boolean `allowed` and numeric `remaining` values

---

### Recipe 19: Authentication/Authorization Test

**When to use**: You need to verify RBAC permissions are enforced for different roles.

```php
<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\KeyManagementService;

class RbacSecurityTest extends IntegrationTestCase
{
    protected bool $runMigrations = true;

    private JWTService $jwt;
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $keyManager = new KeyManagementService(sys_get_temp_dir() . '/keys');
        $this->jwt = new JWTService($keyManager);

        // Seed roles and permissions
        $this->seedRoles();
    }

    private function seedRoles(): void
    {
        $this->db->insert("INSERT INTO roles (name, slug) VALUES (?, ?)", ['Admin', 'admin']);
        $adminRoleId = $this->db->lastInsertId();
        $this->roles['admin'] = $adminRoleId;

        $this->db->insert("INSERT INTO roles (name, slug) VALUES (?, ?)", ['Editor', 'editor']);
        $editorRoleId = $this->db->lastInsertId();
        $this->roles['editor'] = $editorRoleId;

        $this->db->insert("INSERT INTO roles (name, slug) VALUES (?, ?)", ['Viewer', 'viewer']);
        $viewerRoleId = $this->db->lastInsertId();
        $this->roles['viewer'] = $viewerRoleId;

        // Seed permissions
        $permissions = [
            ['users.create', 'Create users'],
            ['users.edit', 'Edit users'],
            ['users.delete', 'Delete users'],
            ['users.view', 'View users'],
        ];

        foreach ($permissions as [$name, $desc]) {
            $this->db->insert("INSERT INTO permissions (name, description) VALUES (?, ?)", [$name, $desc]);
        }

        // Assign full permissions to admin
        $allPerms = $this->db->select("SELECT id FROM permissions");
        foreach ($allPerms as $p) {
            $this->db->insert("INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)", [$adminRoleId, $p->id]);
        }

        // Assign limited permissions to editor
        $editorPerms = $this->db->select("SELECT id FROM permissions WHERE name IN ('users.create', 'users.edit', 'users.view')");
        foreach ($editorPerms as $p) {
            $this->db->insert("INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)", [$editorRoleId, $p->id]);
        }

        // Assign view-only to viewer
        $this->db->insert(
            "INSERT INTO role_permission (role_id, permission_id) VALUES (?, (SELECT id FROM permissions WHERE name = 'users.view'))",
            [$viewerRoleId]
        );
    }

    public function test_admin_has_all_permissions(): void
    {
        $this->assertTrue($this->userHasPermission($this->roles['admin'], 'users.create'));
        $this->assertTrue($this->userHasPermission($this->roles['admin'], 'users.edit'));
        $this->assertTrue($this->userHasPermission($this->roles['admin'], 'users.delete'));
        $this->assertTrue($this->userHasPermission($this->roles['admin'], 'users.view'));
    }

    public function test_editor_cannot_delete_users(): void
    {
        $this->assertTrue($this->userHasPermission($this->roles['editor'], 'users.create'));
        $this->assertTrue($this->userHasPermission($this->roles['editor'], 'users.edit'));
        $this->assertFalse($this->userHasPermission($this->roles['editor'], 'users.delete'));
        $this->assertTrue($this->userHasPermission($this->roles['editor'], 'users.view'));
    }

    public function test_viewer_cannot_modify(): void
    {
        $this->assertFalse($this->userHasPermission($this->roles['viewer'], 'users.create'));
        $this->assertFalse($this->userHasPermission($this->roles['viewer'], 'users.edit'));
        $this->assertFalse($this->userHasPermission($this->roles['viewer'], 'users.delete'));
        $this->assertTrue($this->userHasPermission($this->roles['viewer'], 'users.view'));
    }

    public function test_endpoint_enforces_permissions(): void
    {
        // Generate JWT for viewer
        $token = $this->jwt->generateToken([
            'user_id' => 1,
            'role' => 'viewer',
            'permissions' => ['users.view'],
        ]);

        // Try to access admin endpoint
        $response = $this->call('DELETE', '/api/v1/users/1', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    private function userHasPermission(int $roleId, string $permissionName): bool
    {
        $result = $this->db->selectOne(
            "SELECT 1 FROM role_permission rp
             JOIN permissions p ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.name = ?",
            [$roleId, $permissionName]
        );
        return $result !== null;
    }
}
```

**Expected**: Each role has the correct set of permissions; endpoints enforce authorization.

**Pitfalls**:
- Seed roles/permissions in `setUp` (not `dataProvider`) to avoid stale state
- Test both positive (has permission) and negative (lacks permission) cases
- Verify HTTP 403 is returned for unauthorized endpoint access

---

### Recipe 20: Session Lifecycle Test

**When to use**: You need to verify session creation, validation, expiry, and refresh behavior.

```php
<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;

class SessionLifecycleTest extends IntegrationTestCase
{
    protected bool $runMigrations = true;

    public function test_session_creation(): void
    {
        $token = bin2hex(random_bytes(32));
        $userId = $this->createUser();

        $this->db->insert(
            "INSERT INTO auth_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)",
            [$userId, hash('sha256', $token), date('c', time() + 3600), date('c')]
        );

        $stored = $this->db->selectOne(
            "SELECT * FROM auth_tokens WHERE user_id = ? AND expires_at > ?",
            [$userId, date('c')]
        );

        $this->assertNotNull($stored);
        $this->assertEquals($userId, $stored->user_id);
    }

    public function test_session_validation(): void
    {
        $token = bin2hex(random_bytes(32));
        $userId = $this->createUser();

        $this->db->insert(
            "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, hash('sha256', $token), date('c', time() + 3600)]
        );

        // Validate token
        $stored = $this->db->selectOne(
            "SELECT * FROM auth_tokens WHERE token = ? AND expires_at > ?",
            [hash('sha256', $token), date('c')]
        );

        $this->assertNotNull($stored);
        $this->assertTrue($stored->expires_at > date('c'));
    }

    public function test_session_expiry(): void
    {
        $token = bin2hex(random_bytes(32));
        $userId = $this->createUser();

        // Create an already-expired token
        $this->db->insert(
            "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, hash('sha256', $token), date('c', time() - 3600)]
        );

        // Should not be found
        $stored = $this->db->selectOne(
            "SELECT * FROM auth_tokens WHERE token = ? AND expires_at > ?",
            [hash('sha256', $token), date('c')]
        );

        $this->assertNull($stored, 'Expired token should not validate');
    }

    public function test_session_refresh(): void
    {
        $oldToken = bin2hex(random_bytes(32));
        $newToken = bin2hex(random_bytes(32));
        $userId = $this->createUser();

        // Create original token (expiring soon)
        $this->db->insert(
            "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, hash('sha256', $oldToken), date('c', time() + 300)] // 5 min
        );

        // Revoke old token and create new one (refresh)
        $this->db->update(
            "UPDATE auth_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL",
            [date('c'), $userId]
        );

        $this->db->insert(
            "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, hash('sha256', $newToken), date('c', time() + 3600)]
        );

        // Old token should be invalid (revoked)
        $oldValid = $this->db->selectOne(
            "SELECT * FROM auth_tokens WHERE token = ? AND revoked_at IS NULL AND expires_at > ?",
            [hash('sha256', $oldToken), date('c')]
        );
        $this->assertNull($oldValid, 'Old token should be revoked');

        // New token should be valid
        $newValid = $this->db->selectOne(
            "SELECT * FROM auth_tokens WHERE token = ? AND revoked_at IS NULL AND expires_at > ?",
            [hash('sha256', $newToken), date('c')]
        );
        $this->assertNotNull($newValid, 'New token should be valid');
    }

    public function test_session_revocation(): void
    {
        $token = bin2hex(random_bytes(32));
        $userId = $this->createUser();

        // Create token
        $this->db->insert(
            "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$userId, hash('sha256', $token), date('c', time() + 3600)]
        );

        // Revoke
        $this->db->update(
            "UPDATE auth_tokens SET revoked_at = ? WHERE user_id = ?",
            [date('c'), $userId]
        );

        // Verify revoked
        $activeTokens = $this->db->select(
            "SELECT * FROM auth_tokens WHERE user_id = ? AND revoked_at IS NULL AND expires_at > ?",
            [$userId, date('c')]
        );

        $this->assertCount(0, $activeTokens, 'All tokens should be revoked');
    }

    public function test_concurrent_sessions(): void
    {
        $userId = $this->createUser();
        $sessionCount = 5;

        // Create multiple active sessions for the same user
        for ($i = 0; $i < $sessionCount; $i++) {
            $token = bin2hex(random_bytes(32));
            $this->db->insert(
                "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
                [$userId, hash('sha256', $token), date('c', time() + 3600)]
            );
        }

        // Verify all are active
        $active = $this->db->selectValue(
            "SELECT COUNT(*) FROM auth_tokens WHERE user_id = ? AND revoked_at IS NULL AND expires_at > ?",
            [$userId, date('c')]
        );

        $this->assertEquals($sessionCount, $active, "User should have {$sessionCount} active sessions");
    }

    private function createUser(): int
    {
        $this->db->insert(
            "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
            ['Session Test', 'session@test.com', password_hash('password', PASSWORD_DEFAULT)]
        );
        return $this->db->lastInsertId();
    }
}
```

**Expected**: Sessions follow a correct lifecycle — create → validate → refresh → expire → revoke.

**Pitfalls**:
- Always hash tokens before storing; never store raw tokens in the database
- Test time-based expiration with relative timestamps (`time() + X`)
- Verify both `expires_at` and `revoked_at` conditions in validation queries

---

## Appendix: Recipe Selection Guide

| When you need to... | Use Recipe |
|---------------------|------------|
| Mock an external service | #1 — Unit Test with Mocking |
| Set up temp files/dirs for a test | #2 — Test Fixtures Setup & Cleanup |
| Run the same test with different inputs | #3 — Data Provider Pattern |
| Create reusable assertions | #4 — Custom Assertion Pattern |
| Verify an event was dispatched | #5 — Event Dispatching Test |
| Test async/queued listeners | #6 — Async Listener Test |
| Check event payload contents | #7 — Event Payload Assertion |
| Test database queries | #8 — In-Memory SQLite Test |
| Verify migration correctness | #9 — Migration Verification |
| Ensure test database isolation | #10 — Transaction Rollback Pattern |
| Test CLI script output | #11 — CLI Command Output Test |
| Verify scaffolding output | #12 — Scaffolded File Test |
| Test argument parsing | #13 — Argument Parsing Test |
| Test request/response cycle | #14 — Request/Response Cycle Test |
| Verify route matching | #15 — Route Resolution Test |
| Test full API endpoint | #16 — API Endpoint Integration Test |
| Verify tenant isolation | #17 — Tenant Isolation Test |
| Test rate limiter | #18 — Rate Limiting Test |
| Test RBAC permissions | #19 — Authentication/Authorization Test |
| Test session lifecycle | #20 — Session Lifecycle Test |

---

## See Also

- [CLI Beginner Guide](../cli-framework/beginner-guide.md) — CLI fundamentals
- [CLI Intermediate Guide](../cli-framework/intermediate-guide.md) — Advanced CLI patterns
- [Diagnostic Commands](../cli-framework/diagnostic-commands.md) — Troubleshooting
- [ADR-005 Event System Design](../architecture/decisions/ADR-005-event-system-design.md) — Event architecture
- [TestSuite Blueprint Overview](../../architecture/origin/ComponentBlueprints/TestSuite/OVERVIEW.md) — Testing infrastructure design
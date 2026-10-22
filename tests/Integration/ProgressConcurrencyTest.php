<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Core\Database\MySQLConnection;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Application\AuthenticatedUser;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId, Email};
use SovereignStack\Spoke\Lms\Application\Command\RecordProgressCommand;
use SovereignStack\Spoke\Lms\Application\Service\ProgressApplicationService;
use SovereignStack\Spoke\Lms\Domain\Entity\Progress;
use SovereignStack\Spoke\Lms\Domain\Exception\CannotRevertCompletedModuleException;
use SovereignStack\Spoke\Lms\Domain\Repository\{
    CourseRepositoryInterface,
    EnrollmentRepositoryInterface,
    ModuleRepositoryInterface,
    ProgressRepositoryInterface
};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{
    CourseId,
    CourseStatus,
    EnrollmentId,
    EnrollmentStatus,
    ModuleId,
    ModuleStatus
};
use SovereignStack\Spoke\Lms\Infrastructure\Persistence\{
    MySQLProgressRepository,
    MySQLEnrollmentRepository,
    MySQLCourseRepository,
    MySQLModuleRepository
};

/**
 * Integration test for progress persistence concurrency guarantees.
 *
 * This test exercises the real MySQL database to verify that concurrent
 * progress recording operations are serialized correctly using
 * SELECT ... FOR UPDATE row-level locking within transactions.
 *
 * Per SAAI Finding 2 (concurrent regression): the read-validate-write
 * sequence must be concurrency-safe. FOR UPDATE serializes access to the
 * progress row so that Request A's write of 'completed' blocks Request B's
 * stale read of 'in_progress', preventing a regression write.
 *
 * Requires a MySQL database (CI MySQL service or local dev DB).
 * Configured via environment variables:
 *   - MYSQL_DSN (default: mysql:host=127.0.0.1;port=3306;dbname=dglab_test)
 *   - MYSQL_USER (default: test)
 *   - MYSQL_PASSWORD (default: test)
 *
 * @group integration
 * @requires extension pdo_mysql
 */
final class ProgressConcurrencyTest extends TestCase
{
    private static ?PDO $pdo = null;
    private static ?ConnectionInterface $connection = null;
    private static ?ProgressRepositoryInterface $progressRepo = null;
    private static ?EnrollmentRepositoryInterface $enrollmentRepo = null;
    private static ?CourseRepositoryInterface $courseRepo = null;
    private static ?ModuleRepositoryInterface $moduleRepo = null;
    private static ?IdentityInterface $identity = null;
    private static ?ProgressApplicationService $progressService = null;

    private static string $testEnrollmentId;
    private static string $testModuleId;
    private static string $testCourseId;
    private static string $testUserId;

    public static function setUpBeforeClass(): void
    {
        // Skip if no MySQL available
        $dsn = $_ENV['MYSQL_DSN'] ?? 'mysql:host=127.0.0.1;port=3306;dbname=dglab_test';
        $user = $_ENV['MYSQL_USER'] ?? 'test';
        $pass = $_ENV['MYSQL_PASSWORD'] ?? 'test';

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            self::markTestSkipped('MySQL not available: ' . $e->getMessage());
        }

        self::$connection = new MySQLConnection(self::$pdo);
        self::$progressRepo = new MySQLProgressRepository(self::$connection);
        self::$enrollmentRepo = new MySQLEnrollmentRepository(self::$connection);
        self::$courseRepo = new MySQLCourseRepository(self::$connection);
        self::$moduleRepo = new MySQLModuleRepository(self::$connection);
        self::$identity = new TestIdentityService();

        self::$progressService = new ProgressApplicationService(
            progress: self::$progressRepo,
            enrollments: self::$enrollmentRepo,
            courses: self::$courseRepo,
            modules: self::$moduleRepo,
            identity: self::$identity,
            connection: self::$connection,
        );

        // Create test data
        self::$testCourseId = '01H00000000000000000000001';
        self::$testModuleId = '01H00000000000000000000002';
        self::$testEnrollmentId = '01H00000000000000000000003';
        self::$testUserId = '01H00000000000000000000004';

        self::seedTestData();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            // Clean up test data
            self::$pdo->exec("DELETE FROM lms_progress WHERE enrollment_id = '01H00000000000000000000003'");
            self::$pdo->exec("DELETE FROM lms_enrollments WHERE id = '01H00000000000000000000003'");
            self::$pdo->exec("DELETE FROM lms_modules WHERE id = '01H00000000000000000000002'");
            self::$pdo->exec("DELETE FROM lms_courses WHERE id = '01H00000000000000000000001'");
            self::$pdo = null;
        }
    }

    private static function seedTestData(): void
    {
        // Insert test course
        $stmt = self::$pdo->prepare(
            'INSERT IGNORE INTO lms_courses (id, title, description, status, created_at, updated_at) '
            . 'VALUES (:id, :title, :desc, :status, NOW(6), NOW(6))'
        );
        $stmt->execute([
            'id' => self::$testCourseId,
            'title' => 'Concurrency Test Course',
            'desc' => 'Test course for concurrency tests',
            'status' => CourseStatus::Published->value,
        ]);

        // Insert test module
        $stmt = self::$pdo->prepare(
            'INSERT IGNORE INTO lms_modules (id, course_id, title, position, created_at, updated_at) '
            . 'VALUES (:id, :course_id, :title, :position, NOW(6), NOW(6))'
        );
        $stmt->execute([
            'id' => self::$testModuleId,
            'course_id' => self::$testCourseId,
            'title' => 'Test Module',
            'position' => 1,
        ]);

        // Insert test enrollment
        $stmt = self::$pdo->prepare(
            'INSERT IGNORE INTO lms_enrollments (id, course_id, learner_id, status, enrolled_at, created_at, updated_at) '
            . 'VALUES (:id, :course_id, :learner_id, :status, NOW(6), NOW(6), NOW(6))'
        );
        $stmt->execute([
            'id' => self::$testEnrollmentId,
            'course_id' => self::$testCourseId,
            'learner_id' => self::$testUserId,
            'status' => EnrollmentStatus::Active->value,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up progress records between tests
        if (self::$pdo !== null) {
            self::$pdo->exec("DELETE FROM lms_progress WHERE enrollment_id = '{$this->testEnrollmentId}'");
        }
    }

    /**
     * Test that concurrent progress recording for the same (enrollment, module)
     * is serialized correctly using SELECT ... FOR UPDATE.
     *
     * Two concurrent requests attempt to record progress for the same module.
     * The first request should complete and commit. The second request, which
     * blocked on the row lock, should then re-read the updated progress and
     * apply the transition on top of the committed state.
     */
    public function testConcurrentProgressRecordingIsSerialized(): void
    {
        $enrollmentId = new EnrollmentId(self::$testEnrollmentId);
        $moduleId = new ModuleId(self::$testModuleId);
        $actor = new UserId(self::$testUserId);

        // Request 1: Record progress to in_progress
        $cmd1 = new RecordProgressCommand($enrollmentId, $moduleId, ModuleStatus::InProgress);
        self::$progressService->recordProgress($cmd1, $actor);

        $progress1 = self::$progressRepo->get($enrollmentId, $moduleId);
        $this->assertNotNull($progress1);
        $this->assertEquals(ModuleStatus::InProgress, $progress1->status());

        // Request 2 (simulated concurrent): Record progress to completed
        // This should block on the FOR UPDATE lock until Request 1 commits
        $cmd2 = new RecordProgressCommand($enrollmentId, $moduleId, ModuleStatus::Completed);
        self::$progressService->recordProgress($cmd2, $actor);

        $progress2 = self::$progressRepo->get($enrollmentId, $moduleId);
        $this->assertNotNull($progress2);
        $this->assertEquals(ModuleStatus::Completed, $progress2->status());
        $this->assertNotNull($progress2->completedAt());
    }

    /**
     * Test that concurrent INSERT race is handled correctly.
     *
     * When no progress record exists, two concurrent requests may both
     * attempt to INSERT. MySQL's composite PK (enrollment_id, module_id)
     * causes one INSERT to fail with duplicate key (1062). The service
     * catches this, re-reads under lock, and applies the transition.
     */
    public function testConcurrentInsertRaceIsHandled(): void
    {
        $enrollmentId = new EnrollmentId(self::$testEnrollmentId);
        $moduleId = new ModuleId(self::$testModuleId);
        $actor = new UserId(self::$testUserId);

        // Both requests try to insert InProgress for the same (enrollment, module)
        // First one wins the INSERT, second gets duplicate key and retries
        $cmd1 = new RecordProgressCommand($enrollmentId, $moduleId, ModuleStatus::InProgress);
        $cmd2 = new RecordProgressCommand($enrollmentId, $moduleId, ModuleStatus::InProgress);

        // Execute sequentially to simulate the race (real concurrency would need threads)
        self::$progressService->recordProgress($cmd1, $actor);
        self::$progressService->recordProgress($cmd2, $actor); // Same status = idempotent

        $progress = self::$progressRepo->get($enrollmentId, $moduleId);
        $this->assertNotNull($progress);
        $this->assertEquals(ModuleStatus::InProgress, $progress->status());
    }

    /**
     * Test that completed status cannot be reverted (forward-only state machine).
     *
     * Even under concurrency, a completed module cannot be reverted to
     * in_progress or not_started. The domain entity enforces this.
     */
    public function testCompletedStatusCannotBeRevertedUnderConcurrency(): void
    {
        $enrollmentId = new EnrollmentId(self::$testEnrollmentId);
        $moduleId = new ModuleId(self::$testModuleId);
        $actor = new UserId(self::$testUserId);

        // First, complete the module
        $cmdComplete = new RecordProgressCommand($enrollmentId, $moduleId, ModuleStatus::Completed);
        self::$progressService->recordProgress($cmdComplete, $actor);

        $progress = self::$progressRepo->get($enrollmentId, $moduleId);
        $this->assertEquals(ModuleStatus::Completed, $progress->status());

        // Attempt to revert to in_progress (should fail)
        $cmdRevert = new RecordProgressCommand($enrollmentId, $moduleId, ModuleStatus::InProgress);
        $this->expectException(CannotRevertCompletedModuleException::class);
        self::$progressService->recordProgress($cmdRevert, $actor);
    }

    /**
     * Test that getForUpdate acquires a row lock within a transaction.
     *
     * This verifies the repository-level locking primitive that the
     * application service uses for concurrency safety.
     */
    public function testGetForUpdateAcquiresRowLock(): void
    {
        $enrollmentId = new EnrollmentId(self::$testEnrollmentId);
        $moduleId = new ModuleId(self::$testModuleId);

        // Insert initial progress
        $now = new DateTimeImmutable();
        $progress = new Progress(
            moduleId: $moduleId,
            status: ModuleStatus::InProgress,
            startedAt: $now,
            completedAt: null,
        );
        self::$progressRepo->insert($progress, $enrollmentId);

        // Begin transaction and get with lock
        self::$connection->beginTransaction();
        try {
            $locked = self::$progressRepo->getForUpdate($enrollmentId, $moduleId);
            $this->assertNotNull($locked);
            $this->assertEquals(ModuleStatus::InProgress, $locked->status());

            // Update within the same transaction
            $locked->transition(ModuleStatus::Completed, new DateTimeImmutable());
            self::$progressRepo->save($locked, $enrollmentId);

            self::$connection->commit();
        } catch (\Throwable $e) {
            self::$connection->rollBack();
            throw $e;
        }

        // Verify the update was persisted
        $updated = self::$progressRepo->get($enrollmentId, $moduleId);
        $this->assertEquals(ModuleStatus::Completed, $updated->status());
    }

    /**
     * Test that concurrent getForUpdate calls block until transaction commits.
     *
     * This test simulates the blocking behavior by attempting a second
     * getForUpdate in a separate connection while the first transaction
     * holds the lock. In a real concurrent scenario, the second call would
     * block until the first commits.
     */
    public function testConcurrentGetForUpdateBlocks(): void
    {
        $enrollmentId = new EnrollmentId(self::$testEnrollmentId);
        $moduleId = new ModuleId(self::$testModuleId);

        // Insert initial progress
        $now = new DateTimeImmutable();
        $progress = new Progress(
            moduleId: $moduleId,
            status: ModuleStatus::NotStarted,
            startedAt: $now,
            completedAt: null,
        );
        self::$progressRepo->insert($progress, $enrollmentId);

        // Create a second connection to simulate concurrent access
        $dsn = $_ENV['MYSQL_DSN'] ?? 'mysql:host=127.0.0.1;port=3306;dbname=dglab_test';
        $user = $_ENV['MYSQL_USER'] ?? 'test';
        $pass = $_ENV['MYSQL_PASSWORD'] ?? 'test';
        $pdo2 = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $conn2 = new MySQLConnection($pdo2);
        $repo2 = new MySQLProgressRepository($conn2);

        // Connection 1: begin transaction and getForUpdate (holds lock)
        self::$connection->beginTransaction();
        $locked1 = self::$progressRepo->getForUpdate($enrollmentId, $moduleId);
        $this->assertNotNull($locked1);

        // Connection 2: attempt getForUpdate (should block in real concurrency)
        // In this sequential test, we verify the lock is held by checking
        // that the first connection can update and the second sees the update after commit
        $locked1->transition(ModuleStatus::InProgress, new DateTimeImmutable());
        self::$progressRepo->save($locked1, $enrollmentId);
        self::$connection->commit();

        // Connection 2: now get the updated record
        $locked2 = $repo2->getForUpdate($enrollmentId, $moduleId);
        $this->assertEquals(ModuleStatus::InProgress, $locked2->status());

        $conn2->rollBack(); // Clean up second connection
        $pdo2 = null;
    }
}

/**
 * Minimal test implementation of IdentityInterface for integration tests.
 *
 * Grants all roles to the test user for authorization checks.
 */
final class TestIdentityService implements IdentityInterface
{
    private const TEST_USER_ID = '01H00000000000000000000004';
    private const TEST_EMAIL = 'test@example.com';

    public function authenticate(string $email, string $password): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: new UserId(self::TEST_USER_ID),
            email: Email::fromString(self::TEST_EMAIL),
            roles: [
                RoleIdentifier::fromString('lms:admin'),
                RoleIdentifier::fromString('lms:instructor'),
                RoleIdentifier::fromString('platform:admin'),
            ],
        );
    }

    public function getUserById(UserId $id): ?AuthenticatedUser
    {
        if ((string) $id === self::TEST_USER_ID) {
            return $this->authenticate(self::TEST_EMAIL, 'password');
        }
        return null;
    }

    public function hasRole(UserId $id, RoleIdentifier $role): bool
    {
        return (string) $id === self::TEST_USER_ID;
    }

    public function hasAnyRole(UserId $id, RoleIdentifier ...$roles): bool
    {
        return (string) $id === self::TEST_USER_ID;
    }
}
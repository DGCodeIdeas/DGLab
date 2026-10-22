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
 * Uses pcntl_fork() for genuine process-based concurrency. Each child process
 * gets its own PDO connection to simulate real concurrent database access.
 *
 * Requires a MySQL database (CI MySQL service or local dev DB).
 * Configured via environment variables:
 *   - MYSQL_DSN (default: mysql:host=127.0.0.1;port=3306;dbname=dglab_test)
 *   - MYSQL_USER (default: test)
 *   - MYSQL_PASSWORD (default: test)
 *
 * @group integration
 * @requires extension pdo_mysql
 * @requires extension pcntl
 * @requires extension posix
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
        if (!extension_loaded('pcntl') || !extension_loaded('posix')) {
            self::markTestSkipped('pcntl or posix extension not available - required for fork-based concurrency tests');
        }

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

        self::$testCourseId = '01H00000000000000000000001';
        self::$testModuleId = '01H00000000000000000000002';
        self::$testEnrollmentId = '01H00000000000000000000003';
        self::$testUserId = '01H00000000000000000000004';

        self::seedTestData();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            self::$pdo->exec("DELETE FROM lms_progress WHERE enrollment_id = '01H00000000000000000000003'");
            self::$pdo->exec("DELETE FROM lms_enrollments WHERE id = '01H00000000000000000000003'");
            self::$pdo->exec("DELETE FROM lms_modules WHERE id = '01H00000000000000000000002'");
            self::$pdo->exec("DELETE FROM lms_courses WHERE id = '01H00000000000000000000001'");
            self::$pdo = null;
        }
    }

    private static function seedTestData(): void
    {
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
        if (self::$pdo !== null) {
            self::$pdo->exec("DELETE FROM lms_progress WHERE enrollment_id = '{$this->testEnrollmentId}'");
        }
    }

    /**
     * Create a child process that runs a callable and returns its exit code.
     * The child gets a fresh PDO connection to the database.
     */
    private function forkChild(callable $task): int
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new \RuntimeException('Failed to fork process');
        }

        if ($pid === 0) {
            // Child process
            try {
                $task();
                exit(0);
            } catch (\Throwable $e) {
                // Write error to a temp file for parent to read
                $errorFile = sys_get_temp_dir() . '/progress_concurrency_error_' . getmypid() . '.txt';
                file_put_contents($errorFile, $e->getMessage() . "\n" . $e->getTraceAsString());
                exit(1);
            }
        }

        // Parent process - wait for child
        $status = 0;
        pcntl_waitpid($pid, $status);

        if (pcntl_wifexited($status)) {
            $exitCode = pcntl_wexitstatus($status);
            if ($exitCode !== 0) {
                $errorFile = sys_get_temp_dir() . '/progress_concurrency_error_' . $pid . '.txt';
                if (file_exists($errorFile)) {
                    $error = file_get_contents($errorFile);
                    unlink($errorFile);
                    throw new \RuntimeException("Child process $pid failed: $error");
                }
            }
            return $exitCode;
        }

        throw new \RuntimeException("Child process $pid terminated abnormally");
    }

    /**
     * Run multiple child processes concurrently and wait for all to complete.
     * Returns array of exit codes.
     */
    private function runConcurrent(array $tasks): array
    {
        $pids = [];
        foreach ($tasks as $task) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Failed to fork process');
            }
            if ($pid === 0) {
                // Child
                try {
                    $task();
                    exit(0);
                } catch (\Throwable $e) {
                    $errorFile = sys_get_temp_dir() . '/progress_concurrency_error_' . getmypid() . '.txt';
                    file_put_contents($errorFile, $e->getMessage() . "\n" . $e->getTraceAsString());
                    exit(1);
                }
            }
            $pids[] = $pid;
        }

        $results = [];
        foreach ($pids as $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);
            if (pcntl_wifexited($status)) {
                $exitCode = pcntl_wexitstatus($status);
                if ($exitCode !== 0) {
                    $errorFile = sys_get_temp_dir() . '/progress_concurrency_error_' . $pid . '.txt';
                    if (file_exists($errorFile)) {
                        $error = file_get_contents($errorFile);
                        unlink($errorFile);
                        throw new \RuntimeException("Child process $pid failed: $error");
                    }
                }
                $results[] = $exitCode;
            } else {
                throw new \RuntimeException("Child process $pid terminated abnormally");
            }
        }

        return $results;
    }

    private function createTestDbConnection(): PDO
    {
        $dsn = $_ENV['MYSQL_DSN'] ?? 'mysql:host=127.0.0.1;port=3306;dbname=dglab_test';
        $user = $_ENV['MYSQL_USER'] ?? 'test';
        $pass = $_ENV['MYSQL_PASSWORD'] ?? 'test';
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
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
        $enrollmentId = self::$testEnrollmentId;
        $moduleId = self::$testModuleId;
        $actorId = self::$testUserId;

        // Pre-create progress at InProgress status
        $stmt = self::$pdo->prepare(
            'INSERT INTO lms_progress (enrollment_id, module_id, status, started_at, completed_at) '
            . 'VALUES (:enrollment_id, :module_id, :status, NOW(6), NULL)'
        );
        $stmt->execute([
            'enrollment_id' => $enrollmentId,
            'module_id' => $moduleId,
            'status' => ModuleStatus::InProgress->value,
        ]);

        // Child 1: Record progress to Completed
        // Child 2: Record progress to Completed (same status = idempotent)
        $results = $this->runConcurrent([
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::Completed
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::Completed
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
        ]);

        $this->assertEquals([0, 0], $results, 'Both concurrent requests should succeed');

        // Verify final state is Completed
        $progress = self::$progressRepo->get(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
        $this->assertNotNull($progress);
        $this->assertEquals(ModuleStatus::Completed, $progress->status());
        $this->assertNotNull($progress->completedAt());
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
        $enrollmentId = self::$testEnrollmentId;
        $moduleId = self::$testModuleId;
        $actorId = self::$testUserId;

        // Both requests try to insert InProgress for the same (enrollment, module)
        // First one wins the INSERT, second gets duplicate key and retries
        $results = $this->runConcurrent([
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::InProgress
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::InProgress
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
        ]);

        $this->assertEquals([0, 0], $results, 'Both concurrent requests should succeed (one wins INSERT, other retries)');

        // Verify final state is InProgress
        $progress = self::$progressRepo->get(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
        $this->assertNotNull($progress);
        $this->assertEquals(ModuleStatus::InProgress, $progress->status());
        $this->assertNotNull($progress->startedAt());
    }

    /**
     * Test that completed status cannot be reverted (forward-only state machine).
     *
     * Even under concurrency, a completed module cannot be reverted to
     * in_progress or not_started. The domain entity enforces this.
     */
    public function testCompletedStatusCannotBeRevertedUnderConcurrency(): void
    {
        $enrollmentId = self::$testEnrollmentId;
        $moduleId = self::$testModuleId;
        $actorId = self::$testUserId;

        // First, complete the module (sequential setup)
        $stmt = self::$pdo->prepare(
            'INSERT INTO lms_progress (enrollment_id, module_id, status, started_at, completed_at) '
            . 'VALUES (:enrollment_id, :module_id, :status, NOW(6), NOW(6))'
        );
        $stmt->execute([
            'enrollment_id' => $enrollmentId,
            'module_id' => $moduleId,
            'status' => ModuleStatus::Completed->value,
        ]);

        // Concurrent attempt to revert to InProgress - should fail
        $results = $this->runConcurrent([
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::InProgress
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::NotStarted
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
        ]);

        // Both should fail with CannotRevertCompletedModuleException (exit code 1)
        $this->assertEquals([1, 1], $results, 'Both concurrent revert attempts should fail');

        // Verify state remains Completed
        $progress = self::$progressRepo->get(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
        $this->assertNotNull($progress);
        $this->assertEquals(ModuleStatus::Completed, $progress->status());
    }

    /**
     * Test FOR UPDATE blocking with forked processes.
     *
     * Connection 1 begins a transaction and acquires a row lock via getForUpdate.
     * Connection 2 attempts getForUpdate on the same row and should block
     * until Connection 1 commits.
     */
    public function testForUpdateBlockingWithForkedProcesses(): void
    {
        $enrollmentId = self::$testEnrollmentId;
        $moduleId = self::$testModuleId;

        // Insert initial progress
        $stmt = self::$pdo->prepare(
            'INSERT INTO lms_progress (enrollment_id, module_id, status, started_at, completed_at) '
            . 'VALUES (:enrollment_id, :module_id, :status, NOW(6), NULL)'
        );
        $stmt->execute([
            'enrollment_id' => $enrollmentId,
            'module_id' => $moduleId,
            'status' => ModuleStatus::InProgress->value,
        ]);

        $blockedFlagFile = sys_get_temp_dir() . '/progress_concurrency_blocked_' . getmypid() . '.txt';
        $releaseFlagFile = sys_get_temp_dir() . '/progress_concurrency_release_' . getmypid() . '.txt';

        // Clean up any leftover flag files
        @unlink($blockedFlagFile);
        @unlink($releaseFlagFile);

        $results = $this->runConcurrent([
            // Child 1: Hold lock for a bit, then update and commit
            function () use ($enrollmentId, $moduleId, $blockedFlagFile, $releaseFlagFile) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);

                $conn->beginTransaction();
                try {
                    $locked = $repo->getForUpdate(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
                    if ($locked === null) {
                        throw new \RuntimeException('Expected progress row to exist');
                    }

                    // Signal that we have the lock
                    file_put_contents($blockedFlagFile, 'locked');

                    // Wait for release signal (parent will create this file)
                    $waitStart = microtime(true);
                    while (!file_exists($releaseFlagFile)) {
                        usleep(10000); // 10ms
                        if (microtime(true) - $waitStart > 10) {
                            throw new \RuntimeException('Timeout waiting for release signal');
                        }
                    }
                    @unlink($releaseFlagFile);

                    // Update within the same transaction
                    $locked->transition(ModuleStatus::Completed, new DateTimeImmutable());
                    $repo->save($locked, new EnrollmentId($enrollmentId));

                    $conn->commit();
                } catch (\Throwable $e) {
                    $conn->rollBack();
                    throw $e;
                }
            },
            // Child 2: Attempt getForUpdate - should block until Child 1 commits
            function () use ($enrollmentId, $moduleId, $blockedFlagFile, $releaseFlagFile) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);

                // Wait for Child 1 to acquire lock
                $waitStart = microtime(true);
                while (!file_exists($blockedFlagFile)) {
                    usleep(10000);
                    if (microtime(true) - $waitStart > 5) {
                        throw new \RuntimeException('Timeout waiting for lock signal');
                    }
                }

                // Now attempt getForUpdate - this should block until Child 1 commits
                $startWait = microtime(true);
                $conn->beginTransaction();
                try {
                    $locked = $repo->getForUpdate(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
                    if ($locked === null) {
                        throw new \RuntimeException('Expected progress row to exist');
                    }

                    // If we get here, we acquired the lock after Child 1 released it
                    $waitTime = microtime(true) - $startWait;
                    // Should have waited at least some time for the lock
                    if ($waitTime < 0.1) {
                        throw new \RuntimeException('Lock was not held - expected blocking behavior');
                    }

                    $this->assertEquals(ModuleStatus::Completed, $locked->status());
                    $conn->commit();
                } catch (\Throwable $e) {
                    $conn->rollBack();
                    throw $e;
                }
            },
        ]);

        $this->assertEquals([0, 0], $results, 'Both processes should complete successfully');

        @unlink($blockedFlagFile);
        @unlink($releaseFlagFile);

        // Verify final state is Completed
        $progress = self::$progressRepo->get(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
        $this->assertNotNull($progress);
        $this->assertEquals(ModuleStatus::Completed, $progress->status());
        $this->assertNotNull($progress->completedAt());
    }

    /**
     * Test competing status updates under real concurrency.
     *
     * Multiple concurrent requests with different target statuses:
     * - One tries InProgress
     * - One tries Completed
     * Both should succeed, final state depends on execution order but
     * must be a valid forward transition.
     */
    public function testCompetingStatusUpdatesUnderRealConcurrency(): void
    {
        $enrollmentId = self::$testEnrollmentId;
        $moduleId = self::$testModuleId;
        $actorId = self::$testUserId;

        // Start with no progress record

        $results = $this->runConcurrent([
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::InProgress
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
            function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::Completed
                );
                $service->recordProgress($cmd, new UserId($actorId));
            },
        ]);

        $this->assertEquals([0, 0], $results, 'Both concurrent requests should succeed');

        // Verify final state is either InProgress or Completed (forward-only)
        $progress = self::$progressRepo->get(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
        $this->assertNotNull($progress);
        $this->assertContains($progress->status(), [ModuleStatus::InProgress, ModuleStatus::Completed]);
    }

    /**
     * Test concurrent getForUpdate calls block until transaction commits.
     *
     * This test uses forked processes to verify the blocking behavior
     * of SELECT ... FOR UPDATE in a real concurrent scenario.
     */
    public function testConcurrentGetForUpdateBlocks(): void
    {
        $enrollmentId = self::$testEnrollmentId;
        $moduleId = self::$testModuleId;

        // Insert initial progress
        $stmt = self::$pdo->prepare(
            'INSERT INTO lms_progress (enrollment_id, module_id, status, started_at, completed_at) '
            . 'VALUES (:enrollment_id, :module_id, :status, NOW(6), NULL)'
        );
        $stmt->execute([
            'enrollment_id' => $enrollmentId,
            'module_id' => $moduleId,
            'status' => ModuleStatus::NotStarted->value,
        ]);

        $blockedFlagFile = sys_get_temp_dir() . '/progress_concurrency_blocked_' . getmypid() . '.txt';
        $releaseFlagFile = sys_get_temp_dir() . '/progress_concurrency_release_' . getmypid() . '.txt';
        @unlink($blockedFlagFile);
        @unlink($releaseFlagFile);

        $results = $this->runConcurrent([
            // Child 1: Begin transaction, getForUpdate, hold lock, update, commit
            function () use ($enrollmentId, $moduleId, $blockedFlagFile, $releaseFlagFile) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);

                $conn->beginTransaction();
                try {
                    $locked = $repo->getForUpdate(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
                    if ($locked === null) {
                        throw new \RuntimeException('Expected progress row to exist');
                    }

                    // Signal that we have the lock
                    file_put_contents($blockedFlagFile, 'locked');

                    // Wait for release signal
                    $waitStart = microtime(true);
                    while (!file_exists($releaseFlagFile)) {
                        usleep(10000);
                        if (microtime(true) - $waitStart > 10) {
                            throw new \RuntimeException('Timeout waiting for release signal');
                        }
                    }
                    @unlink($releaseFlagFile);

                    // Update within the same transaction
                    $locked->transition(ModuleStatus::InProgress, new DateTimeImmutable());
                    $repo->save($locked, new EnrollmentId($enrollmentId));

                    $conn->commit();
                } catch (\Throwable $e) {
                    $conn->rollBack();
                    throw $e;
                }
            },
            // Child 2: Attempt getForUpdate - should block until Child 1 commits
            function () use ($enrollmentId, $moduleId, $blockedFlagFile, $releaseFlagFile) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);

                // Wait for Child 1 to acquire lock
                $waitStart = microtime(true);
                while (!file_exists($blockedFlagFile)) {
                    usleep(10000);
                    if (microtime(true) - $waitStart > 5) {
                        throw new \RuntimeException('Timeout waiting for lock signal');
                    }
                }

                // Now attempt getForUpdate - this should block until Child 1 commits
                $startWait = microtime(true);
                $conn->beginTransaction();
                try {
                    $locked = $repo->getForUpdate(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
                    if ($locked === null) {
                        throw new \RuntimeException('Expected progress row to exist');
                    }

                    $waitTime = microtime(true) - $startWait;
                    // Should have waited for the lock
                    if ($waitTime < 0.1) {
                        throw new \RuntimeException('Lock was not held - expected blocking behavior, waited ' . $waitTime . 's');
                    }

                    // Should see the updated status from Child 1
                    $this->assertEquals(ModuleStatus::InProgress, $locked->status());
                    $conn->commit();
                } catch (\Throwable $e) {
                    $conn->rollBack();
                    throw $e;
                }
            },
        ]);

        $this->assertEquals([0, 0], $results, 'Both processes should complete successfully');

        @unlink($blockedFlagFile);
        @unlink($releaseFlagFile);

        // Verify final state is InProgress
        $progress = self::$progressRepo->get(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
        $this->assertNotNull($progress);
        $this->assertEquals(ModuleStatus::InProgress, $progress->status());
    }

    /**
     * Test multiple concurrent requests (3+) for the same (enrollment, module).
     */
    public function testMultipleConcurrentRequests(): void
    {
        $enrollmentId = self::$testEnrollmentId;
        $moduleId = self::$testModuleId;
        $actorId = self::$testUserId;

        // 5 concurrent requests all trying to set to Completed
        $tasks = [];
        for ($i = 0; $i < 5; $i++) {
            $tasks[] = function () use ($enrollmentId, $moduleId, $actorId) {
                $pdo = $this->createTestDbConnection();
                $conn = new MySQLConnection($pdo);
                $repo = new MySQLProgressRepository($conn);
                $enrollmentRepo = new MySQLEnrollmentRepository($conn);
                $courseRepo = new MySQLCourseRepository($conn);
                $moduleRepo = new MySQLModuleRepository($conn);
                $identity = new TestIdentityService();
                $service = new ProgressApplicationService(
                    progress: $repo,
                    enrollments: $enrollmentRepo,
                    courses: $courseRepo,
                    modules: $moduleRepo,
                    identity: $identity,
                    connection: $conn,
                );

                $cmd = new RecordProgressCommand(
                    new EnrollmentId($enrollmentId),
                    new ModuleId($moduleId),
                    ModuleStatus::Completed
                );
                $service->recordProgress($cmd, new UserId($actorId));
            };
        }

        $results = $this->runConcurrent($tasks);

        // All should succeed (idempotent after first)
        $this->assertEquals(array_fill(0, 5, 0), $results, 'All 5 concurrent requests should succeed');

        // Verify final state is Completed
        $progress = self::$progressRepo->get(new EnrollmentId($enrollmentId), new ModuleId($moduleId));
        $this->assertNotNull($progress);
        $this->assertEquals(ModuleStatus::Completed, $progress->status());
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
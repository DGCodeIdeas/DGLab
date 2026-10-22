<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Tests\Unit\Infrastructure;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\Progress;
use SovereignStack\Spoke\Lms\Domain\Repository\ProgressRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, ModuleId, ModuleStatus};
use SovereignStack\Spoke\Lms\Infrastructure\Persistence\MySQLProgressRepository;

final class MySQLProgressRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private MySQLProgressRepository $repository;
    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->repository = new MySQLProgressRepository($this->connection);
    }
    public function testImplementsProgressRepositoryInterface(): void
    {
        self::assertInstanceOf(ProgressRepositoryInterface::class, $this->repository);
    }
    public function testGetUsesPrepareWithCompositeKey(): void
    {
        $eId = EnrollmentId::generate(); $mId = ModuleId::generate();
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['enrollment_id'=>(string)$eId,'module_id'=>(string)$mId]);
        $stmt->method('fetch')->willReturn(false);
        $this->connection->expects($this->once())->method('prepare')->willReturn($stmt);
        self::assertNull($this->repository->get($eId, $mId));
    }
    public function testGetReturnsHydratedProgressWhenFound(): void
    {
        $eId = EnrollmentId::generate(); $mId = ModuleId::generate();
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['enrollment_id'=>(string)$eId,'module_id'=>(string)$mId,'status'=>'in_progress','started_at'=>'2026-10-20 12:00:00.000000','completed_at'=>null]);
        $this->connection->method('prepare')->willReturn($stmt);
        $result = $this->repository->get($eId, $mId);
        self::assertNotNull($result);
        self::assertSame(ModuleStatus::InProgress, $result->status());
    }
    public function testGetThrowsOnNullStartedAt(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['enrollment_id'=>'e','module_id'=>'m','status'=>'not_started','started_at'=>null,'completed_at'=>null]);
        $this->connection->method('prepare')->willReturn($stmt);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Data inconsistency');
        $this->repository->get(EnrollmentId::generate(), ModuleId::generate());
    }
    // Per SAAI Finding 2: getForUpdate must use FOR UPDATE clause
    public function testGetForUpdateSqlContainsForUpdate(): void
    {
        $capturedSql = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $this->connection->expects($this->once())->method('prepare')
            ->willReturnCallback(function($sql) use (&$capturedSql, $stmt) { $capturedSql = $sql; return $stmt; });
        $this->repository->getForUpdate(EnrollmentId::generate(), ModuleId::generate());
        self::assertStringContainsStringIgnoringCase('FOR UPDATE', $capturedSql);
    }
    public function testGetForUpdateReturnsHydratedProgressWhenFound(): void
    {
        $eId = EnrollmentId::generate(); $mId = ModuleId::generate();
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['enrollment_id'=>(string)$eId,'module_id'=>(string)$mId,'status'=>'completed','started_at'=>'2026-10-20 10:00:00.000000','completed_at'=>'2026-10-20 11:00:00.000000']);
        $this->connection->method('prepare')->willReturn($stmt);
        $result = $this->repository->getForUpdate($eId, $mId);
        self::assertNotNull($result);
        self::assertSame(ModuleStatus::Completed, $result->status());
    }
    public function testFindByEnrollmentReturnsOrderedArray(): void
    {
        $eId = EnrollmentId::generate();
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['enrollment_id'=>(string)$eId,'module_id'=>'m1','status'=>'completed','started_at'=>'2026-10-20 10:00:00.000000','completed_at'=>'2026-10-20 11:00:00.000000'],
            ['enrollment_id'=>(string)$eId,'module_id'=>'m2','status'=>'in_progress','started_at'=>'2026-10-20 12:00:00.000000','completed_at'=>null],
        ]);
        $this->connection->method('prepare')->willReturn($stmt);
        $result = $this->repository->findByEnrollment($eId);
        self::assertCount(2, $result);
        self::assertSame(ModuleStatus::Completed, $result[0]->status());
    }
    public function testFindByEnrollmentReturnsEmptyArrayWhenNoProgress(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $this->connection->method('prepare')->willReturn($stmt);
        self::assertSame([], $this->repository->findByEnrollment(EnrollmentId::generate()));
    }
    // Per SAAI Finding 4: save() uses atomic upsert
    public function testSaveUsesAtomicUpsert(): void
    {
        $progress = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $this->connection->expects($this->once())->method('prepare')->willReturn($stmt);
        $this->repository->save($progress, EnrollmentId::generate());
    }
    public function testSaveSqlContainsOnDuplicateKeyUpdate(): void
    {
        $capturedSql = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $this->connection->expects($this->once())->method('prepare')
            ->willReturnCallback(function($sql) use (&$capturedSql, $stmt) { $capturedSql = $sql; return $stmt; });
        $this->repository->save(new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null), EnrollmentId::generate());
        self::assertStringContainsStringIgnoringCase('ON DUPLICATE KEY UPDATE', $capturedSql);
    }
    public function testInsertUsesPrepareAndExecute(): void
    {
        $eId = EnrollmentId::generate();
        $progress = new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null);
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $this->connection->expects($this->once())->method('prepare')->willReturn($stmt);
        $this->repository->insert($progress, $eId);
    }

    public function testInsertSqlContainsInsertIntoLmsProgress(): void
    {
        $capturedSql = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $this->connection->expects($this->once())->method('prepare')
            ->willReturnCallback(function ($sql) use (&$capturedSql, $stmt) {
                $capturedSql = $sql;
                return $stmt;
            });
        $this->repository->insert(
            new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null),
            EnrollmentId::generate(),
        );
        self::assertStringContainsStringIgnoringCase('INSERT INTO lms_progress', $capturedSql);
    }

    public function testInsertExecuteParamsIncludeCompositeKeyAndStatus(): void
    {
        $eId = EnrollmentId::generate();
        $mId = ModuleId::generate();
        $progress = new Progress($mId, ModuleStatus::Completed, new \DateTimeImmutable(), new \DateTimeImmutable());
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with(self::callback(function ($params) use ($eId, $mId) {
                return $params['enrollment_id'] === (string) $eId
                    && $params['module_id'] === (string) $mId
                    && $params['status'] === 'completed'
                    && isset($params['started_at'])
                    && isset($params['completed_at']);
            }));
        $this->connection->method('prepare')->willReturn($stmt);
        $this->repository->insert($progress, $eId);
    }

    public function testInsertPropagatesPDOExceptionOnDuplicateKey(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willThrowException((function(){ $e=new \PDOException('Duplicate entry'); $e->errorInfo=[null,1062,'Duplicate entry']; return $e; })());
        $this->connection->method('prepare')->willReturn($stmt);
        $this->expectException(\PDOException::class);
        $this->repository->insert(
            new Progress(ModuleId::generate(), ModuleStatus::InProgress, new \DateTimeImmutable(), null),
            EnrollmentId::generate(),
        );
    }
}

// original trailing? We need replace last closing brace


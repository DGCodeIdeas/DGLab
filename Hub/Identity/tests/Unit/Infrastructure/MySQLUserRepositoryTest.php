<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Identity\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Domain\Entity\User;
use SovereignStack\Hub\Identity\Domain\ValueObject\{Email, RoleIdentifier, UserId};
use SovereignStack\Hub\Identity\Infrastructure\Persistence\MySQLUserRepository;

/**
 * Regression test for HUB-04 DBAL contract drift (Increment 3 Step 1).
 *
 * Verifies that MySQLUserRepository uses the ACTUAL ConnectionInterface API
 * (prepare/execute/fetch/rollBack) — NOT the non-existent fetchOne()/execute()/
 * fetchAll()/rollback() methods that caused the contract drift.
 *
 * Per SAAI: "Add regression tests that detect calls to methods not declared
 * by the interface." PHPUnit's createMock(ConnectionInterface::class) creates
 * a mock that ONLY allows methods declared on the interface. If the code
 * calls a non-interface method, the mock throws
 * "Error: Call to undefined method".
 */
final class MySQLUserRepositoryTest extends TestCase
{
    private ConnectionInterface $connection;
    private MySQLUserRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->repository = new MySQLUserRepository($this->connection);
    }

    public function testFindByIdUsesPrepareNotFetchOne(): void
    {
        $id = new UserId('test-user-id-1234567890123');

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['id' => (string) $id]);
        $stmt->method('fetch')->willReturn(false); // Not found

        // fetchOne() does not exist on ConnectionInterface — if this test passes,
        // the repository is using prepare() correctly.
        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE id = :id')
            ->willReturn($stmt);

        $result = $this->repository->findById($id);

        self::assertNull($result);
    }

    public function testFindByIdReturnsHydratedUser(): void
    {
        $id = new UserId('test-user-id-1234567890123');

        $userStmt = $this->createMock(\PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetch')->willReturn([
            'id' => (string) $id,
            'email' => 'test@example.com',
            'password_hash' => '$2y$',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $rolesStmt = $this->createMock(\PDOStatement::class);
        $rolesStmt->method('execute')->willReturn(true);
        $rolesStmt->method('fetchAll')->willReturn([
            ['name' => 'platform:admin'],
        ]);

        $this->connection->method('prepare')
            ->willReturnOnConsecutiveCalls($userStmt, $rolesStmt);

        $result = $this->repository->findById($id);

        self::assertNotNull($result);
        self::assertSame('test@example.com', (string) $result->email());
        self::assertCount(1, $result->roles());
    }

    public function testFindByEmailUsesPrepareNotFetchOne(): void
    {
        $email = Email::fromString('user@example.com');

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false); // Not found

        $this->connection->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users WHERE email = :email')
            ->willReturn($stmt);

        $result = $this->repository->findByEmail($email);

        self::assertNull($result);
    }

    public function testSaveUsesPrepareAndRollBackNotExecuteAndRollback(): void
    {
        $user = new User(
            id: new UserId('new-user-id-12345678901'),
            email: Email::fromString('new@example.com'),
            passwordHash: 'hash',
            roles: [],
        );

        // prepare for existence check
        $existsStmt = $this->createMock(\PDOStatement::class);
        $existsStmt->method('execute')->willReturn(true);
        $existsStmt->method('fetch')->willReturn(false); // Not found → INSERT

        // prepare for INSERT
        $insertStmt = $this->createMock(\PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute');

        // prepare for DELETE existing roles
        $deleteRolesStmt = $this->createMock(\PDOStatement::class);
        $deleteRolesStmt->method('execute')->willReturn(true);

        $this->connection->method('prepare')
            ->willReturnOnConsecutiveCalls($existsStmt, $insertStmt, $deleteRolesStmt);

        // rollBack() with capital B — NOT rollback() with lowercase b
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        // rollBack is never called on success path
        $this->connection->expects($this->never())->method('rollBack');

        $this->repository->save($user);
    }

    public function testSaveRollsBackWithCorrectCaseOnFailure(): void
    {
        $user = new User(
            id: new UserId('fail-user-id-123456789012'),
            email: Email::fromString('fail@example.com'),
            passwordHash: 'hash',
            roles: [],
        );

        $existsStmt = $this->createMock(\PDOStatement::class);
        $existsStmt->method('execute')->willReturn(true);
        $existsStmt->method('fetch')->willReturn(false);

        $insertStmt = $this->createMock(\PDOStatement::class);
        $insertStmt->method('execute')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->connection->method('prepare')
            ->willReturnOnConsecutiveCalls($existsStmt, $insertStmt);

        $this->connection->expects($this->once())->method('beginTransaction');
        // rollBack() with capital B — the contract drift had lowercase rollback()
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $this->repository->save($user);
    }

    public function testDeleteUsesPrepareNotExecute(): void
    {
        $id = new UserId('delete-user-id-12345678901');

        $stmt1 = $this->createMock(\PDOStatement::class);
        $stmt1->expects($this->once())->method('execute')->with(['id' => (string) $id]);

        $stmt2 = $this->createMock(\PDOStatement::class);
        $stmt2->expects($this->once())->method('execute')->with(['id' => (string) $id]);

        $this->connection->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmt1, $stmt2);

        $this->connection->expects($this->never())->method('exec');

        $this->repository->delete($id);
    }

    public function testFetchRolesUsesPrepareNotFetchAll(): void
    {
        $id = new UserId('roles-user-id-123456789012');

        $userStmt = $this->createMock(\PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetch')->willReturn([
            'id' => (string) $id,
            'email' => 'roles@example.com',
            'password_hash' => 'hash',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $rolesStmt = $this->createMock(\PDOStatement::class);
        $rolesStmt->expects($this->once())
            ->method('execute')
            ->with(['uid' => (string) $id]);
        $rolesStmt->method('fetchAll')->willReturn([
            ['name' => 'lms:learner'],
            ['name' => 'lms:instructor'],
        ]);

        $this->connection->method('prepare')
            ->willReturnOnConsecutiveCalls($userStmt, $rolesStmt);

        $result = $this->repository->findById($id);

        self::assertNotNull($result);
        self::assertCount(2, $result->roles());
        self::assertTrue($result->hasRole(RoleIdentifier::fromString('lms:learner')));
        self::assertTrue($result->hasRole(RoleIdentifier::fromString('lms:instructor')));
    }
}

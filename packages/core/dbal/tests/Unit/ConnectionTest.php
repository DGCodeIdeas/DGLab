<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\Connection;
use SovereignStack\Core\Database\DatabaseException;
use SovereignStack\Core\Database\Transaction;

final class ConnectionTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');
        $this->conn->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
    }

    public function testExecReturnsAffectedRows(): void
    {
        $count = $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
        self::assertSame(1, $count);
    }

    public function testQueryReturnsStatement(): void
    {
        $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
        $stmt = $this->conn->query('SELECT * FROM test');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
    }

    public function testPrepareReturnsCachedStatement(): void
    {
        $sql = 'SELECT * FROM test WHERE id = ?';
        $stmt1 = $this->conn->prepare($sql);
        $stmt2 = $this->conn->prepare($sql);
        // Same SQL → same cached PDOStatement instance
        self::assertSame(spl_object_id($stmt1), spl_object_id($stmt2));
    }

    public function testPrepareDifferentSqlReturnsDifferentStatements(): void
    {
        $stmt1 = $this->conn->prepare('SELECT * FROM test WHERE id = ?');
        $stmt2 = $this->conn->prepare('SELECT * FROM test WHERE name = ?');
        self::assertNotSame(spl_object_id($stmt1), spl_object_id($stmt2));
    }

    public function testLastInsertId(): void
    {
        $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
        $id = $this->conn->lastInsertId();
        self::assertNotEmpty($id);
    }

    public function testQuote(): void
    {
        $quoted = $this->conn->quote("Alice");
        self::assertNotEmpty($quoted);
        self::assertStringContainsString('Alice', $quoted);
    }

    // --- Transaction tests ---

    public function testBeginAndCommit(): void
    {
        $this->conn->beginTransaction();
        self::assertSame(1, $this->conn->getTransactionNestingLevel());
        $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
        $this->conn->commit();
        self::assertSame(0, $this->conn->getTransactionNestingLevel());

        $stmt = $this->conn->query('SELECT COUNT(*) FROM test');
        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testBeginAndRollback(): void
    {
        $this->conn->beginTransaction();
        $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
        $this->conn->rollBack();
        self::assertSame(0, $this->conn->getTransactionNestingLevel());

        $stmt = $this->conn->query('SELECT COUNT(*) FROM test');
        self::assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testNestedTransactionsViaSavepoints(): void
    {
        $this->conn->beginTransaction(); // outer
        $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");

        $this->conn->beginTransaction(); // inner (SAVEPOINT sp_1)
        $this->conn->exec("INSERT INTO test (name) VALUES ('Bob')");
        $this->conn->rollBack(); // ROLLBACK TO SAVEPOINT sp_1

        $this->conn->commit(); // outer COMMIT

        $stmt = $this->conn->query('SELECT COUNT(*) FROM test');
        self::assertSame(1, (int) $stmt->fetchColumn()); // Alice, not Bob
    }

    // testAbortedTransactionPreventsCommit — removed at depth 2.
    // The blueprint's state diagram (ABORTED state) contradicts its CI
    // verification criteria (nested rollback → outer commit succeeds).
    // The ABORTED state is a depth-3 production-hardening feature.

    // --- Transaction wrapper ---

    public function testTransactionWrapCommitsOnSuccess(): void
    {
        $tx = new Transaction($this->conn);
        $tx->wrap(function () {
            $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
        });

        $stmt = $this->conn->query('SELECT COUNT(*) FROM test');
        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testTransactionWrapRollsBackOnException(): void
    {
        $tx = new Transaction($this->conn);

        try {
            $tx->wrap(function () {
                $this->conn->exec("INSERT INTO test (name) VALUES ('Alice')");
                throw new \RuntimeException('boom');
            });
            self::fail('Should have thrown');
        } catch (\RuntimeException $e) {
            // Expected
        }

        $stmt = $this->conn->query('SELECT COUNT(*) FROM test');
        self::assertSame(0, (int) $stmt->fetchColumn());
    }

    // --- Error handling ---

    public function testInvalidSqlThrowsDatabaseException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->conn->query('SELECT * FROM nonexistent_table');
    }
}

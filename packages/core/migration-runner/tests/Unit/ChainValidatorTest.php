<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\MigrationRunner\Migration\ChainValidationException;
use SovereignStack\Core\MigrationRunner\Migration\ChainValidator;
use SovereignStack\Core\MigrationRunner\Migration\MigrationDefinition;

/**
 * Unit tests for ChainValidator — pure logic, no MySQL required.
 *
 * Covers: empty directory, valid linear chain, missing predecessor,
 * duplicate identity, branching chain, cycle, multiple genesis,
 * disconnected sub-chain, deterministic ordering.
 *
 * @package SovereignStack\Core\MigrationRunner\Tests\Unit
 */
final class ChainValidatorTest extends TestCase
{
    private function makeDef(string $id, ?string $predecessor): MigrationDefinition
    {
        return new MigrationDefinition($id, $predecessor, "Migration {$id}", "/fake/{$id}.sql");
    }

    public function testEmptyDirectoryThrows(): void
    {
        $this->expectException(ChainValidationException::class);
        (new ChainValidator())->validate([]);
    }

    public function testValidLinearChain(): void
    {
        $m1 = $this->makeDef('001', null);
        $m2 = $this->makeDef('002', '001');
        $m3 = $this->makeDef('003', '002');

        [$count, $genesis] = (new ChainValidator())->validate([$m1, $m2, $m3]);

        $this->assertEquals(3, $count);
        $this->assertEquals('001', $genesis);
        $this->assertEquals(0, $m1->chainPosition);
        $this->assertEquals(1, $m2->chainPosition);
        $this->assertEquals(2, $m3->chainPosition);
    }

    public function testMissingPredecessorThrows(): void
    {
        $m1 = $this->makeDef('001', null);
        $m2 = $this->makeDef('002', 'nonexistent');

        $this->expectException(ChainValidationException::class);
        $this->expectExceptionMessage('missing predecessor');
        (new ChainValidator())->validate([$m1, $m2]);
    }

    public function testDuplicateIdentityThrows(): void
    {
        $m1 = $this->makeDef('001', null);
        $m2 = $this->makeDef('001', null); // Duplicate.

        $this->expectException(ChainValidationException::class);
        $this->expectExceptionMessage('Duplicate');
        (new ChainValidator())->validate([$m1, $m2]);
    }

    public function testBranchingChainThrows(): void
    {
        $m1 = $this->makeDef('001', null);
        $m2 = $this->makeDef('002', '001');
        $m3 = $this->makeDef('003', '001'); // Branch from same predecessor.

        $this->expectException(ChainValidationException::class);
        $this->expectExceptionMessage('Branching');
        (new ChainValidator())->validate([$m1, $m2, $m3]);
    }

    public function testCycleThrows(): void
    {
        $m1 = $this->makeDef('001', '003');
        $m2 = $this->makeDef('002', '001');
        $m3 = $this->makeDef('003', '002');

        $this->expectException(ChainValidationException::class);
        (new ChainValidator())->validate([$m1, $m2, $m3]);
    }

    public function testMultipleGenesisThrows(): void
    {
        $m1 = $this->makeDef('001', null);
        $m2 = $this->makeDef('002', null); // Two genesis migrations.

        $this->expectException(ChainValidationException::class);
        $this->expectExceptionMessage('Multiple genesis');
        (new ChainValidator())->validate([$m1, $m2]);
    }

    public function testNoGenesisThrows(): void
    {
        $m1 = $this->makeDef('001', '002');
        $m2 = $this->makeDef('002', '001');

        $this->expectException(ChainValidationException::class);
        $this->expectExceptionMessage('No genesis');
        (new ChainValidator())->validate([$m1, $m2]);
    }

    public function testDisconnectedSubchainThrows(): void
    {
        $m1 = $this->makeDef('001', null);
        $m2 = $this->makeDef('002', '001');
        $m3 = $this->makeDef('003', null); // Disconnected sub-chain.
        $m4 = $this->makeDef('004', '003');

        $this->expectException(ChainValidationException::class);
        (new ChainValidator())->validate([$m1, $m2, $m3, $m4]);
    }

    public function testDeterministicOrdering(): void
    {
        // Pass migrations in non-chain order; validator assigns correct positions.
        $m3 = $this->makeDef('003', '002');
        $m1 = $this->makeDef('001', null);
        $m2 = $this->makeDef('002', '001');

        [$count, $genesis] = (new ChainValidator())->validate([$m3, $m1, $m2]);

        $this->assertEquals(3, $count);
        $this->assertEquals('001', $genesis);
        $this->assertEquals(0, $m1->chainPosition);
        $this->assertEquals(1, $m2->chainPosition);
        $this->assertEquals(2, $m3->chainPosition);
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\MigrationRunner\Migration\ChainValidationException;
use SovereignStack\Core\MigrationRunner\Migration\SqlBlockParser;

/**
 * Unit tests for SqlBlockParser — line-based migration file parser.
 *
 * Validates: @META extraction, @STMT BEGIN/END block parsing, multi-block
 * upgrade split, postcondition parsing, and all malformed-input rejections.
 *
 * No MySQL required — pure string parsing.
 *
 * @package SovereignStack\Core\MigrationRunner\Tests\Unit
 */
final class SqlBlockParserTest extends TestCase
{
    private SqlBlockParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SqlBlockParser();
    }

    /**
     * Build a canonical migration with all block types.
     */
    private function fullMigration(): string
    {
        return <<<'SQL'
-- @META
migration_id: 2024_01_01_000001_create_users_table
predecessor: null
description: Create users table
-- @END

-- @STMT BEGIN upgrade
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
);

CREATE UNIQUE INDEX idx_users_email ON users (email);
-- @STMT END

-- @STMT BEGIN downgrade
DROP TABLE users;
-- @STMT END

-- @STMT BEGIN postconditions
-- table_exists: users
-- expect_count: SELECT COUNT(*) FROM users | 0
-- @STMT END
SQL;
    }

    public function testParseFullMigration(): void
    {
        $def = $this->parser->parse($this->fullMigration(), '<string>');

        $this->assertSame('2024_01_01_000001_create_users_table', $def->migrationId);
        $this->assertNull($def->predecessorId, 'predecessor "null" string should normalize to PHP null');
        $this->assertSame('Create users table', $def->description);
        $this->assertTrue($def->isGenesis(), 'null predecessor = genesis');
        $this->assertTrue($def->isDdlPolicy(), 'MVP: all migrations are DDL-policy');
        $this->assertSame('<string>', $def->filePath);
    }

    public function testParseUpgradeBlocksSplitOnBlankLines(): void
    {
        $def = $this->parser->parse($this->fullMigration(), '<string>');

        // Two non-empty blocks separated by a blank line.
        $this->assertCount(2, $def->upgradeBlocks, 'two upgrade blocks (CREATE TABLE; + CREATE INDEX;)');
        $this->assertStringContainsString('CREATE TABLE users', $def->upgradeBlocks[0]);
        $this->assertStringContainsString('CREATE UNIQUE INDEX', $def->upgradeBlocks[1]);
    }

    public function testParseDowngradeBlock(): void
    {
        $def = $this->parser->parse($this->fullMigration(), '<string>');

        $this->assertCount(1, $def->downgradeBlocks);
        $this->assertStringContainsString('DROP TABLE users', $def->downgradeBlocks[0]);
    }

    public function testParsePostconditions(): void
    {
        $def = $this->parser->parse($this->fullMigration(), '<string>');

        $this->assertCount(2, $def->postconditions);
        $this->assertSame('table_exists', $def->postconditions[0]['type']);
        $this->assertSame('users', $def->postconditions[0]['args']);
        $this->assertSame('expect_count', $def->postconditions[1]['type']);
        $this->assertStringContainsString('SELECT COUNT(*)', $def->postconditions[1]['args']);
    }

    public function testParseNonGenesisMigration(): void
    {
        $content = <<<'SQL'
-- @META
migration_id: 2024_01_01_000002_add_email_index
predecessor: 2024_01_01_000001_create_users_table
description: Add email index
-- @END

-- @STMT BEGIN upgrade
CREATE INDEX idx_users_email ON users (email);
-- @STMT END
SQL;
        $def = $this->parser->parse($content, '<string>');

        $this->assertSame('2024_01_01_000002_add_email_index', $def->migrationId);
        $this->assertSame('2024_01_01_000001_create_users_table', $def->predecessorId);
        $this->assertFalse($def->isGenesis());
    }

    public function testParseEmptyPredecessorNormalizesToNull(): void
    {
        $content = str_replace('predecessor: null', 'predecessor: ', $this->fullMigration());
        $def = $this->parser->parse($content, '<string>');
        $this->assertNull($def->predecessorId, 'empty predecessor string normalizes to null');
    }

    public function testThrowsOnMissingMetaBlock(): void
    {
        $this->expectException(ChainValidationException::class);
        $this->parser->parse("-- just a comment\n-- no meta here\n", '<string>');
    }

    public function testThrowsOnMissingMigrationId(): void
    {
        $content = "-- @META\ndescription: missing id test\n-- @END\n";
        $this->expectException(ChainValidationException::class);
        $this->parser->parse($content, '<string>');
    }

    public function testThrowsOnStmtBeginWithoutLabel(): void
    {
        $content = <<<'SQL'
-- @META
migration_id: test
predecessor: null
-- @END

-- @STMT BEGIN
SELECT 1;
-- @STMT END
SQL;
        $this->expectException(ChainValidationException::class);
        $this->parser->parse($content, '<string>');
    }

    public function testThrowsOnStmtEndWithoutBegin(): void
    {
        $content = <<<'SQL'
-- @META
migration_id: test
predecessor: null
-- @END

-- @STMT END
SELECT 1;
SQL;
        $this->expectException(ChainValidationException::class);
        $this->parser->parse($content, '<string>');
    }

    public function testThrowsOnUnterminatedStmtBegin(): void
    {
        $content = <<<'SQL'
-- @META
migration_id: test
predecessor: null
-- @END

-- @STMT BEGIN upgrade
SELECT 1;
SQL;
        $this->expectException(ChainValidationException::class);
        $this->parser->parse($content, '<string>');
    }

    public function testThrowsOnNestedStmtBegin(): void
    {
        $content = <<<'SQL'
-- @META
migration_id: test
predecessor: null
-- @END

-- @STMT BEGIN upgrade
-- @STMT BEGIN downgrade
SELECT 1;
-- @STMT END
-- @STMT END
SQL;
        $this->expectException(ChainValidationException::class);
        $this->parser->parse($content, '<string>');
    }

    public function testUnknownBlockLabelIgnoredForwardCompatible(): void
    {
        $content = <<<'SQL'
-- @META
migration_id: test
predecessor: null
-- @END

-- @STMT BEGIN future_label
SELECT 1;
-- @STMT END
SQL;
        $def = $this->parser->parse($content, '<string>');
        // Unknown label is silently ignored (forward-compatible per docblock).
        $this->assertSame('test', $def->migrationId);
        $this->assertEmpty($def->upgradeBlocks, 'unknown-label block not added to upgrade');
        $this->assertEmpty($def->downgradeBlocks);
    }

    public function testCrlfLineEndingsNormalized(): void
    {
        // Same content as fullMigration but with CRLF.
        $content = str_replace("\n", "\r\n", $this->fullMigration());
        $def = $this->parser->parse($content, '<string>');
        $this->assertSame('2024_01_01_000001_create_users_table', $def->migrationId);
        $this->assertCount(2, $def->upgradeBlocks, 'CRLF normalization preserves block splitting');
    }

    public function testParseFileReadsActualFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mig_') . '.sql';
        file_put_contents($tmpFile, $this->fullMigration());
        try {
            $def = $this->parser->parseFile($tmpFile);
            $this->assertSame('2024_01_01_000001_create_users_table', $def->migrationId);
            $this->assertSame($tmpFile, $def->filePath);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testParseFileThrowsOnUnreadableFile(): void
    {
        $this->expectException(ChainValidationException::class);
        $this->parser->parseFile('/nonexistent/path/migration.sql');
    }
}

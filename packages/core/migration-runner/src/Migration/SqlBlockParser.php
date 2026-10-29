<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Migration;

/**
 * Parses migration files using the @STMT BEGIN/END block format.
 *
 * Controlling design: Z.ai Focused Revision 5.3 (carrying Rev 3 §6.1,
 * Rev 5 §6.4 — SQL validation guarantee boundary).
 *
 * The parser is LINE-BASED, not a SQL parser. A line whose stripped content
 * is exactly '-- @STMT BEGIN <label>' or '-- @STMT END' is a delimiter.
 * Everything else is block content. The parser does NOT claim to know whether
 * block content contains one statement or several — the driver contract
 * (multi-statement execution disabled, EMULATE_PREPARES off per the existing
 * DBAL Connection) enforces one-statement-per-dispatch at execution time.
 *
 * Supported block labels: upgrade, downgrade, postconditions, meta.
 * Marker-like lines inside string literals or comments are an authoring
 * convention — not guaranteed by this parser (Rev 3 §6.1).
 *
 * @package SovereignStack\Core\MigrationRunner\Migration
 */
final class SqlBlockParser
{
    private const string META_BEGIN = '-- @META';
    private const string META_END = '-- @END';
    private const string STMT_BEGIN = '-- @STMT BEGIN';
    private const string STMT_END = '-- @STMT END';

    /**
     * Parse a migration file into a MigrationDefinition.
     *
     * @throws ChainValidationException On malformed metadata or block structure.
     */
    public function parseFile(string $filePath): MigrationDefinition
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            throw ChainValidationException::malformedMetadata($filePath, 'file not readable');
        }

        return $this->parse($content, $filePath);
    }

    /**
     * Parse migration content into a MigrationDefinition.
     */
    public function parse(string $content, string $filePath = '<string>'): MigrationDefinition
    {
        // Normalize line endings.
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Extract metadata block first.
        $meta = $this->extractMetaBlock($content, $filePath);
        if ($meta === null) {
            throw ChainValidationException::malformedMetadata($filePath, 'no -- @META block found');
        }

        $migrationId = $meta['migration_id'] ?? null;
        if ($migrationId === null) {
            throw ChainValidationException::malformedMetadata($filePath, 'missing migration_id in metadata');
        }

        $predecessor = $meta['predecessor'] ?? null;
        if ($predecessor === 'null' || $predecessor === '') {
            $predecessor = null;
        }

        $description = $meta['description'] ?? '';

        $def = new MigrationDefinition($migrationId, $predecessor, $description, $filePath);

        // Extract @STMT blocks.
        $blocks = $this->extractStmtBlocks($content, $filePath);

        foreach ($blocks as $label => $blockContent) {
            $lines = array_filter(explode("\n", $blockContent), fn($l) => trim($l) !== '');
            switch ($label) {
                case 'upgrade':
                    $def->upgradeBlocks = $this->splitBlocks($lines);
                    break;
                case 'downgrade':
                    $def->downgradeBlocks = $this->splitBlocks($lines);
                    break;
                case 'postconditions':
                    $def->postconditions = $this->parsePostconditions($lines);
                    break;
                default:
                    // Unknown block labels are ignored (forward-compatible).
                    break;
            }
        }

        return $def;
    }

    /**
     * Extract the -- @META ... -- @END block.
     * @return array<string, string>|null
     */
    private function extractMetaBlock(string $content, string $filePath): ?array
    {
        $lines = explode("\n", $content);
        $inMeta = false;
        $metaLines = [];

        foreach ($lines as $line) {
            $stripped = trim($line);

            if ($stripped === self::META_BEGIN) {
                $inMeta = true;
                continue;
            }
            if ($inMeta && $stripped === self::META_END) {
                break;
            }
            if ($inMeta) {
                $metaLines[] = $line;
            }
        }

        if (count($metaLines) === 0) {
            return null;
        }

        $meta = [];
        foreach ($metaLines as $line) {
            $line = trim($line);
            // Strip leading -- comment marker.
            if (str_starts_with($line, '--')) {
                $line = trim(substr($line, 2));
            }
            if ($line === '') continue;

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $meta[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $meta;
    }

    /**
     * Extract all @STMT BEGIN label ... @STMT END blocks.
     * @return array<string, string> label => block content.
     */
    private function extractStmtBlocks(string $content, string $filePath): array
    {
        $lines = explode("\n", $content);
        $blocks = [];
        $currentLabel = null;
        $currentLines = [];

        foreach ($lines as $line) {
            $stripped = trim($line);

            if (str_starts_with($stripped, self::STMT_BEGIN)) {
                // Extract label from "-- @STMT BEGIN upgrade" → "upgrade"
                $labelPart = trim(substr($stripped, strlen(self::STMT_BEGIN)));
                if ($labelPart === '') {
                    throw ChainValidationException::malformedMetadata(
                        $filePath, '@STMT BEGIN without a label'
                    );
                }
                if ($currentLabel !== null) {
                    throw ChainValidationException::malformedMetadata(
                        $filePath, 'nested @STMT BEGIN blocks (missing @STMT END)'
                    );
                }
                $currentLabel = $labelPart;
                $currentLines = [];
                continue;
            }

            if ($stripped === self::STMT_END) {
                if ($currentLabel === null) {
                    throw ChainValidationException::malformedMetadata(
                        $filePath, '@STMT END without matching @STMT BEGIN'
                    );
                }
                $blocks[$currentLabel] = implode("\n", $currentLines);
                $currentLabel = null;
                $currentLines = [];
                continue;
            }

            if ($currentLabel !== null) {
                $currentLines[] = $line;
            }
        }

        if ($currentLabel !== null) {
            throw ChainValidationException::malformedMetadata(
                $filePath, 'unterminated @STMT BEGIN block (missing @STMT END)'
            );
        }

        return $blocks;
    }

    /**
     * Split block content into individual statement blocks.
     * Each block is separated by a blank line or by being on its own line set.
     * The runner dispatches one block at a time; the driver contract ensures
     * one-statement-per-dispatch (Rev 3 §6.3).
     *
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function splitBlocks(array $lines): array
    {
        $blocks = [];
        $currentBlock = [];

        foreach ($lines as $line) {
            $stripped = trim($line);
            if ($stripped === '') {
                // Blank line: block boundary.
                if (count($currentBlock) > 0) {
                    $blocks[] = trim(implode("\n", $currentBlock));
                    $currentBlock = [];
                }
            } else {
                $currentBlock[] = $line;
            }
        }

        if (count($currentBlock) > 0) {
            $blocks[] = trim(implode("\n", $currentBlock));
        }

        return $blocks;
    }

    /**
     * Parse postcondition assertion lines.
     * Format: -- assertion_type: sql_or_args
     * Examples:
     *   -- table_exists: users
     *   -- expect_empty: SELECT 1 FROM users WHERE email IS NULL LIMIT 1
     *   -- expect_count: SELECT COUNT(*) FROM users | 5
     *
     * @param array<int, string> $lines
     * @return array<int, array{type: string, args: string}>
     */
    private function parsePostconditions(array $lines): array
    {
        $assertions = [];
        foreach ($lines as $line) {
            $stripped = trim($line);
            // Strip leading -- comment marker.
            if (str_starts_with($stripped, '--')) {
                $stripped = trim(substr($stripped, 2));
            }
            if ($stripped === '') continue;

            $parts = explode(':', $stripped, 2);
            if (count($parts) === 2) {
                $assertions[] = [
                    'type' => trim($parts[0]),
                    'args' => trim($parts[1]),
                ];
            }
        }
        return $assertions;
    }
}

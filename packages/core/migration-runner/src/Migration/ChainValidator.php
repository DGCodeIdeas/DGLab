<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Migration;

/**
 * Validates that a set of migrations forms a single linear chain.
 *
 * Controlling design: Z.ai Focused Revision 5.3 §6.1 (carrying Rev 4 §6.1).
 *
 * MVP constraint: migrations form a LINEAR chain. Each migration declares
 * exactly one predecessor (or null for the genesis migration). The validator
 * rejects:
 *   - Missing predecessor (a migration references a predecessor that doesn't exist)
 *   - Duplicate migration identities
 *   - Branching (a migration has multiple direct successors)
 *   - Cycles (the chain loops back on itself)
 *   - Multiple genesis migrations (more than one migration with predecessor = null)
 *   - Zero migrations (the directory is empty — handled by Discovery, not here)
 *
 * @package SovereignStack\Core\MigrationRunner\Migration
 */
final class ChainValidator
{
    /**
     * @param array<MigrationDefinition> $migrations
     * @return array{0: int, 1: string|null} Tuple of [chainPosition, genesisMigrationId].
     *         Returns [0, null] if the chain is empty.
     * @throws ChainValidationException On any validation failure.
     */
    public function validate(array $migrations): array
    {
        if (empty($migrations)) {
            return [0, null];
        }

        // Build a map: migration_id => MigrationDefinition.
        $byId = [];
        foreach ($migrations as $m) {
            if (isset($byId[$m->migrationId])) {
                throw ChainValidationException::duplicateIdentity($m->migrationId);
            }
            $byId[$m->migrationId] = $m;
        }

        // Find genesis migrations (predecessor = null).
        $genesis = array_filter($migrations, fn(MigrationDefinition $m) => $m->predecessorId === null);

        if (count($genesis) === 0) {
            throw ChainValidationException::noGenesis();
        }
        if (count($genesis) > 1) {
            $ids = array_map(fn(MigrationDefinition $m) => $m->migrationId, $genesis);
            throw ChainValidationException::multipleGenesis($ids);
        }

        // Check predecessors resolve and detect branching (multiple successors).
        $successorCount = []; // predecessorId => count of successors
        foreach ($migrations as $m) {
            if ($m->predecessorId !== null) {
                if (!isset($byId[$m->predecessorId])) {
                    throw ChainValidationException::missingPredecessor($m->migrationId, $m->predecessorId);
                }
                $successorCount[$m->predecessorId] = ($successorCount[$m->predecessorId] ?? 0) + 1;
            }
        }

        foreach ($successorCount as $predecessorId => $count) {
            if ($count > 1) {
                throw ChainValidationException::branching($predecessorId, $count);
            }
        }

        // Walk the chain from genesis to detect cycles and assign positions.
        $visited = [];
        $position = 0;
        $current = array_values($genesis)[0];
        $genesisId = $current->migrationId;

        while ($current !== null) {
            if (isset($visited[$current->migrationId])) {
                throw ChainValidationException::cycle($current->migrationId);
            }
            $visited[$current->migrationId] = true;
            $current->chainPosition = $position;
            $position++;

            // Find the migration whose predecessor is the current one.
            $next = null;
            foreach ($migrations as $m) {
                if ($m->predecessorId === $current->migrationId) {
                    $next = $m;
                    break;
                }
            }
            $current = $next;
        }

        // Every migration must have been visited (no disconnected sub-chains).
        if (count($visited) !== count($migrations)) {
            $unvisited = array_diff_key($byId, $visited);
            $ids = array_keys($unvisited);
            throw ChainValidationException::disconnected($ids);
        }

        return [$position, $genesisId];
    }
}

/**
 * Thrown when the migration chain is not a valid linear chain.
 */
final class ChainValidationException extends \RuntimeException
{
    public static function duplicateIdentity(string $id): self
    {
        return new self("Duplicate migration identity: '{$id}'");
    }

    public static function noGenesis(): self
    {
        return new self('No genesis migration found (every migration has a predecessor; the chain has no root)');
    }

    /**
     * @param array<int,string> $ids
     */
    public static function multipleGenesis(array $ids): self
    {
        return new self('Multiple genesis migrations (predecessor = null): ' . implode(', ', $ids));
    }

    public static function missingPredecessor(string $migrationId, string $predecessorId): self
    {
        return new self("Migration '{$migrationId}' references missing predecessor '{$predecessorId}'");
    }

    public static function branching(string $predecessorId, int $successorCount): self
    {
        return new self("Branching chain: migration '{$predecessorId}' has {$successorCount} successors (MVP supports linear chain only)");
    }

    public static function cycle(string $migrationId): self
    {
        return new self("Cycle detected at migration '{$migrationId}' (the chain loops back)");
    }

    /**
     * @param array<int,string> $ids
     */
    public static function disconnected(array $ids): self
    {
        return new self('Disconnected migrations (not reachable from genesis): ' . implode(', ', $ids));
    }

    public static function emptyDirectory(): self
    {
        return new self('Migration directory is empty (no migration files found)');
    }

    public static function malformedMetadata(string $file, string $reason): self
    {
        return new self("Malformed migration metadata in '{$file}': {$reason}");
    }
}

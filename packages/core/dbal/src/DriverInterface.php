<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * Backend feature-detection contract.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Database
 */
interface DriverInterface
{
    /**
     * Get the driver name ('mysql', 'pgsql', 'sqlite').
     */
    public function getName(): string;

    /**
     * Check if a feature is supported by this driver.
     *
     * @param string $feature Feature name (e.g. 'json', 'upsert', 'jsonb',
     *     'partial_index', 'rls', 'returning', 'timezone_offset').
     */
    public function supports(string $feature): bool;

    /**
     * Quote an identifier (table/column name) for this driver.
     */
    public function quoteIdentifier(string $name): string;
}

<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Driver;

use SovereignStack\Core\Database\DriverInterface;

/**
 * MySQL driver — default backend (ADR-013).
 *
 * @package SovereignStack\Core\Database\Driver
 */
final class MysqlDriver implements DriverInterface
{
    private const SUPPORTED = [
        'json' => true,
        'upsert' => true,
        'jsonb' => false,
        'partial_index' => false,
        'rls' => false,
        'returning' => false,
        'timezone_offset' => false,
    ];

    public function getName(): string
    {
        return 'mysql';
    }

    public function supports(string $feature): bool
    {
        return self::SUPPORTED[$feature] ?? false;
    }

    public function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}

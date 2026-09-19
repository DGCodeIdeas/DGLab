<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * PHP-type <-> SQL-type mapper.
 *
 * @package SovereignStack\Core\Database
 */
final class TypeMapper
{
    /**
     * Map a PHP value to a SQL-bindable form.
     */
    public function toSql(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s.u'),
            $value instanceof \DateInterval => $value->format('P%yY%mM%dDT%hH%iM%sS'),
            is_bool($value) => $value ? 1 : 0,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
            is_object($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }

    /**
     * Map a fetched SQL value back to a PHP type.
     */
    public function fromSql(string $sqlType, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $strValue = is_string($value) ? $value : (string) $value;

        return match ($sqlType) {
            'timestamp', 'datetime' => \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s.u',
                $strValue,
            ) ?: new \DateTimeImmutable($strValue),
            'bool', 'tinyint' => (bool) (int) $strValue,
            'json' => json_decode($strValue, true) ?? [],
            default => $value,
        };
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * Marker exception for all DBAL errors.
 *
 * Wraps \PDOException with a structured message (SQL state, driver message,
 * the offending SQL hash — never the full SQL with parameter values).
 *
 * @package SovereignStack\Core\Database
 */
final class DatabaseException extends \RuntimeException
{
    private ?string $sqlHash;

    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $sqlHash = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->sqlHash = $sqlHash;
    }

    public function getSqlHash(): ?string
    {
        return $this->sqlHash;
    }

    public static function fromPdoError(\PDOException $e, ?string $sqlHash = null): self
    {
        $message = $e->getMessage();
        $code = (int) ($e->getCode() ?: 0);

        return new self(
            sprintf('Database error [%s]: %s', $sqlHash ?? 'unknown', $message),
            $code,
            $e,
            $sqlHash,
        );
    }
}

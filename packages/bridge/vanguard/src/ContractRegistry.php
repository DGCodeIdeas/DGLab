<?php

declare(strict_types=1);

namespace SovereignStack\Bridge;

/**
 * In-process map of public route → DtoTransformerInterface.
 *
 * registerContract() is called once at boot. The registry is immutable after
 * the first resolve() call — a runtime-registered backdoor contract is impossible.
 *
 * @package SovereignStack\Bridge
 */
final class ContractRegistry
{
    /** @var array<string, DtoTransformerInterface> */
    private array $contracts = [];

    private bool $frozen = false;

    /**
     * @param string $contractId Route identifier matching ^[a-z0-9_.\/]{3,128}$.
     * @throws \InvalidArgumentException If $contractId is malformed.
     * @throws \LogicException If called after the first resolve() call.
     */
    public function registerContract(string $contractId, DtoTransformerInterface $transformer): void
    {
        if ($this->frozen) {
            throw new \LogicException(
                'ContractRegistry is immutable after the first resolve() call. '
                . 'A runtime-registered contract would be a backdoor.',
            );
        }

        if (!preg_match('/^[a-z0-9_.\/]{3,128}$/', $contractId)) {
            throw new \InvalidArgumentException(
                \sprintf('Contract ID [%s] does not match required pattern ^[a-z0-9_.\/]{3,128}$.', $contractId),
            );
        }

        $this->contracts[$contractId] = $transformer;
    }

    /**
     * Resolve the transformer for a route. Returns null for unregistered routes
     * (default-deny). The first call freezes the registry.
     */
    public function resolve(string $route): ?DtoTransformerInterface
    {
        $this->frozen = true;
        return $this->contracts[$route] ?? null;
    }

    /**
     * Whether the registry has been frozen (first resolve() called).
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Whether a contract is registered for the given route (does NOT freeze).
     */
    public function has(string $route): bool
    {
        return isset($this->contracts[$route]);
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Bridge;

use Psr\Http\Server\MiddlewareInterface;

/**
 * The architectural enforcement contract for the SovereignStack tier boundary.
 *
 * Every public route that crosses from the External Spoke tier to an Internal
 * service MUST be registered as a BoundaryContract before it can receive
 * traffic. Unregistered routes are denied by default (HTTP 403).
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Bridge
 */
interface BoundaryContractInterface extends MiddlewareInterface
{
    /**
     * Register a permitted crossing contract.
     *
     * @param string                  $contractId  Route identifier matching `^[a-z0-9_.\/]{3,128}$`.
     * @param DtoTransformerInterface $transformer The transformer for this route.
     *
     * @throws \InvalidArgumentException If $contractId is malformed.
     * @throws \LogicException           If called after the first process() call (immutable after boot).
     */
    public function registerContract(string $contractId, DtoTransformerInterface $transformer): void;
}

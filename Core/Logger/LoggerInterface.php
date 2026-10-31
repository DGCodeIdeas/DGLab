<?php

declare(strict_types=1);

namespace SovereignStack\Core\Logger;

/**
 * LoggerInterface extends PSR-3 with handler management.
 *
 * The PSR-3 LoggerInterface is implemented as-is for the eight log-level
 * methods (debug through emergency). This interface adds:
 *   - {@see withHandler()} for fluent handler registration
 *   - {@see withThreshold()} for level filtering
 *
 * Instances are immutable — adding a handler returns a new Logger with
 * the additional handler. This matches ADR-017's worker-scope discipline:
 * the logger is built once at worker boot and never mutated.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Logger
 */
interface LoggerInterface extends \Psr\Log\LoggerInterface
{
    /**
     * Return a new Logger with $handler appended to the handler stack.
     *
     * The original Logger is unchanged. Handlers are invoked in registration order.
     */
    public function withHandler(HandlerInterface $handler): static;

    /**
     * Return a new Logger that filters out records below $threshold.
     *
     * @param string $threshold PSR-3 LogLevel constant.
     */
    public function withThreshold(string $threshold): static;

    /**
     * The current minimum log level. Records below this level are not dispatched.
     */
    public function threshold(): string;

    /**
     * The handler stack (in invocation order).
     *
     * @return array<HandlerInterface>
     */
    public function handlers(): array;
}

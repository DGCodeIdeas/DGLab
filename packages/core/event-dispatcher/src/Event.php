<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class Event implements StoppableEventInterface
{
    /** @var bool Whether event propagation has been halted. */
    private bool $propagationStopped = false;

    /**
     * Immutable stamp audit trail.
     *
     * Each entry is [key, value, microtime]. Stamps are append-only;
     * callers cannot modify or remove prior stamps.
     *
     * @var list<array{string, mixed, float}>
     */
    private array $stamps = [];

    /**
     * Halt further listener execution for this event.
     *
     * Once called, the dispatcher will cease iterating over the
     * remaining listeners in the pipeline for this event.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Check whether propagation has been requested to stop.
     *
     * @return bool True if stopPropagation() has been called.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Attach an immutable audit stamp to the event.
     *
     * Stamps are recorded with microsecond precision and cannot be
     * removed or mutated after attachment. Returns $this for fluent
     * chaining within listeners.
     *
     * @param string $key The stamp identifier (e.g., 'audit', 'trace').
     * @param mixed $value The stamp payload.
     * @return static
     */
    public function stamp(string $key, mixed $value): static
    {
        $this->stamps[] = [$key, $value, microtime(true)];

        return $this;
    }

    /**
     * Retrieve a specific stamp value by key.
     *
     * Returns the value of the most recent stamp matching the given key,
     * or $default if no such stamp exists.
     *
     * @param string $key The stamp identifier to look up.
     * @param mixed $default The default value if the key is not found.
     * @return mixed
     */
    public function getStamp(string $key, mixed $default = null): mixed
    {
        // Walk backwards to find the most recent stamp for this key
        for ($i = count($this->stamps) - 1; $i >= 0; $i--) {
            if ($this->stamps[$i][0] === $key) {
                return $this->stamps[$i][1];
            }
        }

        return $default;
    }

    /**
     * Retrieve all stamps attached to this event.
     *
     * @return list<array{string, mixed, float}> The full stamp audit trail.
     */
    public function getStamps(): array
    {
        return $this->stamps;
    }

    /**
     * Check whether the event has any stamps.
     */
    public function hasStamps(): bool
    {
        return $this->stamps !== [];
    }
}

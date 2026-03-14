<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\StoppableEventInterface;

/**
 * Class BaseEvent
 *
 * Provides a base implementation for events, including propagation control.
 */
abstract class BaseEvent implements StoppableEventInterface
{
    /**
     * @var bool Whether the propagation has been stopped.
     */
    protected bool $propagationStopped = false;

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @inheritDoc
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}

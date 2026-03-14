<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\StoppableEventInterface;

/**
 * Class BaseEvent
 *
 * Provides a base implementation for events, including propagation control and aliasing.
 */
abstract class BaseEvent implements StoppableEventInterface
{
    /**
     * @var bool Whether the propagation has been stopped.
     */
    protected bool $propagationStopped = false;

    /**
     * @var string|null The cached alias.
     */
    protected ?string $alias = null;

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
    public function getAlias(): string
    {
        if ($this->alias === null) {
            $this->alias = $this->generateAlias();
        }

        return $this->alias;
    }

    /**
     * Generate a default alias based on the class name.
     *
     * Converts "DGLab\Events\User\LoginEvent" to "user.login".
     *
     * @return string
     */
    protected function generateAlias(): string
    {
        $className = static::class;

        // Strip namespace
        if (($pos = strrpos($className, '\\')) !== false) {
            $className = substr($className, $pos + 1);
        }

        // Strip "Event" suffix if present
        if (str_ends_with($className, 'Event')) {
            $className = substr($className, 0, -5);
        }

        // Convert CamelCase to dot.notation
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '.$0', $className));
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

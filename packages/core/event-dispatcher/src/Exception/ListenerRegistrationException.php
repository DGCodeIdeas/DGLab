<?php

declare(strict_types=1);

namespace SovereignStack\Events\Exception;

use InvalidArgumentException;

final class ListenerRegistrationException extends InvalidArgumentException
{
    /**
     * Create an exception for when the specified event class does not exist.
     *
     * @param string $eventClass The invalid event class name.
     */
    public static function eventClassNotFound(string $eventClass): self
    {
        return new self(
            sprintf('Event class "%s" does not exist or is not a valid class.', $eventClass)
        );
    }

    /**
     * Create an exception for when a listener class name does not exist.
     *
     * @param string $listenerClass The invalid listener class name.
     */
    public static function listenerClassNotFound(string $listenerClass): self
    {
        return new self(
            sprintf('Listener class "%s" does not exist or is not a valid class.', $listenerClass)
        );
    }

    /**
     * Create an exception for when a listener is neither a class name nor a callable.
     *
     * @param string $eventClass The event class being registered for.
     */
    public static function invalidListener(string $eventClass): self
    {
        return new self(
            sprintf(
                'Listener for event "%s" must be a valid class name (string) or a callable.',
                $eventClass
            )
        );
    }
}

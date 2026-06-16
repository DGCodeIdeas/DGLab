<?php

declare(strict_types=1);

namespace SovereignStack\Events\Exception;

use RuntimeException;

final class EventDispatchException extends RuntimeException
{
    /**
     * Create an exception for when a listener fails during dispatch.
     *
     * @param class-string $eventClass The event class being dispatched.
     * @param class-string|string $listenerClass The listener that threw.
     * @param string $message The underlying error message.
     * @param int $code The underlying error code.
     */
    public static function listenerFailed(
        string $eventClass,
        string $listenerClass,
        string $message,
        int $code = 0
    ): self {
        return new self(
            sprintf(
                'Listener "%s" failed while processing event "%s": %s',
                $listenerClass,
                $eventClass,
                $message
            ),
            $code
        );
    }
}

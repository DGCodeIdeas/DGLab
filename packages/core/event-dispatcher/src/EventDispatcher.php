<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher;

use Psr\Log\LoggerInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use SovereignStack\Core\EventDispatcher\Exception\EventDispatchException;

final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @param ListenerProviderInterface $provider The listener provider that maps events to callables.
     * @param LoggerInterface|null $logger Optional PSR-3 logger for recording listener failures.
     */
    public function __construct(
        private readonly ListenerProviderInterface $provider,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * Listeners execute in priority order (highest first). For stoppable
     * events, propagation halts immediately when isPropagationStopped()
     * returns true. Any listener that throws is caught, logged, and the
     * pipeline continues with the next listener — a single failing
     * listener never crashes the dispatcher.
     *
     * @param object $event The event to dispatch.
     * @return object The (potentially mutated) event after listener processing.
     */
    public function dispatch(object $event): object
    {
        $listeners = $this->provider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            try {
                $listener($event);
            } catch (\Throwable $e) {
                $this->handleListenerFailure($event, $listener, $e);

                // Continue to the next listener — error isolation
            }
        }

        return $event;
    }

    /**
     * Handle a listener that threw an exception during dispatch.
     *
     * Logs the failure if a logger is available. The exception is caught
     * and does not propagate; this ensures error isolation between
     * listeners in the pipeline.
     *
     * @param object $event The event being dispatched.
     * @param callable $listener The listener that failed.
     * @param \Throwable $exception The exception thrown by the listener.
     */
    private function handleListenerFailure(object $event, callable $listener, \Throwable $exception): void
    {
        if ($this->logger === null) {
            return;
        }

        $listenerDescription = $this->describeListener($listener);

        $dispatchException = EventDispatchException::listenerFailed(
            $event::class,
            $listenerDescription,
            $exception->getMessage(),
            (int) $exception->getCode()
        );

        $this->logger->error(
            $dispatchException->getMessage(),
            [
                'event' => $event::class,
                'listener' => $listenerDescription,
                'exception' => $exception::class,
                'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * Produce a human-readable description of a listener callable.
     *
     * Handles closures, invocable objects, class-method arrays,
     * and function strings.
     *
     * @param callable $listener
     * @return string
     */
    private function describeListener(callable $listener): string
    {
        if ($listener instanceof \Closure) {
            return 'Closure';
        }

        if (is_object($listener)) {
            return $listener::class;
        }

        if (is_array($listener) && isset($listener[0], $listener[1])) {
            $classPart = $listener[0];
            $methodPart = $listener[1];

            if (is_object($classPart)) {
                $class = $classPart::class;
            } elseif (is_scalar($classPart)) {
                $class = (string) $classPart;
            } else {
                return 'Unknown listener';
            }

            $method = is_string($methodPart) ? $methodPart : 'unknown';

            return $class . '::' . $method;
        }

        if (is_string($listener)) {
            return $listener;
        }

        return 'Unknown listener';
    }
}

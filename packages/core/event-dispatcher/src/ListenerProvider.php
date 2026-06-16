<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher;

use Psr\Container\ContainerInterface;
use SovereignStack\Core\EventDispatcher\Exception\EventDispatchException;
use SovereignStack\Core\EventDispatcher\Exception\ListenerRegistrationException;

final class ListenerProvider implements ListenerProviderInterface
{
    /**
     * Internal listener registry.
     *
     * Structure: [eventClass => [priority => [listenerString|callable, ...]]]
     *
     * @var array<class-string, array<int, list<string|callable>>>
     */
    private array $listeners = [];

    /**
     * Cached merged + sorted listeners per event class for performance.
     *
     * @var array<class-string, list<callable>>
     */
    private array $resolvedCache = [];

    /**
     * @param ContainerInterface|null $container Optional DI container for lazy listener resolution.
     */
    public function __construct(
        private readonly ?ContainerInterface $container = null
    ) {
    }

    /**
     * Register a listener for a specific event class.
     *
     * Validates that the event class exists and that the listener is
     * either a valid callable or a resolvable class name. Listeners
     * registered as class strings are resolved lazily from the
     * container when the event fires.
     *
     * @param class-string $eventClass The fully-qualified event class name.
     * @param class-string|callable $listener The listener class name or callable.
     * @param int $priority Higher values run first.
     *
     * @throws ListenerRegistrationException If the event class does not exist
     *                                       or the listener is invalid.
     */
    public function addListener(string $eventClass, string|callable $listener, int $priority = 0): void
    {
        if (!class_exists($eventClass) && !interface_exists($eventClass)) {
            throw ListenerRegistrationException::eventClassNotFound($eventClass);
        }

        if (is_string($listener)) {
            if (!class_exists($listener)) {
                throw ListenerRegistrationException::listenerClassNotFound($listener);
            }
        }
        // Non-string listeners are always callable due to the type declaration

        $this->listeners[$eventClass][$priority][] = $listener;

        // Invalidate cache for this event class and all its children
        $this->resolvedCache = [];
    }

    /**
     * Retrieve all listeners for an event, sorted by priority (highest first).
     *
     * Walks the full class hierarchy (parent classes and interfaces) so that
     * listeners registered for a parent type fire for child events. Listeners
     * registered as class strings are resolved through the container if available.
     *
     * @param object $event The event to find listeners for.
     * @return iterable<callable> Prioritized callables for the event.
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = $event::class;

        if (isset($this->resolvedCache[$eventClass])) {
            yield from $this->resolvedCache[$eventClass];

            return;
        }

        $resolved = $this->collectAndSortListeners($eventClass);

        $this->resolvedCache[$eventClass] = $resolved;

        yield from $resolved;
    }

    /**
     * Collect all listeners for a given event class, walking the type hierarchy.
     *
     * @param class-string $eventClass
     * @return list<callable>
     */
    private function collectAndSortListeners(string $eventClass): array
    {
        $collected = [];

        // Gather listeners from the event class itself and all ancestors
        foreach ($this->getTypeHierarchy($eventClass) as $type) {
            if (!isset($this->listeners[$type])) {
                continue;
            }

            foreach ($this->listeners[$type] as $priority => $listenerGroup) {
                $collected[$priority] = array_merge(
                    $collected[$priority] ?? [],
                    $listenerGroup
                );
            }
        }

        if ($collected === []) {
            return [];
        }

        // Sort by priority descending (highest first)
        krsort($collected, SORT_NUMERIC);

        $callables = [];

        foreach ($collected as $listenerGroup) {
            foreach ($listenerGroup as $listener) {
                $callables[] = $this->resolveListener($listener, $eventClass);
            }
        }

        return $callables;
    }

    /**
     * Get the full type hierarchy (class + parents + interfaces) for a class.
     *
     * @param class-string $class
     * @return list<class-string>
     */
    private function getTypeHierarchy(string $class): array
    {
        $hierarchy = [];

        // Walk class parents
        $current = $class;
        while ($current !== false) {
            $hierarchy[] = $current;
            $current = get_parent_class($current);
        }

        // Walk interfaces implemented by the class and its parents
        $interfaces = class_implements($class);
        if ($interfaces !== false) {
            foreach ($interfaces as $interface) {
                $hierarchy[] = $interface;
            }
        }

        return $hierarchy;
    }

    /**
     * Resolve a registered listener into a callable.
     *
     * If the listener is a class string and a container is present,
     * resolve it lazily. Otherwise, assume it is already a callable.
     *
     * @param string|callable $listener
     * @param class-string $eventClass
     * @return callable
     */
    private function resolveListener(string|callable $listener, string $eventClass): callable
    {
        if (!is_string($listener)) {
            return $listener;
        }

        // Lazy resolution via container (preferred)
        if ($this->container !== null) {
            try {
                $instance = $this->container->get($listener);
                if (is_callable($instance)) {
                    return $instance;
                }
                throw EventDispatchException::listenerFailed(
                    $eventClass,
                    $listener,
                    'Resolved listener is not callable (missing __invoke).'
                );
            } catch (EventDispatchException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw EventDispatchException::listenerFailed(
                    $eventClass,
                    $listener,
                    $e->getMessage(),
                    (int) $e->getCode()
                );
            }
        }

        // Fallback: direct instantiation when no container is available
        try {
            $instance = new $listener();
            if (is_callable($instance)) {
                return $instance;
            }
        } catch (\Throwable $e) {
            throw EventDispatchException::listenerFailed(
                $eventClass,
                $listener,
                $e->getMessage(),
                (int) $e->getCode()
            );
        }

        throw EventDispatchException::listenerFailed(
            $eventClass,
            $listener,
            'Resolved listener is not callable (missing __invoke).'
        );
    }
}

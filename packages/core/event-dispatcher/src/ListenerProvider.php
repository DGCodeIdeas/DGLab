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
     * Re-entrancy guard: tracks event classes whose listener list is
     * currently being resolved. Prevents infinite recursion and cache
     * stampedes when a listener (or container resolution side-effect)
     * dispatches the same event class during resolution.
     *
     * @var array<class-string, true>
     */
    private array $resolving = [];

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
     * Listener deduplication: a listener identical to one already
     * registered for the same event class + priority is silently
     * skipped. "Identical" means:
     *   - Same object/closure instance (=== identity check).
     *   - Same string (class name or function name).
     *   - Same array-shape callable (serialized comparison).
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
            if (class_exists($listener)) {
                // Class-string listener: resolved lazily via the container
                // or direct instantiation when getListenersForEvent() fires.
            } elseif (!is_callable($listener)) {
                // Not a class name AND not a callable function name — reject.
                throw ListenerRegistrationException::listenerClassNotFound($listener);
            }
        } else {
            // PHP's `callable` type hint rejects non-callables at the language
            // level (TypeError before our code runs), but is_callable()
            // provides defensive, explicit validation at registration time.
            if (!is_callable($listener)) {
                throw ListenerRegistrationException::invalidListener($eventClass);
            }
        }

        // Deduplicate: skip if an identical listener is already registered
        // for the same event class at the same priority. This prevents
        // accidental double-registration from cascading into duplicate
        // side-effects at dispatch time.
        $group = $this->listeners[$eventClass][$priority] ?? [];
        foreach ($group as $existing) {
            if ($this->isSameListener($existing, $listener)) {
                return; // Already registered — silent dedup.
            }
        }

        $this->listeners[$eventClass][$priority][] = $listener;

        // Invalidate only the cache entries that include this event class
        // (the event itself and all its subtypes). Previously this nuked the
        // entire cache, which is wasteful for boot-time registration.
        unset($this->resolvedCache[$eventClass]);
        // Also invalidate any cached entries for parent classes — a listener
        // registered for a parent event type will fire for child events.
        foreach (array_keys($this->resolvedCache) as $cachedClass) {
            if (is_subclass_of($cachedClass, $eventClass)) {
                unset($this->resolvedCache[$cachedClass]);
            }
        }
    }

    /**
     * Retrieve all listeners for an event, sorted by priority (highest first).
     *
     * Walks the full class hierarchy (parent classes and interfaces) so that
     * listeners registered for a parent type fire for child events. Listeners
     * registered as class strings are resolved through the container if available.
     *
     * Cache + stampede safety:
     *   - The cache is populated EAGERLY when iteration begins (the body
     *     runs to completion before yielding, writing the result to the
     *     cache in one step). The previous implementation only wrote the
     *     cache lazily during yield, leaving a window in which two
     *     concurrent get-listeners callers (e.g. across Fibers during
     *     container resolution) would both miss the cache and re-resolve
     *     the same list — a generator cache stampede.
     *   - A re-entrancy guard (`$resolving`) prevents infinite recursion
     *     when a listener dispatches the same event class during its own
     *     resolution: subsequent calls during the resolving phase yield
     *     an empty list rather than recursing into collectAndSortListeners().
     *
     * @param object $event The event to find listeners for.
     * @return iterable<callable> Prioritized callables for the event.
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = $event::class;

        // Eager resolution: compute + cache the list BEFORE yielding so
        // the cache is populated by the time iteration begins (and by the
        // time any re-entrant dispatch during resolution reads the cache).
        if (!isset($this->resolvedCache[$eventClass]) && !isset($this->resolving[$eventClass])) {
            $this->resolving[$eventClass] = true;
            try {
                $resolved = $this->collectAndSortListeners($eventClass);
                $this->resolvedCache[$eventClass] = $resolved;
            } finally {
                unset($this->resolving[$eventClass]);
            }
        }

        // Either the cache is populated now, OR we're in a re-entrant call
        // (another caller is mid-resolution) — yield from whatever the
        // cache has, possibly nothing.
        yield from $this->resolvedCache[$eventClass] ?? [];
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

    public function clearCache(): void
    {
        $this->resolvedCache = [];
        $this->resolving = [];
    }

    /**
     * Determine whether two listener registrations are "the same" for
     * deduplication purposes.
     *
     * Identity rules:
     *   - Strings (class name or function name): compared with strict ===.
     *   - Closures and invokable objects: compared by instance identity (===).
     *   - Array-shaped callables [class|obj, method]: serialized for comparison.
     *
     * @param string|callable $a
     * @param string|callable $b
     */
    private function isSameListener(string|callable $a, string|callable $b): bool
    {
        if (is_string($a) || is_string($b)) {
            return $a === $b;
        }
        if (is_array($a) && is_array($b)) {
            return serialize($a) === serialize($b);
        }
        // Objects / closures: identity check.
        return $a === $b;
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
     * Listener strings come in two flavors:
     *   - Class names (e.g. SampleListener::class): resolved via the
     *     container when present, otherwise instantiated directly. The
     *     resulting instance MUST be callable (have __invoke).
     *   - Function names (e.g. 'array_map'): NOT class names. Returned
     *     directly as callables — they do not need instantiation.
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

        // Function-name string (callable but not a class). Return as-is.
        // We validate via is_callable() — at registration we already
        // guaranteed that class_name OR is_callable holds, so any string
        // listener reaching this point is one or the other.
        if (!class_exists($listener)) {
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

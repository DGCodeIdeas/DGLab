<?php
declare(strict_types=1);

namespace SovereignStack\Core\Container;

/**
 * Immutable description of a single service binding.
 *
 * A `readonly class` (PHP 8.2+) — all properties are implicitly readonly.
 * Once constructed, a definition cannot be mutated; compiler passes that
 * need to alter a binding must replace it by calling `bind()` with the
 * same id.
 */
final readonly class ServiceDefinition
{
    /**
     * @param string       $abstract The service identifier (the id passed to bind()).
     * @param mixed        $concrete A class-string, a Closure receiving
     *                               ($container, $parameters), or a pre-built
     *                               object instance.
     * @param bool         $shared   True for singletons (cached after first resolution).
     * @param list<string> $tags     Optional tags for tagged-iterator passes.
     */
    public function __construct(
        public string $abstract,
        public mixed $concrete,
        public bool $shared = false,
        public array $tags = [],
    ) {
    }
}
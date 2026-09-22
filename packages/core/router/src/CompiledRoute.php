<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

final class CompiledRoute
{
    /**
     * @param Route               $route
     * @param string              $regex          Anchored PCRE regex, e.g. "#^/users/(?P<id>[^/]+)$#u".
     * @param list<string>        $placeholderNames Ordered list of {placeholder} names in the path.
     */
    public function __construct(
        public readonly Route $route,
        public readonly string $regex,
        public readonly array $placeholderNames,
    ) {}
}

/**
 * Default RouterInterface implementation. Compiled routes are indexed by HTTP
 * method for O(1) bucket selection on match(). Within a bucket, iteration is
 * linear in registration order; first regex hit wins. For >1,000 routes a
 * future trie-based matcher (FastRoute-style) can be substituted behind the
 * same RouterInterface.
 *
 * The router freezes after the first match() call: subsequent addRoute()
 * throws LogicException (same immutability invariant as CORE-05's
 * MiddlewarePipeline, for the same reason).
 */

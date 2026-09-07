<?php
declare(strict_types=1);

/**
 * Stub RouterInterface for CORE-06 (not yet implemented).
 * This file exists so CORE-05 can compile and pass PHPStan without
 * the actual CORE-06 package. When CORE-06 ships, this stub is
 * replaced by the real packages/core/router/src/RouterInterface.php.
 *
 * The interface is defined in the SovereignStack\Core\Router namespace
 * so both CORE-05 and CORE-06 reference the same type.
 */

namespace SovereignStack\Core\Router;

use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    /**
     * Resolve the request to a RouteResult, or null on no match.
     *
     * @return RouteResult|null
     */
    public function match(ServerRequestInterface $request): ?RouteResult;

    /**
     * Generate a URL path from a named route and a parameter map.
     *
     * @param string $name
     * @param array<string, string> $parameters
     * @param array<string, string> $query
     * @return string
     */
    public function generateUrl(string $name, array $parameters = [], array $query = []): string;
}

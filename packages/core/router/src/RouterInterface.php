<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

use Psr\Http\Message\ServerRequestInterface;


interface RouterInterface
{
    /**
     * Register a Route. Compiles via RouteCompiler.
     *
     * @throws \LogicException                                          If called after match() has been invoked.
     * @throws Exception\DuplicateRouteNameException                   If $route->name is non-empty and already registered.
     * @throws Exception\InvalidRoutePatternException                  If $route->path is malformed.
     */
    public function addRoute(Route $route): void;

    /**
     * Resolve the request to a RouteResult, or null on no match.
     *
     * Matching order: HTTP method filter first, then regex iteration in
     * registration order; first hit wins. Route parameters are URL-decoded
     * EXACTLY ONCE — controllers MUST NOT call urldecode() again.
     *
     * @return RouteResult|null Null if no route matches (caller emits 404);
     *                          also null if path matches but method does not
     *                          (caller may re-query via matchIgnoringMethod()
     *                          to emit 405 with an Allow: header).
     */
    public function match(ServerRequestInterface $request): ?RouteResult;

    /**
     * Generate a URL path from a named route and a parameter map.
     *
     * Inverse of match(): substitutes parameters into the route's original
     * pattern with percent-encoding. Missing required parameters throw;
     * extra parameters are appended as an RFC-3986 query string.
     *
     * @param string               $name        Registered route name.
     * @param array<string,string> $parameters  Parameter values (each rawurlencode()'d).
     * @param array<string,string> $query       Optional query-string parameters.
     *
     * @throws Exception\RouteNotFoundException         If $name is not registered.
     * @throws Exception\MissingRouteParameterException  If a required placeholder is absent.
     */
    public function generateUrl(string $name, array $parameters = [], array $query = []): string;
}

/**
 * Immutable value object: a route declaration. Constructed by AttributeRouteLoader
 * from a #[Route] attribute plus reflected controller/method metadata.
 */

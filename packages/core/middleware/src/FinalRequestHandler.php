<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerInterface;

final class FinalRequestHandler implements FinalRequestHandlerInterface
{
    private ?\SovereignStack\Core\Router\RouterInterface $router = null;

    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function withRouter(\SovereignStack\Core\Router\RouterInterface $router): static
    {
        $clone = clone $this;
        $clone->router = $router;
        return $clone;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->router === null) {
            throw new \LogicException(
                'FinalRequestHandler has no router configured. Call withRouter() at kernel boot.'
            );
        }

        $match = $this->router->match($request);
        if ($match === null) {
            return new Response(404, reasonPhrase: 'Not Found');
        }

        $controller = $this->container->get($match->route->controllerClass);
        return $controller->{$match->route->controllerMethod}(
            $request->withAttribute('__route_match', $match),
        );
    }
}

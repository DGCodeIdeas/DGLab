<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Server\RequestHandlerInterface;

interface FinalRequestHandlerInterface extends RequestHandlerInterface
{
    /**
     * Configure the router used for terminal dispatch. Called once at kernel boot.
     */
    public function withRouter(\SovereignStack\Core\Router\RouterInterface $router): static;
}

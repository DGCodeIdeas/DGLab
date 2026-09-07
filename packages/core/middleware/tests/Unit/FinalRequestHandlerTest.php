<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Http\FinalRequestHandler;
use SovereignStack\Core\Http\Response;

final class FinalRequestHandlerTest extends TestCase
{
    public function testHandleWithNoRouterThrowsLogicException(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $handler = new FinalRequestHandler($container);

        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(\LogicException::class);
        $handler->handle($request);
    }

    public function testWithRouterReturnsNewInstance(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $handler = new FinalRequestHandler($container);

        // RouterInterface doesn't exist yet (CORE-06), use a mock
        $router = $this->createMock(\SovereignStack\Core\Router\RouterInterface::class);

        $new = $handler->withRouter($router);

        self::assertNotSame($handler, $new);
        self::assertInstanceOf(FinalRequestHandler::class, $new);
    }

    public function testWithRouterIsImmutable(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $handler = new FinalRequestHandler($container);

        $router = $this->createMock(\SovereignStack\Core\Router\RouterInterface::class);

        $new = $handler->withRouter($router);

        // Original should still throw — it doesn't have the router
        $request = $this->createMock(ServerRequestInterface::class);
        $this->expectException(\LogicException::class);
        $handler->handle($request);
    }
}

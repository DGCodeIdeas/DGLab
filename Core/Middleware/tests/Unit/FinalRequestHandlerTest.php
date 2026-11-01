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
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\RouteResult;

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

    public function testHandleRejectsNonCallableControllerMethod(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('get')
            ->with('TestController')
            ->willReturn(new \stdClass());

        $router = $this->createMock(\SovereignStack\Core\Router\RouterInterface::class);
        $router->method('match')->willReturn(self::routeResult('missingMethod'));

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturnSelf();
        $handler = (new FinalRequestHandler($container))->withRouter($router);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Route controller method is not callable');
        $handler->handle($request);
    }

    public function testHandleRejectsControllerResultThatIsNotResponse(): void
    {
        $controller = new class {
            public function dispatch(ServerRequestInterface $request): string
            {
                return 'not a response';
            }
        };
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($controller);

        $router = $this->createMock(\SovereignStack\Core\Router\RouterInterface::class);
        $router->method('match')->willReturn(self::routeResult('dispatch'));

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturnSelf();
        $handler = (new FinalRequestHandler($container))->withRouter($router);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Route controller must return a PSR-7 response');
        $handler->handle($request);
    }

    private static function routeResult(string $controllerMethod): RouteResult
    {
        return new RouteResult(
            new Route('/', ['GET'], 'test.route', 'TestController', $controllerMethod),
            [],
            'GET',
        );
    }
}

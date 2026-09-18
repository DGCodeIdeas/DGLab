<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Http\CallableMiddlewareAdapter;
use SovereignStack\Core\Http\MiddlewareResolver;

final class MiddlewareResolverTest extends TestCase
{
    public function testMiddlewareInterfacePassesThrough(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $resolver = new MiddlewareResolver($container);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $result = $resolver->resolve($middleware);

        self::assertSame($middleware, $result);
    }

    public function testCallableIsWrappedInAdapter(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $resolver = new MiddlewareResolver($container);

        $callable = function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            return $this->createMock(ResponseInterface::class);
        };

        $result = $resolver->resolve($callable);

        self::assertInstanceOf(CallableMiddlewareAdapter::class, $result);
    }

    public function testClassStringResolvedViaContainer(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('SomeMiddleware')->willReturn($middleware);

        $resolver = new MiddlewareResolver($container);
        $result = $resolver->resolve('SomeMiddleware');

        self::assertSame($middleware, $result);
    }

    public function testClassStringResolvingToNonMiddlewareThrowsTypeError(): void
    {
        $notMiddleware = new \stdClass();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('NotMiddleware')->willReturn($notMiddleware);

        $resolver = new MiddlewareResolver($container);

        $this->expectException(\TypeError::class);
        $resolver->resolve('NotMiddleware');
    }

    public function testInvalidTypeThrowsTypeError(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $resolver = new MiddlewareResolver($container);

        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line intentional type violation for testing */
        $resolver->resolve(42);
    }

    /**
     * Class-string of a NON-EXISTENT class WITHOUT a container: the
     * container-less string branch in resolve() explicitly checks
     * class_exists() and throws a LogicException with a clear "class
     * does not exist" message. Previously this path fell through to an
     * unreachable TypeError with a misleading "got string" message;
     * the P2 fix added the explicit class_exists() guard.
     */
    public function testResolveNonExistentClassStringWithoutContainerThrowsLogicException(): void
    {
        // No container passed — exercises the container-less string branch.
        $resolver = new MiddlewareResolver(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('class does not exist');

        $resolver->resolve('SovereignStack\\Core\\Http\\Tests\\Fixture\\NonExistentMiddleware');
    }
}

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

        /** @phpstan-ignore-next-line intentional type violation */
        $this->expectException(\TypeError::class);
        $resolver->resolve(42);
    }
}

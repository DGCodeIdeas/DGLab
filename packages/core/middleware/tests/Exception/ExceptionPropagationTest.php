<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Http\MiddlewarePipeline;
use SovereignStack\Core\Http\MiddlewareResolver;
use SovereignStack\Core\Http\Response;

final class ExceptionPropagationTest extends TestCase
{
    public function testExceptionFromMiddlewarePropagatesUncaught(): void
    {
        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            throw new \RuntimeException('boom');
        });

        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $pipeline->handle($request);
    }

    public function testExceptionFromFinalHandlerPropagatesUncaught(): void
    {
        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('final handler failed');
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('final handler failed');
        $pipeline->handle($request);
    }

    public function testExceptionFromInnerMiddlewareReachesOuterMiddleware(): void
    {
        $caughtException = null;

        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

        // Outer middleware catches and records the exception
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$caughtException): ResponseInterface {
            try {
                return $handler->handle($req);
            } catch (\RuntimeException $e) {
                $caughtException = $e;
                return new Response(500);
            }
        });

        // Inner middleware throws
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            throw new \RuntimeException('inner failure');
        });

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $pipeline->handle($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertInstanceOf(\RuntimeException::class, $caughtException);
        self::assertSame('inner failure', $caughtException->getMessage());
    }
}

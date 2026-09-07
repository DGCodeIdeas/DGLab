<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Immutability;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Http\MiddlewarePipeline;
use SovereignStack\Core\Http\MiddlewareResolver;
use SovereignStack\Core\Http\Response;

final class PipelineImmutabilityTest extends TestCase
{
    private function createFinalHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };
    }

    public function testPipeAfterHandleThrowsLogicException(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        $request = $this->createMock(ServerRequestInterface::class);
        $pipeline->handle($request);

        $this->expectException(\LogicException::class);
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($req);
        });
    }

    public function testSecondHandleDoesNotReFreeze(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        $request = $this->createMock(ServerRequestInterface::class);

        // First handle freezes
        $pipeline->handle($request);

        // Second handle should NOT throw — it just re-runs with cursor reset
        $response = $pipeline->handle($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCursorResetsBetweenRequests(): void
    {
        $finalHandler = $this->createFinalHandler();
        $callCount = 0;

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$callCount): ResponseInterface {
            $callCount++;
            return $handler->handle($req);
        });

        $request = $this->createMock(ServerRequestInterface::class);

        // First request: middleware called once
        $pipeline->handle($request);
        self::assertSame(1, $callCount);

        // Second request: middleware called again (cursor reset)
        $pipeline->handle($request);
        self::assertSame(2, $callCount);
    }

    public function testFrozenFlagPersistsAcrossRequests(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        $request = $this->createMock(ServerRequestInterface::class);
        $pipeline->handle($request);

        // After first handle, pipe() should throw regardless of how many times handle() is called
        $pipeline->handle($request); // second handle
        $pipeline->handle($request); // third handle

        $this->expectException(\LogicException::class);
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($req);
        });
    }
}

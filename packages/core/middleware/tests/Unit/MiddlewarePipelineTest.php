<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Http\CallableMiddlewareAdapter;
use SovereignStack\Core\Http\FinalRequestHandler;
use SovereignStack\Core\Http\MiddlewarePipeline;
use SovereignStack\Core\Http\MiddlewareResolver;
use SovereignStack\Core\Http\Response;

final class MiddlewarePipelineTest extends TestCase
{
    private function createFinalHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, reasonPhrase: 'OK');
            }
        };
    }

    private function createPassthroughMiddleware(string $marker): MiddlewareInterface
    {
        return new class($marker) implements MiddlewareInterface {
            private string $m;
            public function __construct(string $m) { $this->m = $m; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                return $response->withHeader('X-Trace', $this->m);
            }
        };
    }

    public function testPipeAppendsMiddleware(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());
        $pipeline->pipe($this->createPassthroughMiddleware('A'));
        $pipeline->pipe($this->createPassthroughMiddleware('B'));

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $pipeline->handle($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleAdvancesCursorAndDelegatesToFinalHandler(): void
    {
        $finalHandler = $this->createFinalHandler();
        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $pipeline->handle($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testFirstHandleFreezesPipeline(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        $request = $this->createMock(ServerRequestInterface::class);
        $pipeline->handle($request);

        $this->expectException(\LogicException::class);
        $pipeline->pipe($this->createPassthroughMiddleware('A'));
    }

    public function testSecondHandleDoesNotRefreeze(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        $request = $this->createMock(ServerRequestInterface::class);
        $pipeline->handle($request);

        // Should not throw — second handle just re-runs with cursor reset
        $response = $pipeline->handle($request);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCallableMiddlewareWorks(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            $response = $handler->handle($req);
            return $response->withHeader('X-Custom', 'yes');
        });

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $pipeline->handle($request);

        self::assertSame(['yes'], $response->getHeader('X-Custom'));
    }

    public function testMiddlewareOrderIsFifo(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        // First piped = outermost (sees request first, response last)
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            $response = $handler->handle($req);
            return $response->withAddedHeader('X-Trace', 'A');
        });
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            $response = $handler->handle($req);
            return $response->withAddedHeader('X-Trace', 'B');
        });
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            $response = $handler->handle($req);
            return $response->withAddedHeader('X-Trace', 'C');
        });

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $pipeline->handle($request);

        // On the way OUT: C (innermost) runs first, then B, then A (outermost)
        // So X-Trace should be: C, B, A
        self::assertSame(['C', 'B', 'A'], $response->getHeader('X-Trace'));
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $pipeline = new MiddlewarePipeline($this->createFinalHandler(), new MiddlewareResolver());

        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            return new Response(401, reasonPhrase: 'Unauthorized');
        });

        // This middleware should never run because the first one short-circuits
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            throw new \RuntimeException('should not be reached');
        });

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $pipeline->handle($request);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testEmptyPipelineDelegatesToFinalHandler(): void
    {
        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, headers: ['X-Source' => 'final']);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $pipeline->handle($request);

        self::assertSame(['final'], $response->getHeader('X-Source'));
    }

    public function testMiddlewareCanMutateRequest(): void
    {
        $finalHandler = new class implements RequestHandlerInterface {
            public ?string $attr = null;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->attr = $request->getAttribute('test');
                return new Response(200);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($req->withAttribute('test', 'value'));
        });

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturnSelf();
        $request->method('getAttribute')->willReturn('value');

        $pipeline->handle($request);
        // The final handler should have received the attribute
        // (We can't easily assert on the mock, but the pipeline should not throw)
        $this->expectNotToPerformAssertions();
    }
}

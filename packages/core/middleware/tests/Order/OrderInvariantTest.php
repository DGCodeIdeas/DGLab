<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Order;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Http\MiddlewarePipeline;
use SovereignStack\Core\Http\MiddlewareResolver;
use SovereignStack\Core\Http\Response;

final class OrderInvariantTest extends TestCase
{
    public function testMiddlewareOrderIsBoustrophedon(): void
    {
        // The request walks inward: A → B → C → final handler
        // The response walks back outward: C → B → A
        // So X-Trace accumulates as: C, B, A (last-piped = first on the way out)

        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

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

        // C is innermost (last piped) → runs first on the way out
        // A is outermost (first piped) → runs last on the way out
        // So order is: C, B, A — NOT A, B, C
        self::assertSame(['C', 'B', 'A'], $response->getHeader('X-Trace'));
    }

    public function testFifoOrderWithThreeMiddleware(): void
    {
        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler, new MiddlewareResolver());

        $callOrder = [];

        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$callOrder): ResponseInterface {
            $callOrder[] = 'A-pre';
            $response = $handler->handle($req);
            $callOrder[] = 'A-post';
            return $response;
        });
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$callOrder): ResponseInterface {
            $callOrder[] = 'B-pre';
            $response = $handler->handle($req);
            $callOrder[] = 'B-post';
            return $response;
        });
        $pipeline->pipe(function(ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$callOrder): ResponseInterface {
            $callOrder[] = 'C-pre';
            $response = $handler->handle($req);
            $callOrder[] = 'C-post';
            return $response;
        });

        $request = $this->createMock(ServerRequestInterface::class);
        $pipeline->handle($request);

        // Request walks inward: A-pre, B-pre, C-pre
        // Response walks outward: C-post, B-post, A-post
        self::assertSame(
            ['A-pre', 'B-pre', 'C-pre', 'C-post', 'B-post', 'A-post'],
            $callOrder
        );
    }
}

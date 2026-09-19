<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Http\CallableMiddlewareAdapter;
use SovereignStack\Core\Http\Response;

final class CallableMiddlewareAdapterTest extends TestCase
{
    public function testProcessInvokesCallable(): void
    {
        $called = false;
        $callable = function(ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$called): ResponseInterface {
            $called = true;
            return new Response(200);
        };

        $adapter = new CallableMiddlewareAdapter($callable);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $adapter->process($request, $handler);

        self::assertTrue($called);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testProcessPassesRequestAndHandlerToCallable(): void
    {
        $receivedRequest = null;
        $receivedHandler = null;

        $callable = function(ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$receivedRequest, &$receivedHandler): ResponseInterface {
            $receivedRequest = $req;
            $receivedHandler = $handler;
            return new Response(200);
        };

        $adapter = new CallableMiddlewareAdapter($callable);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $adapter->process($request, $handler);

        self::assertSame($request, $receivedRequest);
        self::assertSame($handler, $receivedHandler);
    }

    public function testProcessReturnsResponseFromCallable(): void
    {
        $expectedResponse = new Response(201, reasonPhrase: 'Created');

        $callable = function(ServerRequestInterface $req, RequestHandlerInterface $handler) use ($expectedResponse): ResponseInterface {
            return $expectedResponse;
        };

        $adapter = new CallableMiddlewareAdapter($callable);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $result = $adapter->process($request, $handler);

        self::assertSame($expectedResponse, $result);
    }
}

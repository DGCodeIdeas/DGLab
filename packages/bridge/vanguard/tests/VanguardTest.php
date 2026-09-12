<?php

declare(strict_types=1);

namespace SovereignStack\Bridge\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Bridge\Vanguard;
use SovereignStack\Bridge\ContractRegistry;
use SovereignStack\Bridge\WafInspector;
use SovereignStack\Bridge\DefaultDtoTransformer;
use SovereignStack\Core\Http\ResponseFactory;
use SovereignStack\Core\Http\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Vanguard middleware end-to-end tests: contract enforcement, WAF block,
 * DTO transformation, audit logging, happy path forwarding.
 *
 * @package SovereignStack\Bridge\Tests
 */
final class VanguardTest extends TestCase
{
    private Vanguard $vanguard;
    private ContractRegistry $contracts;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->contracts = new ContractRegistry();
        $this->responseFactory = new ResponseFactory();
        $this->vanguard = new Vanguard(
            contracts: $this->contracts,
            waf: new WafInspector(),
            responseFactory: $this->responseFactory,
        );
    }

    public function testUnregisteredRouteReturns403(): void
    {
        $handler = $this->passthroughHandler();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/unregistered');

        $response = $this->vanguard->process($request, $handler);
        self::assertSame(403, $response->getStatusCode());
    }

    public function testRegisteredRouteForwardsToHandler(): void
    {
        $this->contracts->registerContract('/hello', new DefaultDtoTransformer());
        $handler = $this->helloHandler();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/hello');

        $response = $this->vanguard->process($request, $handler);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello World', (string) $response->getBody());
    }

    public function testWafBlockReturns400(): void
    {
        $this->contracts->registerContract('/hello', new DefaultDtoTransformer());
        $handler = $this->helloHandler();
        // WAF scans raw body — put the attack payload in the body, not the
        // query string (URL-encoding would hide <script> from the regex).
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/hello');
        $body = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $body->write('<script>alert(1)</script>');
        $body->rewind();
        $request = $request->withBody($body);

        $response = $this->vanguard->process($request, $handler);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Bad Request', (string) $response->getBody());
    }

    public function testDtoTransformationStripsInternalFields(): void
    {
        $this->contracts->registerContract('/api/data', new DefaultDtoTransformer());
        $handler = $this->jsonHandler([
            'public_field' => 'visible',
            '_internal_field' => 'should_be_stripped',
            'nested' => [
                'visible' => 'yes',
                '_hidden' => 'no',
            ],
        ]);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/data');

        $response = $this->vanguard->process($request, $handler);
        $body = json_decode((string) $response->getBody(), true);

        self::assertArrayHasKey('public_field', $body);
        self::assertArrayNotHasKey('_internal_field', $body);
        self::assertArrayHasKey('nested', $body);
        self::assertArrayHasKey('visible', $body['nested']);
        self::assertArrayNotHasKey('_hidden', $body['nested']);
    }

    public function testRegisterContractDelegatesToRegistry(): void
    {
        $this->vanguard->registerContract('/test', new DefaultDtoTransformer());
        self::assertTrue($this->contracts->has('/test'));
    }

    private function passthroughHandler(): RequestHandlerInterface
    {
        return new class ($this->responseFactory) implements RequestHandlerInterface {
            public function __construct(private ResponseFactory $rf) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->rf->createResponse(200);
            }
        };
    }

    private function helloHandler(): RequestHandlerInterface
    {
        return new class ($this->responseFactory) implements RequestHandlerInterface {
            public function __construct(private ResponseFactory $rf) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = $this->rf->createResponse(200);
                $response->getBody()->write('Hello World');
                return $response;
            }
        };
    }

    private function jsonHandler(array $data): RequestHandlerInterface
    {
        return new class ($this->responseFactory, $data) implements RequestHandlerInterface {
            public function __construct(
                private ResponseFactory $rf,
                private array $data,
            ) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = $this->rf->createResponse(200);
                $response->getBody()->write(json_encode($this->data, JSON_PRETTY_PRINT));
                return $response;
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace SovereignStack\Bridge;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The Vanguard — the public-facing security chokepoint of SovereignStack.
 *
 * Final class: subclassing would let a child reorder the chain, defeating
 * the load-bearing ordering documented in BoundaryContractInterface.
 *
 * Depth-2 scope: enforces contract lookup (default-deny 403) and WAF inspection.
 * JWT verification, rate limiting, and network forwarding are pass-through stubs
 * (the request is delegated to the next handler — the Kernel's pipeline).
 * Audit events are logged via PSR-3. When HUB-02/HUB-04/HUB-06 land, the
 * stubs are replaced — the interfaces and chain order are unchanged.
 *
 * @package SovereignStack\Bridge
 */
final class Vanguard implements BoundaryContractInterface
{
    public function __construct(
        private readonly ContractRegistry $contracts,
        private readonly WafInspector $waf,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function registerContract(string $contractId, DtoTransformerInterface $transformer): void
    {
        $this->contracts->registerContract($contractId, $transformer);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getUri()->getPath();

        // Step 1: JWT verification (depth-2 pass-through — no real HUB-04).
        // Real implementation will verify Authorization: Bearer <jwt> via HUB-04.
        $this->logger->debug('Vanguard: JWT verification (depth-2 pass-through)', ['route' => $route]);

        // Step 2: Rate limiting (depth-2 pass-through — no real HUB-02/Redis).
        $this->logger->debug('Vanguard: rate-limit check (depth-2 pass-through)', ['route' => $route]);

        // Step 3: WAF inspection (real — pure PCRE, no deps).
        $queryString = $request->getUri()->getQuery();
        $rawBody = '';
        $body = $request->getBody();
        if ($body->isReadable()) {
            $rawBody = (string) $body;
            // Rewind so downstream handlers can read the body.
            if ($body->isSeekable()) {
                $body->rewind();
            }
        }

        $wafHit = $this->waf->inspect($queryString, $rawBody);
        if ($wafHit !== null) {
            $this->logger->warning('Vanguard: WAF block', [
                'route' => $route,
                'pattern' => $wafHit,
            ]);
            $response = $this->responseFactory->createResponse(400);
            $response->getBody()->write('Bad Request');
            return $response;
        }

        // Step 4: Contract lookup (default-deny).
        $transformer = $this->contracts->resolve($route);
        if ($transformer === null) {
            $this->logger->info('Vanguard: contract not found (403 default-deny)', ['route' => $route]);
            $response = $this->responseFactory->createResponse(403);
            $response->getBody()->write('Forbidden');
            return $response;
        }

        // Step 5: Forward — at depth 2, delegate to the next handler (Kernel pipeline).
        $response = $handler->handle($request);

        // Step 6: DTO transformation — strip internal fields from the response.
        $responseBody = (string) $response->getBody();
        $decoded = json_decode($responseBody, true);
        if (is_array($decoded)) {
            $transformed = $transformer->transformResponse($decoded);
            $response = $response->withBody($this->streamFromJson($transformed));
        }

        // Step 7: Audit (depth-2 pass-through — log via PSR-3).
        $this->logger->info('Vanguard: tier_crossing audit', [
            'route' => $route,
            'status' => $response->getStatusCode(),
            'outcome' => 'forwarded',
        ]);

        return $response;
    }

    /**
     * Create a PSR-7 stream from JSON-encoded data.
     */
    private function streamFromJson(mixed $data): \Psr\Http\Message\StreamInterface
    {
        $json = json_encode($data, JSON_PRETTY_PRINT) ?: '';
        $stream = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $stream->write($json);
        $stream->rewind();
        return $stream;
    }
}

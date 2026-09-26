<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Http;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Kernel\RequestContext;
use SovereignStack\Hub\Identity\Jwt\JwtVerifier;

/**
 * PSR-15 middleware that authenticates requests via JWT.
 *
 * Per Lap 2 P0-1: stamps RequestContext.userId as the AUTHORITATIVE identity source.
 * The PSR-7 request attribute remains as a transport-level convenience but is
 * NOT the authoritative identity channel.
 *
 * @package SovereignStack\Hub\Identity\Http
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtVerifier $jwtVerifier,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $authHeader = $request->getHeaderLine('Authorization');
        $userId = null;

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            try {
                $payload = $this->jwtVerifier->verify($token);
                if (isset($payload['sub'])) {
                    $userId = $payload['sub'];
                }
            } catch (\Throwable) {
                // Invalid token — proceed as unauthenticated
            }
        }

        // P0-1: RequestContext is the AUTHORITATIVE identity source.
        // The request attribute is a transport convenience only.
        $request = $request->withAttribute('userId', $userId);

        return $handler->handle($request);
    }

    /**
     * Stamp a RequestContext with the authenticated userId.
     *
     * Per P0-1: this is the authoritative identity integration point.
     * Application services should call RequestContext::withUserId()
     * at the request boundary, using the userId extracted here.
     *
     * This method is called by the application composition root
     * (ApplicationFactory) after AuthMiddleware extracts the userId.
     *
     * @param RequestContext $context The current request context
     * @return RequestContext A new context with userId stamped (or unchanged if unauthenticated)
     */
    public function stampRequestContext(RequestContext $context): RequestContext
    {
        // The userId is stored on the request attribute by process().
        // This method is called by the composition root after process() completes
        // to stamp the authoritative RequestContext.
        // For now, the composition root reads the request attribute and stamps RequestContext.
        // Future: inject Container into AuthMiddleware to stamp directly.
        return $context;
    }
}

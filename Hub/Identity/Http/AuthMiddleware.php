<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Identity\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Core\Kernel\RequestContext;
use SovereignStack\Hub\Identity\Jwt\JwtVerifier;
use SovereignStack\Hub\Identity\Session\SessionStoreInterface;

/**
 * PSR-15 middleware that authenticates requests via JWT Bearer or session cookie.
 *
 * Per ADR-024: Dual transport — session cookies (same-origin) + JWT Bearer (API clients).
 * Per P0-1: stamps RequestContext.userId as the AUTHORITATIVE identity source.
 * The PSR-7 request attribute remains as a transport-level convenience but is
 * NOT the authoritative identity channel.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ?JwtVerifier $jwtVerifier = null,
        private readonly ?SessionStoreInterface $sessionStore = null,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $userId = $this->authenticate($request);

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
    public function stampRequestContext(RequestContext $context, ?string $userId): RequestContext
    {
        if ($userId === null) {
            return $context;
        }
        return $context->withUserId($userId);
    }

    private function authenticate(ServerRequestInterface $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $hasBearerToken = str_starts_with($authHeader, 'Bearer ');

        if ($hasBearerToken) {
            $token = substr($authHeader, 7);
            try {
                if ($this->jwtVerifier === null) {
                    throw new \RuntimeException('JWT verifier not configured');
                }
                $payload = $this->jwtVerifier->verify($token);
                if (isset($payload['sub'])) {
                    return (string)$payload['sub'];
                }
                throw new \RuntimeException('Invalid JWT: missing subject claim');
            } catch (\Throwable $e) {
                // Invalid Bearer token — reject with 401, do NOT fall back to session cookie
                throw new \RuntimeException('Invalid Bearer token: ' . $e->getMessage(), 401);
            }
        }

        // Try session cookie (same-origin browser apps) ONLY when no Bearer token was present
        $cookieHeader = $request->getHeaderLine('Cookie');
        if ($this->sessionStore !== null && $cookieHeader !== '') {
            $sessionId = $this->parseSessionCookie($cookieHeader);
            if ($sessionId !== null) {
                $session = $this->sessionStore->get($sessionId);
                if ($session !== null && isset($session['userId'])) {
                    return (string)$session['userId'];
                }
            }
        }

        return null;
    }

    private function parseSessionCookie(string $cookieHeader): ?string
    {
        $cookies = [];
        foreach (explode(';', $cookieHeader) as $cookie) {
            $parts = explode('=', trim($cookie), 2);
            if (count($parts) === 2) {
                $cookies[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $cookies['DGLAB_SESSION'] ?? null;
    }
}
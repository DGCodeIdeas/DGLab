<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Http;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SovereignStack\Hub\Identity\Jwt\JwtVerifier;
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
        $request = $request->withAttribute('userId', $userId);
        return $handler->handle($request);
    }
}

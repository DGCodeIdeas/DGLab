<?php

namespace DGLab\Services\Nexus;

use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Core\Application;
use DGLab\Models\User;
use RuntimeException;

class HandshakeValidator
{
    protected JWTService $jwtService;
    protected UserRepository $userRepository;
    protected string $secret;
    protected string $algo;

    public function __construct(JWTService $jwtService, UserRepository $userRepository, string $secret, string $algo = 'HS256')
    {
        $this->jwtService = $jwtService;
        $this->userRepository = $userRepository;
        $this->secret = $secret;
        $this->algo = $algo;
    }

    /**
     * Validates the handshake request and returns the authenticated user.
     *
     * @param mixed $request Swoole\Http\Request
     * @return User
     * @throws RuntimeException
     */
    public function validate($request): User
    {
        $token = $this->extractToken($request);

        if (!$token) {
            throw new RuntimeException('Authentication token missing', 401);
        }

        try {
            $payload = $this->jwtService->decode($token, $this->secret, [$this->algo]);

            if (!isset($payload['sub'])) {
                throw new RuntimeException('Invalid token payload', 401);
            }

            $user = $this->userRepository->find($payload['sub']);

            if (!$user) {
                throw new RuntimeException('User not found', 401);
            }

            return $user;
        } catch (\Exception $e) {
            throw new RuntimeException('Invalid or expired token: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Extracts token from header, query string, or sub-protocol.
     */
    protected function extractToken($request): ?string
    {
        // 1. Check Authorization header
        if (isset($request->header['authorization'])) {
            $auth = $request->header['authorization'];
            if (str_starts_with($auth, 'Bearer ')) {
                return substr($auth, 7);
            }
        }

        // 2. Check token query parameter
        if (isset($request->get['token'])) {
            return $request->get['token'];
        }

        // 3. Check sec-websocket-protocol (often used for token passing in browser clients)
        if (isset($request->header['sec-websocket-protocol'])) {
            return $request->header['sec-websocket-protocol'];
        }

        return null;
    }
}

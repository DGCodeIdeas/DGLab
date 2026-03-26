<?php

namespace DGLab\Services\Auth\Guards;

use DGLab\Models\User;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use DGLab\Core\Request;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\KeyManagementService;
use DGLab\Core\Application;

class JwtGuard implements AuthGuardInterface
{
    protected UserRepository $provider;
    protected Request $request;
    protected JWTService $jwtService;
    protected ?User $user = null;
    public function __construct(UserRepository $provider, Request $request, JWTService $jwtService)
    {
        $this->provider = $provider;
        $this->request = $request;
        $this->jwtService = $jwtService;
    }
    public function user(): ?User
    {
        if (!is_null($this->user)) {
            return $this->user;
        }
        $token = $this->getTokenFromRequest();
        if (!$token) {
            return null;
        }
        try {
            $keyService = Application::getInstance()->get(KeyManagementService::class);
            $key = config('auth.jwt.algo') === 'RS256' ? $keyService->getKey(config('auth.jwt.key_name'), 'public') : config('auth.jwt.secret');
            $payload = $this->jwtService->decode($token, $key, [config('auth.jwt.algo', 'HS256')]);
            if (isset($payload['sub'])) {
                $this->user = $this->provider->find($payload['sub']);
            }
        } catch (\Exception $e) {
            return null;
        }
        return $this->user;
    }
    public function id(): int|string|null
    {
        return $this->user() ? $this->user()->id : null;
    }
    public function check(): bool
    {
        return !is_null($this->user());
    }
    public function guest(): bool
    {
        return !$this->check();
    }
    public function validate(array $credentials = []): bool
    {
        return false;
    }
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        return false;
    }
    public function login(User $user, bool $remember = false): mixed
    {
        $this->setUser($user);
        return $this->createToken($user);
    }
    public function createToken(User $user): string
    {
        $keyService = Application::getInstance()->get(KeyManagementService::class);
        $key = config('auth.jwt.algo') === 'RS256' ? $keyService->getKey(config('auth.jwt.key_name'), 'private') : config('auth.jwt.secret');
        $payload = [ 'iss' => config('app.url', 'http://localhost'), 'sub' => $user->id, 'iat' => time(), 'exp' => time() + (config('auth.jwt.ttl', 60) * 60), 'nbf' => time(), 'jti' => bin2hex(random_bytes(16)), ];
        return $this->jwtService->encode($payload, $key, config('auth.jwt.algo', 'HS256'));
    }
    public function setUser(User $user): void
    {
        $this->user = $user;
    }
    public function logout(): void
    {
        $this->user = null;
    }
    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->getHeader('Authorization') ?: '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}

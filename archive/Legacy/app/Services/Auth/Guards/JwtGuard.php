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
        if ($this->user) {
            return $this->user;
        }
        $token = $this->getTokenFromRequest();
        if (!$token) {
            return null;
        }
        try {
            $key = config('auth.jwt.secret');
            $payload = $this->jwtService->decode($token, $key, [config('auth.jwt.algo', 'HS256')]);
            return $this->user = $this->provider->find($payload['sub']);
        } catch (\Exception $e) {
            return null;
        }
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
        $idnt = $credentials['email'] ?? ($credentials['login'] ?? ($credentials['username'] ?? ''));
        $user = $this->provider->findByIdentifier($idnt);
        return $user && password_verify($credentials['password'] ?? '', $user->password_hash);
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        if ($this->validate($credentials)) {
            $idnt = $credentials['email'] ?? ($credentials['login'] ?? ($credentials['username'] ?? ''));
            $this->user = $this->provider->findByIdentifier($idnt);
            return true;
        }
        return false;
    }

    public function login(User $user, bool $remember = false): mixed
    {
        $this->user = $user;
        return $this->createToken($user);
    }

    public function createToken(User $user): string
    {
        $payload = ['sub' => $user->id, 'iat' => time(), 'exp' => time() + 3600];
        return $this->jwtService->encode($payload, config('auth.jwt.secret'), config('auth.jwt.algo', 'HS256'));
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
    public function can(string $permission, array $arguments = []): bool
    {
        return $this->user() && $this->user()->can($permission, $arguments);
    }
    public function logout(): void
    {
        $this->user = null;
    }

    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->getHeader('Authorization') ?: '';
        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }
}
